<?php
namespace jasonwynn10\FakeAdmin;

use pocketmine\entity\Effect;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use specter\network\SpecterPlayer;
use specter\Specter;
use spoondetector\SpoonDetector;

class Main extends PluginBase implements Listener {
	/** @var PluginBase|false $authPlugin */
	private $authPlugin;
	/** @var string $loginPassword */
	private $loginPassword = "";
	/** @var Specter $specter */
	private $specter;
	public function onEnable() {
		$this->saveDefaultConfig();
		SpoonDetector::printSpoon($this,"spoon.txt");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->specter = $this->getServer()->getPluginManager()->getPlugin("Specter");
		$this->authPlugin = $this->getServer()->getPluginManager()->getPlugin($this->getConfig()->getNested("Admin properties.authentication.plugin","")) ?? false;
		if($this->authPlugin !== false)
			$this->loginPassword = $this->getConfig()->getNested("Admin properties.authentication.password","");

	}
	public function onJoin(PlayerJoinEvent $ev) {
		/** @var SpecterPlayer $player */
		if(($player = $ev->getPlayer()) instanceof SpecterPlayer) {
			if($this->getConfig()->getNested("Admin properties.invisible-to-non-op", false) == true) {
				$player->addEffect(Effect::getEffect(Effect::INVISIBILITY)->setVisible(false)->setDuration(999));
			}
		}
	}
}