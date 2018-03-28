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

namespace pocketmine\entity\object;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\AltayEntityEquipment;
use pocketmine\inventory\utils\EquipmentSlot;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\Player;

// TODO : Change to Living
class ArmorStand extends Entity{
	public const NETWORK_ID = EntityIds::ARMOR_STAND;

	public const TAG_ARMOR = "Armor";
	public const TAG_MAINHAND = "Mainhand";
	public const TAG_OFFHAND = "Offhand";
	public const TAG_POSE = "Pose";
	public const TAG_LAST_SIGNAL = "LastSignal";
	public const TAG_POSE_INDEX = "PoseIndex";

	/** @var AltayEntityEquipment */
	protected $equipment;

	public $width = 0.5;
	public $height = 1.975;

	protected $gravity = 0.04;

	public function __construct(Level $level, CompoundTag $nbt){
		$air = Item::get(Item::AIR)->nbtSerialize();
		if(!$nbt->hasTag(self::TAG_MAINHAND, ListTag::class)){
			$nbt->setTag(new ListTag(self::TAG_MAINHAND, [
				$air
			], NBT::TAG_Compound));
		}

		if(!$nbt->hasTag(self::TAG_OFFHAND, ListTag::class)){
			$nbt->setTag(new ListTag(self::TAG_OFFHAND, [
				$air
			], NBT::TAG_Compound));
		}

		if(!$nbt->hasTag(self::TAG_ARMOR, ListTag::class)){
			$nbt->setTag(new ListTag(self::TAG_ARMOR, [
				$air, // helmet
				$air, // chestplate
				$air, // legging
				$air  // boots
			], NBT::TAG_Compound));
		}

		if(!$nbt->hasTag(self::TAG_POSE, CompoundTag::class)){
			$nbt->setTag(new CompoundTag(self::TAG_POSE, [
				new IntTag(self::TAG_LAST_SIGNAL, 0),
				new IntTag(self::TAG_POSE_INDEX, 0)
			]));
		}

		parent::__construct($level, $nbt);
	}

	protected function initEntity(){
		$this->setMaxHealth(6);
		parent::initEntity();

		$this->equipment = new AltayEntityEquipment($this);

		/** @var ListTag $armor */
		$armor = $this->namedtag->getTag(self::TAG_ARMOR);
		/** @var ListTag $mainhand */
		$mainhand = $this->namedtag->getTag(self::TAG_MAINHAND);
		/** @var ListTag $offhand */
		$offhand = $this->namedtag->getTag(self::TAG_OFFHAND);

		$contents = array_merge(array_map(function(CompoundTag $tag) : Item{ return Item::nbtDeserialize($tag); }, $armor->getAllValues()), [Item::nbtDeserialize($offhand->offsetGet(0))], [Item::nbtDeserialize($mainhand->offsetGet(0))]);
		$this->equipment->setContents($contents);

		/** @var CompoundTag $pose */
		$pose = $this->namedtag->getTag(self::TAG_POSE);
		$pose = $pose->getInt(self::TAG_POSE_INDEX, 0);
		$this->setPose($pose);
	}

	public function onInteract(Player $player, Item $item, Vector3 $clickPos, int $slot) : void{
		if($player->isSneaking()){
			$pose = $this->getPose();
			if(++$pose >= 13){
				$pose = 0;
			}

			$this->setPose($pose);
		}else{
			$diff = $clickPos->getY() - $this->getY();
			$type = $this->getEquipmentSlot($item);
			$playerInv = $player->getInventory();

			switch(true){ // yes order matter here.
				case ($diff < 0.5):
					$clicked = EquipmentSlot::HACK_FEET;
					break;
				case ($diff < 1):
					$clicked = EquipmentSlot::HACK_LEGS;
					break;
				case ($diff < 1.5):
					$clicked = EquipmentSlot::HACK_CHEST;
					break;
				default: // armor stands are only 2-ish blocks tall :shrug:
					$clicked = EquipmentSlot::HACK_HEAD;
					break;
			}

			if($item->isNull()){
				if($clicked == EquipmentSlot::HACK_CHEST){
					if($this->equipment->getMainhandItem()->isNull()){
						$ASchestplate = clone $this->equipment->getChestplate();
						$this->equipment->setChestplate($item);
						$playerInv->setItemInHand(Item::get(Item::AIR));
						$playerInv->addItem($ASchestplate);
					}else{
						$ASiteminhand = clone $this->equipment->getMainhandItem();
						$this->equipment->setMainhandItem($item);
						$playerInv->setItemInHand(Item::get(Item::AIR));
						$playerInv->addItem($ASiteminhand);
					}
				}else{
					$old = clone $this->equipment->getItem($clicked);
					$this->equipment->setItem($clicked, $item);
					$playerInv->setItemInHand(Item::get(Item::AIR));
					$playerInv->addItem($old);
				}
			}else{
				if($type == EquipmentSlot::MAINHAND){
					if($this->equipment->getMainhandItem()->equals($item)){
						$playerInv->addItem(clone $this->equipment->getMainhandItem());
						$this->equipment->setMainhandItem(Item::get(Item::AIR));
					}else{
						$playerInv->addItem(clone $this->equipment->getMainhandItem());

						$ic = clone $item;
						$playerInv->setItemInHand($ic);
						$this->equipment->setMainhandItem($ic->pop());
					}
				}else{
					$old = clone $this->equipment->getItem($type);
					$this->equipment->setItem($type, $item);
					$playerInv->setItemInHand(Item::get(Item::AIR));
					$playerInv->addItem($old);
				}
			}

			$this->equipment->sendContents($this->getViewers());
		}
	}

