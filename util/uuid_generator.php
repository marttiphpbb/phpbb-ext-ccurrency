<?php
/**
* phpBB Extension - marttiphpbb Community Currencies
* @copyright (c) 2015 - 2020 marttiphpbb <info@martti.be>
* @license GNU General Public License, version 2 (GPL-2.0)
*/

namespace marttiphpbb\communitycurrencies\util;

// uuid v4
class uuid_generator
{
    private $openssl_available;

    public function __construct()
    {
        $this->openssl_available =
			((defined('PHP_WINDOWS_VERSION_BUILD')
			&& version_compare(PHP_VERSION, '5.3.4', '<'))
			|| !function_exists('openssl_random_pseudo_bytes'))
			? false : true;
    }

    public function generate()
    {
		if ($this->openssl_available)
		{
			$bytes = openssl_random_pseudo_bytes(16);

		}
		else
		{
			$bytes = '';
			while (strlen($bytes) < 32)
			{
				$bytes .= chr(mt_rand(0, 0xff));
			}
		}

		$bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
		$bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
