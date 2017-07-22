<?php
namespace jasonwynn10\FakeAdmin;

use _64FF00\PurePerms\PurePerms;

use jasonwynn10\FakeAdmin\network\FAInterface;

use jasonwynn10\FakeAdmin\network\FakeAdmin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {
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