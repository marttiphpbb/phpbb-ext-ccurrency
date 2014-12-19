<?php

/**
* @package phpBB Extension - marttiphpbb community currency
* @copyright (c) 2014 marttiphpbb <info@martti.be>
* @license http://opensource.org/licenses/MIT
*/

namespace marttiphpbb\ccurrency\operators;

use phpbb\cache\service as cache;
use phpbb\config\db as config;
use phpbb\content_visibility;
use phpbb\db\driver\factory as db;
use phpbb\user;
use phpbb\controller\helper;

use marttiphpbb\ccurrency\util\uuid_generator;
use marttiphpbb\ccurrency\util\uuid_validator;


class transaction
{

	protected $cache;
	protected $config;
	protected $content_visibility;
	protected $db;
	protected $user;
	protected $helper;
	protected $cc_transactions_table;
	protected $topics_table;
	protected $users_table;
	protected $is_time_banking;

   /**
   * @param cache $cache
   * @param config   $config
   * @param content_visibility $content_visibility 
   * @param db   $db  
   * @param user   $user 
   * @param helper $helper   
   * @param string $cc_transactions_table 
   * @param string $cc_topics_table 
   * @param string $cc_users_table 
   */
   
   public function __construct(
		cache $cache, 
		config $config,
		content_visibility $content_visibility, 
		db $db,
		user $user, 
		helper $helper, 
		$cc_transactions_table,
		$topics_table,
		$users_table
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->user = $user;
		$this->helper = $helper;
		$this->cc_transactions_table = $cc_transactions_table;
		$this->topics_table = $topics_table;
		$this->users_table = $users_table;
		
		$this->is_time_banking = ($this->config['cc_currency_rate'] > 0) ? false : true;
   }

	/**
	* @param string $username
	* @return array
	*/
	public function get_user_by_username($username = '')
	{
		$sql_ary = array(
			'SELECT'	=> 'u.user_id, u.username, u.user_colour',
			'FROM'		=> array(
				$this->users_table => 'u',
			),
			'WHERE'		=> 'u.username = \'' . $this->db->sql_escape($username) . '\'',

		);
		
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		$user_ary = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $user_ary;			
	}
				
