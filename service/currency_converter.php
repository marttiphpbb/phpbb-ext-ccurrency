<?php

/**
* phpBB Extension - marttiphpbb Community Currencies
* @copyright (c) 2015 - 2020 marttiphpbb <info@martti.be>
* @license GNU General Public License, version 2 (GPL-2.0)
*/

namespace marttiphpbb\communitycurrencies\dataconverter;

use phpbb\config\db as config;

class currency_converter
{

    protected $config;
    protected $transform_func;
    protected $reverse_transform_func;
    protected $is_time_banking;

    /**
     * @param config $config
     */
    public function __construct(config $config)
    {
        $this->config = $config;
        if ($this->config['cc_currency_rate']
			&& is_int($config['cc_currency_rate'])
			&& $config['cc_currency_rate'] > 0)
		{
			$this->transform_func = 'custom_transform';
			$this->reverse_transform_func = 'custom_reverse_transform';
		}
		else
		{
			$this->transform_func = 'time_banking_transform';
			$this->reverse_transform_func = 'time_banking_reverse_transform';
		}
    }

    /**
     * Transforms internal amount (seconds) to local currency.
     *
     * @param  integer|null $seconds
     * @return integer|string
     */
    public function transform($seconds)
    {
		return $this->transform_func($seconds);
    }

    /**
     * Transforms amount to seconds.
     *
     * @param  integer|string $amount
     * @return integer|null
     */
    public function reverse_transform($amount)
    {
		return $this->reverse_transform_func($amount);
    }

    private function custom_transform($seconds)
    {
        if (null === $seconds) {
            return 0;
        }

        return round($seconds / $this->config['cc_currency_rate']);
	}

    private function custom_reverse_transform($amount)
    {
        if (!$amount) {
			$amount = 0;
        }
        return $amount * $this->config['cc_currency_rate'];
	}
}
