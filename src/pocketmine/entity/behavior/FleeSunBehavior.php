<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\entity\behavior;

use pocketmine\entity\Entity;
use pocketmine\block\Block;
use pocketmine\block\Grass;
use pocketmine\entity\Mob;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\entity\Animal;

class FleeSunBehavior extends Behavior{

    /** @var float */
	protected $speedMultiplier = 1.0;
	/** @var Path */
	protected $currentPath = null;
	
	public function __construct(Mob $mob, float $speedMultiplier = 1.0){
		parent::__construct($mob);
		
		$this->speedMultiplier = $speedMultiplier;
	}
	
	public function canStart() : bool{
		$chunk = $this->mob->chunk;
		if($this->mob->isOnFire() and $chunk->getHighestBlockAt($this->mob->x, $this->mob->z) < $this->mob->y){
			$pos = $this->findPossibleShelter($this->mob);
			if($pos === null) return false;
			
			$path = Path::findPath($this->mob, $pos, $this->mob->distance($pos) + 2);
			
			$this->currentPath = $path;
			
			return $path->havePath();
		}

		return false;
	}
	
	public function canContinue() : bool{
		return $this->currentPath !== null and $this->currentPath->havePath();
	}
	
	public function onTick(int $tick) : void{
		if($this->currentPath->havePath()){
			if($next = $this->currentPath->getNextTile($this->mob)){
				$this->mob->lookAt(new Vector3($next->x + 0.5, $this->mob->y, $next->y + 0.5));
				$this->mob->moveForward($this->speedMultiplier);
			}
		}
	}

	public function onEnd() : void{
		$this->mob->motionX = 0; $this->mob->motionZ = 0;
		$this->currentPath = null;
	}

	/**
	 * @param Entity $entity
	 * @param int $dxz
	 * @param int $dy
	 * @return null|Block
	 */
	public function findPossibleShelter(Entity $entity) : ?Block{
		$chunk = $entity->chunk;
		for($i = 0; $i < 10; $i++){
			$block = $this->mob->level->getBlock($this->mob->add($this->random->nextBoundedInt(20) - 10, $this->random->nextBoundedInt(6) - 3, $this->random->nextBoundedInt(20) - 10));
			$canSeeSky = $chunk->getHighestBlockAt($block->x, $block->z) < $block->y;
			if(!$canSeeSky and $this->calculateBlockWeight($entity, $block, $block->getSide(0)) < 0){
				return $block;
			}
		}
		
		return null;
	}
	
	public function calculateBlockWeight(Entity $entity, Block $block, Block $blockDown) : int{
		$vec = [$block->getX(), $block->getY(), $block->getZ()];
		if($entity instanceof Animal){
			if($blockDown instanceof Grass) return 20;
			
			return (int) (max($entity->level->getBlockLightAt(...$vec), $entity->level->getBlockSkyLightAt(...$vec)) - 0.5);
		}else{
			return (int) 0.5 - max($entity->level->getBlockLightAt(...$vec), $entity->level->getBlockSkyLightAt(...$vec));
		}
	}
}