	public function setPose(int $pose) : void{
		$this->propertyManager->setInt(self::DATA_ARMOR_STAND_POSE, $pose);
	}

	public function getPose() : int{
		return $this->propertyManager->getInt(self::DATA_ARMOR_STAND_POSE);
	}

	public function onUpdate(int $currentTick): bool{
		if(($hasUpdated = parent::onUpdate($currentTick))){
			if($this->isAffectedByGravity()){
				if($this->level->getBlock($this->getSide(Vector3::SIDE_DOWN)) === Item::AIR){
					$this->applyGravity();
					$this->level->broadcastLevelEvent($this, LevelEventPacket::EVENT_SOUND_ARMOR_STAND_FALL);
				}
			}
			return true;
		}

		return $hasUpdated;
	}

	public function saveNBT(){
		parent::saveNBT();

		$this->namedtag->setTag(new ListTag(self::TAG_MAINHAND, [$this->equipment->getMainhandItem()->nbtSerialize()], NBT::TAG_Compound));
		$this->namedtag->setTag(new ListTag(self::TAG_OFFHAND, [$this->equipment->getOffhandItem()->nbtSerialize()], NBT::TAG_Compound));

		$armorNBT = array_map(function(Item $item) : CompoundTag{ return $item->nbtSerialize(); }, $this->equipment->getArmorContents());
		$this->namedtag->setTag(new ListTag(self::TAG_ARMOR, $armorNBT, NBT::TAG_Compound));

		/** @var CompoundTag $poseTag */
		$poseTag = $this->namedtag->getTag(self::TAG_POSE);
		$poseTag->setInt(self::TAG_POSE_INDEX, $this->getPose());
		$this->namedtag->setTag($poseTag);
	}

	public function kill(){
		$dropVector = $this->add(0.5, 0.5, 0.5);
		$items = array_merge($this->equipment->getContents(false), [ItemFactory::get(Item::ARMOR_STAND)]);
		$this->level->dropItems($dropVector, $items);

		return parent::kill();
	}

	public function attack(EntityDamageEvent $source){
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player){
				if($damager->isCreative()){
					$this->level->broadcastLevelEvent($this, LevelEventPacket::EVENT_SOUND_ARMOR_STAND_BREAK);
					$this->level->broadcastLevelEvent($this, LevelEventPacket::EVENT_PARTICLE_DESTROY, 5);
					$this->flagForDespawn();
				}else{
					$this->level->broadcastLevelEvent($this, LevelEventPacket::EVENT_SOUND_ARMOR_STAND_HIT);
				}
			}
		}
		if($source->getCause() != EntityDamageEvent::CAUSE_CONTACT){ // cactus
			parent::attack($source);
		}
	}

	public function spawnTo(Player $player){
		parent::spawnTo($player);
		$this->equipment->sendContents($player);
	}

	public function getName(): string{
		return "Armor Stand";
	}

	public function getEquipmentSlot(Item $item){
		if($item instanceof Armor){
			return $item->getArmorSlot() + 2; // HACK :D
		}else{
			switch($item->getId()){
				case Item::SKULL:
				case Item::SKULL_BLOCK:
				case Item::PUMPKIN:
					return EquipmentSlot::HACK_HEAD;
			}
			return EquipmentSlot::MAINHAND;
		}
	}
}