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
use pocketmine\entity\utils\PaintingMotive;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\level\particle\DestroyParticle;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddPaintingPacket;
use pocketmine\Player;

class Painting extends Entity{
    public const NETWORK_ID = self::PAINTING;

    /** @var float */
    protected $gravity = 0.0;
    /** @var float */
    protected $drag = 1.0;

    /** @var Vector3 */
    protected $blockIn;
    /** @var int */
    protected $direction = 0;
    /** @var string */
    protected $motive;
    /** @var int */
    protected $checkDestroyedTicker = 0;

    public function __construct(Level $level, CompoundTag $nbt){
        $this->motive = $nbt->getString("Motive");
        $this->blockIn = new Vector3($nbt->getInt("TileX"), $nbt->getInt("TileY"), $nbt->getInt("TileZ"));
        if($nbt->hasTag("Direction", ByteTag::class)){
            $this->direction = $nbt->getByte("Direction");
        }elseif($nbt->hasTag("Facing", ByteTag::class)){
            $this->direction = $nbt->getByte("Facing");
        }
        parent::__construct($level, $nbt);
    }

    protected function initEntity(){
        $this->setMaxHealth(1);
        $this->setHealth(1);
        parent::initEntity();
    }

    public function saveNBT(){
        parent::saveNBT();
        $this->namedtag->setInt("TileX", (int) $this->blockIn->x);
        $this->namedtag->setInt("TileY", (int) $this->blockIn->y);
        $this->namedtag->setInt("TileZ", (int) $this->blockIn->z);

        $this->namedtag->setByte("Facing", (int) $this->direction);
        $this->namedtag->setByte("Direction", (int) $this->direction); //Save both for full compatibility
    }

    public function entityBaseTick(int $tickDiff = 1) : bool{
        static $directions = [
            0 => Vector3::SIDE_SOUTH,
            1 => Vector3::SIDE_WEST,
            2 => Vector3::SIDE_NORTH,
            3 => Vector3::SIDE_EAST
        ];

        $hasUpdate = parent::entityBaseTick($tickDiff);

        if($this->checkDestroyedTicker++ > 10){
            /*
             * we don't have a way to only update on local block updates yet! since random chunk ticking always updates
             * all the things
             * ugly hack, but vanilla uses 100 ticks so on there it looks even worse
             */
            $this->checkDestroyedTicker = 0;
            $face = $directions[$this->direction];
            if(!self::canFit($this->level, $this->blockIn->getSide($face), $face, false, $this->getMotive())){
                $this->kill();
                $hasUpdate = true;
            }
        }

        return $hasUpdate; //doesn't need to be ticked always
    }

    public function kill(){
        parent::kill();

        $drops = true;

        if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
            $killer = $this->lastDamageCause->getDamager();
            if($killer instanceof Player and $killer->isCreative()){
                $drops = false;
            }
        }

