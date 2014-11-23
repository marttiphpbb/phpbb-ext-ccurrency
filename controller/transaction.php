<?php

/**
* @package phpBB Extension - marttiphpbb community currency
* @copyright (c) 2014 marttiphpbb <info@martti.be>
* @license http://opensource.org/licenses/MIT
*/

namespace marttiphpbb\ccurrency\controller;

use phpbb\auth\auth;
use phpbb\cache\service as cache;
use phpbb\config\db as config;
use phpbb\content_visibility;
use phpbb\db\driver\factory as db;
use phpbb\request\request;
use phpbb\template\twig\twig as template;
use phpbb\user;
use phpbb\controller\helper;
use Symfony\Component\HttpFoundation\Response;


class transaction
{
	protected $auth;
	protected $cache;
	protected $config;
	protected $content_visibility;
	protected $db;
	protected $php_ext;
	protected $request;
	protected $template;
	protected $user;
	protected $helper;
	protected $root_path;
	protected $cc_transactions_table;
	protected $topics_table;
	protected $users_table;
	protected $is_time_banking;

   /**
   * @param auth $auth
   * @param cache $cache
   * @param config   $config
   * @param content_visibility $content_visibility 
   * @param db   $db
   * @param string $php_ext 
   * @param request   $request
   * @param template   $template  
   * @param user   $user 
   * @param helper $helper   
   * @param string $root_path 
   * @param string $cc_transactions_table 
   * @param string $cc_topics_table 
   * @param string $cc_users_table 
   */
   
   public function __construct(
		auth $auth, 
		cache $cache, 
		config $config,
		content_visibility $content_visibility, 
		db $db,
		$php_ext, 
		request $request, 
		template $template, 
		user $user, 
		helper $helper, 
		$root_path,
		$cc_transactions_table,
		$topics_table,
		$users_table
	)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->php_ext = $php_ext;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->helper = $helper;
		$this->root_path = $root_path;
		$this->cc_transactions_table = $cc_transactions_table;
		$this->topics_table = $topics_table;
		$this->users_table = $users_table;
		
