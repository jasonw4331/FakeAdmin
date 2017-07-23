<?php
namespace jasonwynn10\FakeAdmin;

use _64FF00\PurePerms\PurePerms;

use jasonwynn10\FakeAdmin\network\FAInterface;

use jasonwynn10\FakeAdmin\network\FakeAdmin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

	const ACTION_DORMANT = 0;
	const ACTION_OBSERVE_PLAYER = 1;
	const ACTION_RAPID_SNEAK = 2;
	const ACTION_ATTACK_TARGET = 3;

	/** @var FAInterface $interface */
	private $interface;
	public function onEnable() {
		$this->saveDefaultConfig();
		$this->interface =  new FAInterface($this);
		$this->getServer()->getNetwork()->registerInterface($this->interface);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!$this->interface->openSession($this->getConfig()->getNested("Admin properties.Name","FakeAdmin"), $this->getConfig()->getNested("Admin properties.Ip","127.0.0.1"), (int)$this->getConfig()->getNested("Admin properties.Port",58383))) {
			$this->setEnabled(false);
			return;
		}
	}

	/**
	 * @param string $str
	 * @param string $sender
	 *
	 * @return mixed|string
	 */
	public function translate(string $str, string $sender = "") : string {
		$str = str_replace("{%name}", $this->getConfig()->getNested("Admin properties.Name","FakeAdmin"), $str);
		$str = str_replace("{%login}", $this->getConfig()->getNested("Admin properties.authentication.password",""), $str);
		$str = str_replace("{%sender}",$sender, $str);
		return $str;
	}


	public function doAI(int $action = self::ACTION_DORMANT, string $target = null) {
		/** @var FakeAdmin $fakeAdmin */
		$fakeAdmin = $this->getServer()->getPlayer($this->getConfig()->getNested("Admin properties.Name","FakeAdmin"));
		$targetPlayer = $this->getServer()->getPlayer($target ?? $fakeAdmin->target);
		if($fakeAdmin->closed || !$fakeAdmin->isAlive()) {
			return;
		}
		if(empty($targetPlayer) && $fakeAdmin->getCurrentAction() !== self::ACTION_DORMANT) {
			$fakeAdmin->setAction(self::ACTION_DORMANT);
		} elseif(!$targetPlayer->isOnline() && $fakeAdmin->getCurrentAction() !== self::ACTION_DORMANT) {
			$fakeAdmin->setAction(self::ACTION_DORMANT);
		}
		$fakeAdmin->directionFindTick++;
		switch($action) {
			case self::ACTION_DORMANT:
				break;
			case self::ACTION_OBSERVE_PLAYER:
				$fakeAdmin->generateNewDirection();
				$x = $fakeAdmin->xOffset;
				$y = ($fakeAdmin->isFlying() ? $fakeAdmin->yOffset : 0);
				$z = $fakeAdmin->zOffset;
				if($x * $x + $y * $y + $z * $z < 4) {
					$fakeAdmin->motionX = 0;
					$fakeAdmin->motionY = 0;
					$fakeAdmin->motionZ = 0;
				} else {
					$fakeAdmin->motionX = $x * $fakeAdmin->getSpeed();
					$fakeAdmin->motionY = 0;
					if($y !== $fakeAdmin->y) {
						$fakeAdmin->motionY = $y * $fakeAdmin->getSpeed();
					}
					$fakeAdmin->motionZ = $z * $fakeAdmin->getSpeed();
				}
				if(!$fakeAdmin->isFlying()) {
					$fakeAdmin->motionY -= $fakeAdmin->getGravity();
					if($fakeAdmin->isCollidedHorizontally && $fakeAdmin->isOnGround()) {
						$fakeAdmin->jump();
					}
				} else {
					if($fakeAdmin->isCollidedVertically) {
						$fakeAdmin->setFlying(false);
					}
				}
				$fakeAdmin->yaw = rad2deg(atan2(-$targetPlayer->x, $targetPlayer->z));
				$fakeAdmin->pitch = rad2deg(-atan2($targetPlayer->getEyeHeight(), sqrt($targetPlayer->x * $targetPlayer->x + $targetPlayer->z * $targetPlayer->z)));
				if($targetPlayer->distanceSquared(new Vector3($x, $y, $z)) > 25) {
					$fakeAdmin->directionFindTick = 120;
				}
				$fakeAdmin->move($fakeAdmin->motionX, $fakeAdmin->motionY, $fakeAdmin->motionZ);
				$fakeAdmin->checkWalkingArea();
				break;
			case self::ACTION_RAPID_SNEAK:
				if(mt_rand(0, 5) === 0) {
					$fakeAdmin->setSneaking(!$fakeAdmin->isSneaking());
				}
				$fakeAdmin->yaw = rad2deg(atan2(-$targetPlayer->x, $targetPlayer->z));
				$fakeAdmin->pitch = rad2deg(-atan2($targetPlayer->getEyeHeight(), sqrt($targetPlayer->x * $targetPlayer->x + $targetPlayer->z * $targetPlayer->z)));
				break;
			case self::ACTION_ATTACK_TARGET:
				if($fakeAdmin->isFlying()) {
					$fakeAdmin->setFlying(false);
				}
				$x = $targetPlayer->x - $fakeAdmin->x;
				$z = $targetPlayer->z - $fakeAdmin->z;
				if($x * $x + $z * $z < 4) {
					$fakeAdmin->motionX = 0;
					$fakeAdmin->motionY = 0;
					$fakeAdmin->motionZ = 0;
				} else {
					$fakeAdmin->motionX = $x * $fakeAdmin->getSpeed();
					$fakeAdmin->motionZ = $z * $fakeAdmin->getSpeed();
				}
				if($fakeAdmin->isCollidedHorizontally && $fakeAdmin->isOnGround()) {
					$fakeAdmin->jump();
				}
				$fakeAdmin->yaw = rad2deg(atan2(-$targetPlayer->x, $targetPlayer->z));
				$fakeAdmin->pitch = rad2deg(-atan2($targetPlayer->getEyeHeight(), sqrt($targetPlayer->x * $targetPlayer->x + $targetPlayer->z * $targetPlayer->z)));
				$fakeAdmin->hit($targetPlayer);
				break;
		}
	}

	/**
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 *
	 * @param PlayerPreLoginEvent $ev
	 */
	public function onPreLogin(PlayerPreLoginEvent $ev) {
		if($ev->getPlayer()->getName() === $this->getConfig()->getNested("Admin properties.Name","FakeAdmin")) {
			$ev->setCancelled(false);
			$ev->getPlayer()->setBanned(false);
			$ev->getPlayer()->setGamemode(FakeAdmin::CREATIVE);
			if($this->getConfig()->getNested("Admin properties.Fake Hacks", false) !== false) {
				$ev->getPlayer()->setAllowFlight(true);
				$ev->getPlayer()->setAllowInstaBreak(true);
				$ev->getPlayer()->setAllowMovementCheats(true);
				$ev->getPlayer()->setCanClimb(true);
				$ev->getPlayer()->setCanClimbWalls(true);
			}
		}
	}

	/**
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 *
	 * @param PlayerLoginEvent $ev
	 */
	public function onLogin(PlayerLoginEvent $ev) {
		if($ev->getPlayer()->getName() === $this->getConfig()->getNested("Admin properties.Name","FakeAdmin"))
			$ev->setCancelled(false);
	}

	/**
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 *
	 * @param PlayerJoinEvent $ev
	 */
	public function onJoin(PlayerJoinEvent $ev) {
		if($ev->getPlayer()->getName() !== $this->getConfig()->getNested("Admin properties.Name","FakeAdmin"))
			return;
		$ev->setCancelled(false);
		$ev->getPlayer()->setOp(true);
		/** @var PurePerms $pureperms */
		if(($pureperms = $this->getServer()->getPluginManager()->getPlugin("PurePerms")) != null) {
			$pureperms->getUserDataMgr()->setPermission($ev->getPlayer(),"*");
		}
	}
}