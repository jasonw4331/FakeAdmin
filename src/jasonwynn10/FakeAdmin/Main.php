<?php
namespace jasonwynn10\FakeAdmin;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

use spoondetector\SpoonDetector;

class Main extends PluginBase implements Listener {
	/** @var PluginBase|false $authPlugin */
	private $authPlugin;
	/** @var string $loginPassword */
	private $loginPassword = "";
	/** @var AdminEntity $specter */
	private $specter;
	public function onEnable() {
		$this->saveDefaultConfig();
		SpoonDetector::printSpoon($this,"spoon.txt");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->authPlugin = $this->getServer()->getPluginManager()->getPlugin($this->getConfig()->getNested("Admin properties.authentication.plugin","")) ?? false;
		if($this->authPlugin !== false)
			$this->loginPassword = $this->getConfig()->getNested("Admin properties.authentication.password","");
		$this->specter = new AdminEntity($this->getConfig()->getNested("Admin properties.Name","FakeAdmin"), $this->getConfig()->getNested("Admin properties.Ip","SPECTER"), (int)$this->getConfig()->getNested("Admin properties.Port",19133));
	}
}