		$this->is_time_banking = ($this->config['cc_currency_rate'] > 0) ? false : true;
   }

	/**  
	* @return Response
	*/
	public function listAction()
	{
		if (!$this->auth->acl_get('u_cc_viewtransactions'))
		{
			trigger_error('CC_NO_AUTH_VIEW_TRANSACTIONS');
		}		

		add_form_key('new_transaction');
		$error = array();
		
		if ($this->request->is_set_post('submit'))
		{
			if (!$this->auth->acl_get('u_cc_createtransactions'))
			{
				trigger_error('CC_NO_AUTH_CREATE_TRANSACTION');
			}
			
			$to_user = utf8_normalize_nfc($this->request->variable('to_user', '', true));
			$description = utf8_normalize_nfc($this->request->variable('description', '', true));
			
			if ($this->is_time_banking)
			{
				$hours = $this->request->variable('hours', 0);
				$minutes = $this->request->variable('minutes', 0);
				$seconds = ($hours * 3600) + ($minutes * 60);				
				
			}
			else
			{
				$amount = $this->request->variable('amount', 0);
				$seconds = $amount * $this->config['cc_currency_rate'];
			}
			
			
						
			if (!check_form_key('new_transaction'))
			{
				$error[] = $this->user->lang('FORM_INVALID');
			}

			if (empty($error))
			{
				if (utf8_clean_string($to_user) === '')
				{
					$error[] = $user->lang['CC_EMPTY_TO_USER'];
				}						
				if (utf8_clean_string($description) === '')
				{
					$error[] = $user->lang['CC_EMPTY_DESCRIPTION'];
				}
				if ($seconds < 1)
				{
					$error[] = $user->lang['CC_AMOUNT_NOT_POSITIVE'];					
				}	
					
				
				
				
			}
			
			$this->template->assign_var('S_DISPLAY_NEW_TRANSACTION', true);
		}	

		$topic_id = $this->request->variable('n_t', 0);
		$to_user_id = $this->request->variable('n_u', 0);
		
		// pre-fill fields in new transaction creation form
		if ($topic_id || $to_user_id)
		{
			if (!$this->auth->acl_get('u_cc_createtransactions'))
			{
				trigger_error('CC_NO_AUTH_CREATE_TRANSACTION');
			}

			if ($topic_id) 
			{
				$sql = 'SELECT forum_id 
					FROM ' . $this->topics_table . '
					WHERE topic_id = ' . $topic_id;
				$forum_id = '';
				
				
				$sql = 'SELECT topic_id, topic_title, topic_poster 
					FROM ' . $this->topics_table . '
					WHERE topic_id = ' . $topic_id . '
					AND ' . $this->content_visibility->get_visibility_sql('topic', $forum_id);
				
			}	



			
			
			$this->template->assign_var('S_DISPLAY_NEW_TRANSACTION', true);
		}	


		if ($this->is_time_banking)
		{
			$granularity = $this->config['cc_time_banking_granularity'];
						
			$minutes = round($seconds / 60);
			$hours = floor($minutes / 60);
			$minutes = $minutes - $hours * 60;

			if ($granularity && $granularity < 1801)
			{
				$minutes_options = '';
				
				for ($sec = 0; $sec < 3600; $sec = $sec + $granularity)
				{
							
					$value = round($sec / 60);
					$minutes_options .= '<option value="' . $value . '"';
					$minutes_options .= ($value == $minutes) ? ' selected="selected"' : '';
					$minutes_options .= '>' . str_pad($value, 2, '0', STR_PAD_LEFT) . '</option>';	
				}
			}
			
		}
		else
		{
			$amount = round($seconds / $this->config['cc_currency_rate']);
		}







		
		$this->template->assign_vars(array(
			'ERROR'		=> (sizeof($error)) ? implode('<br />', $error) : '',
			'U_ACTION'	=> $this->helper->route('marttiphpbb_cc_transactionlist_controller'),
			'S_AUTH_CREATE_TRANSACTION'	=> $this->auth->acl_get('u_cc_createtransactions'),
			'S_TIME_BANKING'	=> $this->is_time_banking,
			'S_MINUTES_OPTIONS' => $minutes_options,
			'HOURS'				=> $hours,
			'MINUTES'			=> $minutes,
			'AMOUNT'			=> $amount,
			'TO_USER'			=> $to_user,
			'DESCRIPTION'		=> $description,
			
			
			
		));
		
		// get transactions
		
		$start = $this->request->variable('start', 0);
//		$limit = $this->config['transactions_per_page'];
		$limit = 10;
		
		$sql_ary = array(
			'SELECT'	=> 'tr.*',
			'FROM'		=> array(
				$this->cc_transactions_table => 'tr',
			),
			'WHERE'		=> '1 = 1',
//			'ORDER_BY'	=> 't.topic_type ' . ((!$store_reverse) ? 'DESC' : 'ASC') . ', ' . $sql_sort_order,
		);
		
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query_limit($sql, $limit, $start);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$transaction_list[] = $row;
			
			
			$template->assign_block_vars('transactionrow', array(
				'FROM_USER' 	=> $row['transaction_from_user'],
				'U_FROM_USER'	=> '',
				'TO_USER'		=> $row['transaction_to_user'],
				'U_TO_USER'		=> '',
				'AMOUNT'		=> $this->transform->reverse($row['transaction_amount']),
				'DESCRIPTION'	=> $row['description'],
				'TIME'			=> $this->user->format_date($row['transaction_time']),
				'U_TRANSACTION'	=> '',
			));
		}
		$this->db->sql_freeresult($result);

		make_jumpbox(append_sid($this->root_path . 'viewforum.' . $this->php_ext));
		return $this->helper->render('transactions.html');
	}
   
	/**
	* @param int   $page   
	* @return Response
	*/
	public function showAction($id = 0)
	{

		return $this->helper->render('transaction.html');
	}   

	/**
	* @return Response
	*/
	public function newAction()
	{
	   
		
		
		return $this->helper->render('transaction_new.html');
	}

	/**
	* @return Response
	*/
	public function createAction()
	{
		
		
		return $this->helper->render('transaction_new.html');
	}

	private function find_user_by_username($username)
	{
		global $db;
		$sql = 'SELECT *
			FROM ' . USERS_TABLE . '
			WHERE username_clean = \'' . $db->sql_escape(utf8_clean_string($username)) . '\'';
		$result = $db->sql_query($sql);
		$user = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		return $member;
	}  
   	
}
