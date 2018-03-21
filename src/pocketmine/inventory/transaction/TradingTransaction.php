<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\inventory\transaction;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\Player;

class TradingTransaction extends InventoryTransaction{

	protected $inputs;
	protected $output;

	/** @var CraftingRecipe|null */
	protected $recipe = null;

	public function __construct(Player $source, $actions = []){
		$air = ItemFactory::get(Item::AIR, 0, 0);
		$this->inputs = [$air, $air];
		$this->output = $air;

		parent::__construct($source, $actions);
	}

	public function addInput(Item $item) : void{
		$this->inputs[] = clone $item;
	}

	public function getInputMap() : array{
		return $this->inputs;
	}

	public function setOutput(Item $item) : void{
		$this->output = clone $item;
	}

	public function getOutput() : ?Item{
		return $this->output;
	}

	public function canExecute() : bool{
		//TODO check trade recipe

		return parent::canExecute();
	}

	//protected function callExecuteEvent() : bool{
	//	$this->source->getServer()->getPluginManager()->callEvent($ev = new TradeEvent($this));
	//	return !$ev->isCancelled();
	//}
}
