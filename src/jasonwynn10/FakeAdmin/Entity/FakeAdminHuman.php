<?php
namespace jasonwynn10\FakeAdmin\Entity;

use jasonwynn10\FakeAdmin\Main;
use pocketmine\block\Solid;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\Player;
use pocketmine\plugin\PluginException;

class FakeAdminHuman extends Human {

	const ACTION_DORMANT = 0;
	const ACTION_OBSERVE_PLAYER = 1;
	const ACTION_RAPID_SNEAK = 2;
	const ACTION_ATTACK_TARGET = 3;

	/*
	 * TODO: Skins.
	 * I'm not the best with these.
	 */

	/** @var int */
	private $action = self::ACTION_DORMANT;
	/** @var null|Player */
	private $targetPlayer = null;

	/** @var float */
	private $xOffset = 0.0;
	/** @var float */
	private $yOffset = 0.0;
	/** @var float */
	private $zOffset = 0.0;
	/** @var bool */
	private $flying = true;
	/** @var int */
	private $directionFindTick = 0;
	/** @var int */
	private $attackDelay = 6;

	public function __construct(Level $level, CompoundTag $nbt) {
		parent::__construct($level, $nbt);
		$this->spawnToAll();
		$this->putName();
		$this->setDormant();
		$this->setDataProperty(self::DATA_FLAG_NO_AI, self::DATA_TYPE_BYTE, 1);
		$this->setMaxHealth(20);
		$this->setHealth(20);
	}

	public function putName() {
		$this->setNameTag($this->getPlugin()->getConfig()->getNested("Admin properties.Name", "FakeAdmin"));
		$this->setNameTagVisible();
		$this->setNameTagAlwaysVisible();
	}

	public function getSpeed(): float {
		return ($this->isSprinting() ? 0.13 : 0.1);
	}

	/**
	 * @return bool
	 */
	public function generateNewDirection(): bool {
		if($this->directionFindTick < 120) {
			return false;
		}
		$this->setSprinting((bool) mt_rand(0, 1));
		$this->flying = (bool) mt_rand(0, 1);
		$i = mt_rand(0, 1) === 1 ? 1 : -1;
		$this->xOffset = lcg_value() * 7 * $i;
		if($this->flying) {
			$this->yOffset = lcg_value() * 3 * $i;
		}
		$this->zOffset = lcg_value() * 7 * $i;
		$this->directionFindTick = mt_rand(0, 40);
		return true;
	}

	/**
	 * @return bool
	 */
	public function isFlying(): bool {
		return $this->flying;
	}

	/**
	 * @return Main
	 */
	public function getPlugin(): Main {
		$level = $this->getLevel();
		if($level === null) {
			throw new InvalidEntityException("Trying to get the level of a closed or dead entity.");
		}
		$plugin = $level->getServer()->getPluginManager()->getPlugin("FakeAdmin");
		if(!($plugin instanceof Main)) {
			throw new PluginException("Existing Fake Admin entity without FakeAdmin available.");
		}
		return $plugin;
	}

	/**
	 * @param $currentTick
	 *
	 * @return bool
	 */
	public function onUpdate($currentTick): bool {
		if($this->closed || !$this->isAlive()) {
			return false;
		}
		if($this->targetPlayer === null && !$this->isDormant()) {
			$this->setDormant();
		} elseif(!$this->targetPlayer->isOnline() && !$this->isDormant()) {
			$this->setDormant();
		}
		$this->directionFindTick++;
		switch($this->getAction()) {
			case self::ACTION_DORMANT:
				break;

			case self::ACTION_OBSERVE_PLAYER:
				$this->generateNewDirection();
				$x = $this->xOffset;
				$y = ($this->isFlying() ? $this->yOffset : 0);
				$z = $this->zOffset;
				if($x * $x + $y * $y + $z * $z < 4) {
					$this->motionX = 0;
					$this->motionY = 0;
					$this->motionZ = 0;
				} else {
					$this->motionX = $x * $this->getSpeed();
					$this->motionY = 0;
					if($y !== $this->y) {
						$this->motionY = $y * $this->getSpeed();
					}
					$this->motionZ = $z * $this->getSpeed();
				}
				if(!$this->isFlying()) {
					$this->motionY -= $this->gravity;
					if($this->isCollidedHorizontally && $this->isOnGround()) {
						$this->jump();
					}
				} else {
					if($this->isCollidedVertically) {
						$this->flying = false;
					}
				}
				$player = $this->targetPlayer;
				$this->yaw = rad2deg(atan2(-$player->x, $player->z));
				$this->pitch = rad2deg(-atan2($player->getEyeHeight(), sqrt($player->x * $player->x + $player->z * $player->z)));

				if($player->distanceSquared(new Vector3($x, $y, $z)) > 25) {
					$this->directionFindTick = 120;
				}
				$this->move($this->motionX, $this->motionY, $this->motionZ);
				$this->checkWalkingArea();
				break;

			case self::ACTION_RAPID_SNEAK:
				if(mt_rand(0, 5) === 0) {
					$this->setSneaking(!$this->isSneaking());
				}
				$player = $this->targetPlayer;
				$this->yaw = rad2deg(atan2(-$player->x, $player->z));
				$this->pitch = rad2deg(-atan2($player->getEyeHeight(), sqrt($player->x * $player->x + $player->z * $player->z)));
				break;

			case self::ACTION_ATTACK_TARGET:
				if($this->flying) {
					$this->flying = false;
				}
				$player = $this->targetPlayer;
				$x = $player->x - $this->x;
				$z = $player->z - $this->z;
				if($x * $x + $z * $z < 4) {
					$this->motionX = 0;
					$this->motionY = 0;
					$this->motionZ = 0;
				} else {
					$this->motionX = $x * $this->getSpeed();
					$this->motionZ = $z * $this->getSpeed();
				}
				if($this->isCollidedHorizontally && $this->isOnGround()) {
					$this->jump();
				}

				$this->yaw = rad2deg(atan2(-$player->x, $player->z));
				$this->pitch = rad2deg(-atan2($player->getEyeHeight(), sqrt($player->x * $player->x + $player->z * $player->z)));

				$this->hit($player);
				break;
		}
		$this->updateMovement();
		parent::onUpdate($currentTick);
		return $this->isAlive();
}