	/**
	* @param string $unique_id
	* @return array
	*/
	public function transaction_unique_id_exists($unique_id = '')
	{	
		$sql_ary = array(
			'SELECT'	=> 'tr.transaction_unique_id',
			'FROM'		=> array(
				$this->cc_transactions_table => 'tr',
			),
			'WHERE'		=> 'tr.transaction_unique_id = \'' . $this->db->sql_escape($unique_id) . '\'',
		);
		
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		
		return ($this->db->sql_fetchfield('transaction_unique_id') == $unique_id)) ? true : false;
	}

	/**
	* @param string $unique_id
	* @param array $from_user_ary
	* @param array $to_user_ary
	* @param string $description
	* @param int $amount (seconds)
	* @return int|false transaction_id
	*/
	public function insert_transaction($unique_id, $from_user_ary, $to_user_ary, $description, $amount)
	{
		$sql_ary = array(
			'transaction_unique_id'			=> $unique_id,
			'transaction_from_user_id'		=> $from_user_ary['user_id'],
			'transaction_from_username'		=> $from_user_ary['username'],
			'transaction_from_user_colour'	=> $from_user_ary['user_colour'],
			'transaction_to_user_id'		=> $to_user_ary['user_id'],
			'transaction_to_username'		=> $to_user_ary['username'],
			'transaction_to_user_colour'	=> $to_user_ary['user_colour'],					
			'transaction_description'		=> $description,					
			'transaction_amount'			=> $amount_seconds,					
			'transaction_confirmed'			=> true,
			'transaction_confirmed_at'		=> $now,
			'transaction_created_by'		=> $from_user_ary['user_id'],
			'transaction_created_at'		=> $now,				
		);

		$this->db->sql_transaction('begin');

		$r = $this->db->sql_query('INSERT INTO ' . $this->cc_transactions_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		

		$sql_ary = array(
			'user_cc_balance'			=> 'user_cc_balance - ' . $amount_seconds,
			'user_cc_transaction_count'	=> 'user_cc_transaction_count  + 1',
		);

		$sql = 'UPDATE ' . $this->users_table . '
			SET user_cc_balance = user_cc_balance - ' . $amount_seconds . ',
			user_cc_transaction_count = user_cc_transaction_count + 1
			WHERE user_id = ' . $from_user_ary['user_id'];
		$this->db->sql_query($sql);
					
		$sql_ary = array(
			'user_cc_balance'			=> 'user_cc_balance + ' . $amount_seconds,
		);

		$sql = 'UPDATE ' . $this->users_table . '
			SET user_cc_balance = user_cc_balance + ' . $amount_seconds . '
			WHERE user_id = ' . $to_user_ary['user_id'];
		$this->db->sql_query($sql);					
	
		$this->db->sql_transaction('commit');
		
		if ($r)
		{
			return $this->db->sql_nextid();
		}
		
		return false;
	}


	/**
	 * @param string $q
	 * @return int
	*/
	public function get_transactions_count($search_query)
	{
		$sql_where = 'tr.transaction_parent_id IS NULL';
		
		if ($search_query)
		{
			$sql_where .= ' tr.transaction_description ' . $this->db->sql_like_expression(str_replace('*', $this->db->get_any_char(), utf8_clean_string($search_query)));
		}

		$sql_ary = array(
			'SELECT' => 'count(*) as num', 
			'FROM' => array(
				$this->cc_transactions_table => 'tr',
			),
			'WHERE' => $sql_where,
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		$transactions_count = $this->db->sql_fetchfield('num');
		$this->db->sql_freeresult($result);
		
		return $transactions_count;
	}
	
	
	/**
	 * @param string $search_query
	 * @param string $sort_by
	 * @param string $sort_dir
	 * @param int $start
	 * @param int $limit
	 * @return array
	*/
	public function get_transactions(
		$search_query = '', 
		$sort_by = 'created_at', 
		$sort_dir = 'desc',
		$start = 0,
		$limit = 25
	)
	{

		$params = array();

		if ($sort_by != 'created_at')
		{
			$params['sort_by'] = $sort_by;
		}
		
		if ($sort_dir != 'desc')
		{
			$params['sort_dir'] = $sort_dir;
		}
		
		if ($search_query)
		{
			$params['q'] = $search_query;
		}		
		
		$sql_ary = array(
			'SELECT'	=> 'tr.*',
			'FROM'		=> array(
				$this->cc_transactions_table => 'tr',
			),
			'WHERE'		=> $sql_where,
			'ORDER_BY'	=> 'tr.transaction_' . $sort_by . ' ' . (($sort_dir == 'desc') ? 'DESC' : 'ASC'),	
			'LIMIT'		=> $limit . ', ' . $start,
		);
		
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		$transactions = $this->db->sql_fetchrowset($result));
		$this->db->sql_freeresult($result);
		return $transactions;
	}
	
	/**
	 * @param int $transaction_id
	 * @return array
	*/
	public function get_transaction($transaction_id)
	{
		$sql_ary = array(
			'SELECT'	=> 'tr.*',
			'FROM'		=> array(
				$this->cc_transactions_table => 'tr',
			),
			'WHERE'		=> 'tr.transaction_id = ' . $transaction_id,
		);		

		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row;
	}
		
	/**
	 * @param int $transaction_id
	 * @return int
	*/
	public function get_children_transactions_count($transaction_id)
	{	
		$sql_ary = array(
			'SELECT' => 'count(*) as num', 
			'FROM' => array(
				$this->cc_transactions_table => 'tr',
			),
			'WHERE' => 'tr.transaction_parent_id = ' . $transaction_id,
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		$transactions_count = $this->db->sql_fetchfield('num');
		$this->db->sql_freeresult($result);
		return $transactions_count;
	}
}