<?php

namespace App;

use App\Card;

class Manacost {

	public $manacost;
	public $manacost_sorted;
	public $cmc;
	public $hybridless_cmc;

	private static $variable_costs = ['{X}','{Y}','{Z}'];

	public static function createFromCard(Card $card) : Manacost {

		$instance = new self();

		$instance->manacost = $card->manacost;
		$instance->manacost_sorted = $card->manacost_sorted;
		$instance->cmc = $card->cmc;
		$instance->hybridless_cmc = $card->hybridless_cmc;

		return $instance;
	}

	public static function createFromManacostString($manacost, $override_cmc = null) : Manacost {

		$instance = new self();

		$instance->manacost = $manacost;
		$instance->manacost_sorted = $instance->calculateColoredManaCosts();

		// Cmc exception for lands. Scryfall presents lands as cmc 0 (not null), but manacost as "" (not "{0}")
		// So we should follow this behaviour and override cmc with given value.
		// This value is used for hybridless_cmc as default in calculateHybridlessCmcFromCost()
		$instance->cmc = ($override_cmc !== null) ? $override_cmc : $instance->calculateCmcFromCost();
		$instance->hybridless_cmc = $instance->calculateHybridlessCmcFromCost();

		return $instance;
	}

	/*
		Should only be used to generate/populate manacost_sorted field,
		in other cases use manacost_sorted attribute
	*/
	public function calculateColoredManaCosts() 
	{
		$costs = [];

		// Use strtok() to find first word. Multifaced cards have manacosts like: {R} // {U}
		// Match all non-digit symbols
		if (preg_match_all('/(\{[^\d\}]+\})/u', strtok($this->manacost, " "), $symbols)) {
			foreach ($symbols[1] as $symbol) {

				$cost = 1;
				// Handle "half mana" from Unhinged set
				if ($symbol[1] === 'H') {
					$cost = 0.5;
					$symbol = '{' . mb_substr($symbol, 2);
				}

				if (!isset($costs[$symbol]))
					$costs[$symbol] = $cost;
				else
					$costs[$symbol] += $cost;
			}
		}

		return $costs;
	}

	public function addColorless($coloress_amount)
	{
		$this->cmc += $coloress_amount;
		$this->hybridless_cmc += $coloress_amount;

		return $this;
	}

	public function calculateCmcFromCost()
	{
		$cmc = null;
		
		// Only match digit symbol at the start of mana cost. Multifaced cards could have manacosts like: {R} // {1}
		if (preg_match('/^\{(\d+)\}/u', $this->manacost, $matches))
			$cmc = $matches[1];

		$mana_counts = ($this->manacost_sorted !== null) ? $this->manacost_sorted : $this->calculateColoredManaCosts();

		if (empty($mana_counts))
			return $cmc;

		if ($cmc === null)
			$cmc = 0;

		// X, Y and Z don't count towards cmc
		foreach (self::$variable_costs as $symbol) {
			unset($mana_counts[$symbol]);
		}

		foreach ($mana_counts as $symbol => $amount) {
			if (mb_strpos($symbol, '/2') === false)
				$cmc += $amount;
			else
				$cmc += ($amount * 2);
		}
		return $cmc;
	}

	public function calculateHybridlessCmcFromCost()
	{
		if ($this->cmc === null)
			return null;

		$cmc = $this->cmc;
		foreach ($this->manacost_sorted as $symbol => $amount) {
			if (mb_strpos($symbol, 'P') !== false || mb_strpos($symbol, '2') !== false)

				// Both phyrexian and 2/color hybrids should cost the lesser amount 
				// 0 for phyrexian and 1 for 2/color, but 2/color is already calculated as 2 cmc per symbol, so -amount gives the correct amount in both cases
				$cmc -= $amount;	
		}
		return $cmc;
	}

	public function costsMoreThan(Manacost $other, $may_cost_more_of_same = false)
	{
		if (($other->cmc === null || 
			($this->cmc <= $other->cmc && $this->hybridless_cmc <= $other->hybridless_cmc)) && 
			!$this->costsMoreColoredThan($other, $may_cost_more_of_same))
			return false;

		return true;
	}


	public function costsMoreColoredThan(Manacost $other, $may_cost_more_of_same = false)
	{
		$mana = $this->manacost_sorted;
		$mana_left = $other->manacost_sorted;

		// Strip variable costs
		foreach (self::$variable_costs as $variable_cost) {
			unset($mana_left[$variable_cost]);
			unset($mana[$variable_cost]);
		}

		$cmc = $other->cmc ? $other->cmc : 0;
		$anytype_left = $cmc - (array_sum(array_values($mana_left)));
		
		// If this costs nothing colored, it can't cost more
		if (empty($mana))
			return false;

		// If the other costs nothing colored, then this must cost more
		if (empty($mana_left))
			return true;

		foreach ($mana as $symbol => $cost) {

			if (!isset($mana_left[$symbol])) {

				// Is it hybrid mana?
				$pos = mb_strpos($symbol, '/');
				if ($pos === false || $pos < 1)
					return true;

				// Translate Phyrexian mana to the base color
				// Phyrexian red > red
				if ($symbol[$pos + 1] === 'P')
					$symbol = '{'.$symbol[$pos-1].'}';

				// Translate multicolor / 2-generic/color to one of the left colors if any
				// multicolor / 2-generic/color > base color
				else {
					
					$symbol2 = '{'.$symbol[$pos-1].'}';
					$symbol = '{'.$symbol[$pos+1].'}';

					if (!isset($mana_left[$symbol]) || (
						($may_cost_more_of_same && $cost > ($anytype_left + $mana_left[$symbol])) || 
						(!$may_cost_more_of_same && $cost > $mana_left[$symbol])
					))
						$symbol = $symbol2;
				}

				if (!isset($mana_left[$symbol]))
					return true;

			}

			// Reduce usable mana for next iteration, unless we alredy ran out
			if ($cost > $mana_left[$symbol]) {
				if (!$may_cost_more_of_same || $cost > ($anytype_left + $mana_left[$symbol]))
					return true;
				else {
					$anytype_left -= ($cost - $mana_left[$symbol]);
					$mana_left[$symbol] = 0;
				}
			}
			else
				$mana_left[$symbol] -= $cost;
		}

		return false;
	}
}