        if($drops){
            //non-living entities don't have a way to create drops generically yet
            $this->level->dropItem($this, ItemFactory::get(Item::PAINTING));
        }
        $this->level->addParticle(new DestroyParticle($this->add(0.5, 0.5, 0.5), Item::PAINTING));
    }

    protected function recalculateBoundingBox() : void{
        static $directions = [
            0 => Vector3::SIDE_SOUTH,
            1 => Vector3::SIDE_WEST,
            2 => Vector3::SIDE_NORTH,
            3 => Vector3::SIDE_EAST
        ];

        $facing = $directions[$this->direction];

        $this->boundingBox->setBB(self::getPaintingBB($this->blockIn->getSide($facing), $facing, $this->getMotive()));
    }

    protected function tryChangeMovement(){
        $this->motionX = $this->motionY = $this->motionZ = 0;
    }

    protected function updateMovement(bool $teleport = false){

    }

    public function canBeCollidedWith() : bool{
        return false;
    }

    protected function sendSpawnPacket(Player $player) : void{
        $pk = new AddPaintingPacket();
        $pk->entityRuntimeId = $this->getId();
        $pk->x = $this->blockIn->x;
        $pk->y = $this->blockIn->y;
        $pk->z = $this->blockIn->z;
        $pk->direction = $this->direction;
        $pk->title = $this->motive;

        $player->dataPacket($pk);
    }

    /**
     * Returns the painting motive (which image is displayed on the painting)
     * @return PaintingMotive
     */
    public function getMotive() : PaintingMotive{
        return PaintingMotive::getMotiveByName($this->motive);
    }

    public function getDirection() : int{
        return $this->direction;
    }

    /**
     * Returns the bounding-box a painting with the specified motive would have at the given position and direction.
     *
     * @param Vector3        $blockIn
     * @param int            $facing
     * @param PaintingMotive $motive
     *
     * @return AxisAlignedBB
     */
    private static function getPaintingBB(Vector3 $blockIn, int $facing, PaintingMotive $motive) : AxisAlignedBB{
        $width = $motive->getWidth();
        $height = $motive->getHeight();

        $horizontalStart = (int) (ceil($width / 2) - 1);
        $verticalStart = (int) (ceil($height / 2) - 1);

        $thickness = 1 / 16;

        $minX = $maxX = 0;
        $minZ = $maxZ = 0;

        $minY = -$verticalStart;
        $maxY = $minY + $height;

        switch($facing){
            case Vector3::SIDE_NORTH:
                $minZ = 1 - $thickness;
                $maxZ = 1;
                $maxX = $horizontalStart + 1;
                $minX = $maxX - $width;
                break;
            case Vector3::SIDE_SOUTH:
                $minZ = 0;
                $maxZ = $thickness;
                $minX = -$horizontalStart;
                $maxX = $minX + $width;
                break;
            case Vector3::SIDE_WEST:
                $minX = 1 - $thickness;
                $maxX = 1;
                $minZ = -$horizontalStart;
                $maxZ = $minZ + $width;
                break;
            case Vector3::SIDE_EAST:
                $minX = 0;
                $maxX = $thickness;
                $maxZ = $horizontalStart + 1;
                $minZ = $maxZ - $width;
                break;
        }

        return new AxisAlignedBB(
            $blockIn->x + $minX,
            $blockIn->y + $minY,
            $blockIn->z + $minZ,
            $blockIn->x + $maxX,
            $blockIn->y + $maxY,
            $blockIn->z + $maxZ
        );
    }

    /**
     * Returns whether a painting with the specified motive can be placed at the given position.
     *
     * @param Level          $level
     * @param Vector3        $blockIn
     * @param int            $facing
     * @param bool           $checkOverlap
     * @param PaintingMotive $motive
     *
     * @return bool
     */
    public static function canFit(Level $level, Vector3 $blockIn, int $facing, bool $checkOverlap, PaintingMotive $motive) : bool{
        $width = $motive->getWidth();
        $height = $motive->getHeight();

        $horizontalStart = (int) (ceil($width / 2) - 1);
        $verticalStart = (int) (ceil($height / 2) - 1);

        switch($facing){
            case Vector3::SIDE_NORTH:
                $rotatedFace = Vector3::SIDE_WEST;
                break;
            case Vector3::SIDE_WEST:
                $rotatedFace = Vector3::SIDE_SOUTH;
                break;
            case Vector3::SIDE_SOUTH:
                $rotatedFace = Vector3::SIDE_EAST;
                break;
            case Vector3::SIDE_EAST:
                $rotatedFace = Vector3::SIDE_NORTH;
                break;
            default:
                return false;
        }

        $oppositeSide = Vector3::getOppositeSide($facing);

        $startPos = $blockIn->asVector3()->getSide(Vector3::getOppositeSide($rotatedFace), $horizontalStart)->getSide(Vector3::SIDE_DOWN, $verticalStart);

        for($w = 0; $w < $width; ++$w){
            for($h = 0; $h < $height; ++$h){
                $pos = $startPos->getSide($rotatedFace, $w)->getSide(Vector3::SIDE_UP, $h);

                $block = $level->getBlockAt($pos->x, $pos->y, $pos->z);
                if($block->isSolid() or !$block->getSide($oppositeSide)->isSolid()){
                    return false;
                }
            }
        }

        if($checkOverlap){
            $bb = self::getPaintingBB($blockIn, $facing, $motive);

            foreach($level->getNearbyEntities($bb) as $entity){
                if($entity instanceof self){
                    return false;
                }
            }
        }

        return true;
    }
}