<?php
/**
* phpBB Extension - marttiphpbb communitycurrencies
* @copyright (c) 2015 - 2018 marttiphpbb <info@martti.be>
* @license GNU General Public License, version 2 (GPL-2.0)
*/

namespace marttiphpbb\communitycurrencies;

class ext extends \phpbb\extension\base
{
    /**
	 * phpBB 3.2.x and PHP 7+
	 */
	public function is_enableable()
	{
		$config = $this->container->get('config');
		return phpbb_version_compare($config['version'], '3.2', '>=') && version_compare(PHP_VERSION, '7', '>=');
	}
}