	/**
	 * @return bool
	 */
    public function checkWalkingArea(): bool {
		if($this->distance($block = $this->getTargetBlock(2)) <= 1.5) {
			if($block instanceof Solid) {
				if((int) $block->y === (int) $this->getEyeHeight()) {
					$this->directionFindTick = 120;
					return true;
				}
			}
		}
		return false;
    }

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
    public function hit(Player $player): bool {
	    if(!$this->distance($player) <= 2.5) { // TODO: Find actual reach length of a player.
			return false;
	    }
    	if($this->attackDelay < 6) {
    		$this->attackDelay++;
    		return false;
	    }
    	$player->attack(7, new EntityDamageByEntityEvent($this, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 7));
    	$pk = new AnimatePacket();
    	$pk->action = 1;
    	$pk->entityRuntimeId = $this->getId();
    	foreach($this->getLevel()->getPlayers() as $p) {
    		$p->dataPacket($pk);
	    }
	    return true;
    }

	/**
	 * @param Player $player
	 */
    public function spawnTo(Player $player) {
	    if(!isset($this->hasSpawned[$player->getLoaderId()])) {
		    $this->hasSpawned[$player->getLoaderId()] = $player;
		    $pk = new AddPlayerPacket();
		    $pk->uuid = $this->getUniqueId();
		    $pk->username = "";
		    $pk->entityRuntimeId = $this->getId();
		    $pk->x = $this->x;
		    $pk->y = $this->y;
		    $pk->z = $this->z;
		    $pk->speedX = $pk->speedY = $pk->speedZ = 0.0;
		    $pk->yaw = $this->yaw;
		    $pk->pitch = $this->pitch;
		    $pk->item = $this->getInventory()->getItemInHand();
		    $pk->metadata = $this->dataProperties;
		    $pk->metadata[self::DATA_NAMETAG] = [self::DATA_TYPE_STRING, $this->namedtag->name];
		    $player->dataPacket($pk);
		    $this->inventory->sendArmorContents($player);
	    }
    }

	/**
	 * @return int
	 */
	public function getAction(): int {
		return $this->action;
	}

	/**
	 * Sets the fake admin into a sleeping mode, disallowing it to do anything. Returns false if already dormant.
	 *
	 * @return bool
	 */
	public function setDormant(): bool {
		if($this->isDormant()) {
			return false;
		}
		$this->action = self::ACTION_DORMANT;
		$this->targetPlayer = null;
		return true;
	}

	/**
	 * @return bool
	 */
	public function isDormant(): bool {
		return $this->action === self::ACTION_DORMANT;
	}

	/**
	 * Starts observing the given player. Returns true if the fake admin is already observing a player, false otherwise.
	 *
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function invoke(Player $player): bool {
		$return = false;
		if($this->isObserving()) {
			$return = true;
		}
		$this->targetPlayer = $player;
		$this->action = self::ACTION_OBSERVE_PLAYER;
		return $return;
	}

	/**
	 * Returns whether the fake admin is currently observing a player.
	 *
	 * @return bool
	 */
	public function isObserving(): bool {
		return $this->targetPlayer !== null && $this->action === self::ACTION_OBSERVE_PLAYER;
	}

	/**
	 * @return bool
	 */
	public function startRapidSneaking(): bool {
		if($this->isRapidSneaking()) {
			return false;
		}
		$this->action = self::ACTION_RAPID_SNEAK;
		return true;
	}

	/**
	 * @return bool
	 */
	public function isRapidSneaking(): bool {
		return $this->action === self::ACTION_RAPID_SNEAK;
	}
}