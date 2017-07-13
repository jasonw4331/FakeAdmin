<?php
namespace jasonwynn10\FakeAdmin\Entity;

use jasonwynn10\FakeAdmin\Main;
use pocketmine\entity\Human;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginException;

class FakeAdminHuman extends Human {

	const ACTION_DORMANT = 0;
	const ACTION_OBSERVE_PLAYER = 1;
	const ACTION_RAPID_SNEAK = 2;

	/*
	 * TODO: Skins.
	 * I'm not the best with these.
	 */

	/** @var int */
	private $action = self::ACTION_DORMANT;
	/** @var null|Player */
	private $observedPlayer = null;

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

	public function __construct(Level $level, CompoundTag $nbt) {
		parent::__construct($level, $nbt);
		$this->spawnToAll();
		$this->putName();
		$this->setDormant();
		$this->setDataProperty(self::DATA_FLAGS, self::DATA_FLAG_NO_AI, true);
	}

	public function putName() {
		$this->setNameTag($this->getPlugin()->getConfig()->getNested("Admin properties.Name","FakeAdmin"));
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
		if($this->observedPlayer === null && !$this->isDormant()) {
			$this->setDormant();
		} elseif(!$this->observedPlayer->isOnline() && !$this->isDormant()) {
			$this->setDormant();
		}
		$this->directionFindTick++;
		switch($this->getAction()) {
			case self::ACTION_DORMANT:
				break;

			case self::ACTION_OBSERVE_PLAYER:
				$this->generateNewDirection();
				$x = $this->x + $this->xOffset;
				$y = ($this->isFlying() ? $this->y + $this->yOffset : 0);
				$z = $this->z + $this->zOffset;
				if($x * $x + $y + $y + $z + $z < 4) {
					$this->motionX = 0;
					$this->motionY = 0;
					$this->motionZ = 0;
				} else {
					$this->motionX = $x * $this->getSpeed();
					$this->motionY = $y * $this->getSpeed();
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
				$player = $this->observedPlayer;
				$this->yaw = rad2deg(atan2(-$player->x, $player->z));
				$this->pitch = rad2deg(-atan2($player->getEyeHeight(), sqrt($player->x * $player->x + $player->z * $player->z)));

				$this->move($this->motionX, $this->motionY, $this->motionZ);
				break;

			case self::ACTION_RAPID_SNEAK:
				if(!$this->observedPlayer->isOnline()) {
					$this->setDormant();
					break;
				}
				if(mt_rand(0, 5) === 0) {
					$this->setSneaking(!$this->isSneaking());
				}
				$player = $this->observedPlayer;
				$this->yaw = rad2deg(atan2(-$player->x, $player->z));
				$this->pitch = rad2deg(-atan2($player->getEyeHeight(), sqrt($player->x * $player->x + $player->z * $player->z)));
				break;
		}
		$this->updateMovement();
		parent::onUpdate($currentTick);
		return $this->isAlive();
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
		$this->observedPlayer = null;
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
		$this->observedPlayer = $player;
		$this->action = self::ACTION_OBSERVE_PLAYER;
		return $return;
	}

	/**
	 * Returns whether the fake admin is currently observing a player.
	 *
	 * @return bool
	 */
	public function isObserving(): bool {
		return $this->observedPlayer !== null && $this->action === self::ACTION_OBSERVE_PLAYER;
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