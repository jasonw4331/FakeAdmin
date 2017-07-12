<?php
namespace jasonwynn10\FakeAdmin;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\network\mcpe\protocol\CommandStepPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\plugin\PluginBase;

use specter\Specter;

use spoondetector\SpoonDetector;

class Main extends PluginBase implements Listener {
	/** @var PluginBase|false $authPlugin */
	private $authPlugin;
	/** @var AdminEntity $specter */
	private $specter;
	/** @var Specter $specterPlugin */
	private $specterPlugin;
	/** @var string[] $translations */
	private $translations = [];
	public function onEnable() {
		$this->saveDefaultConfig();
		$this->saveResource("chat-scripts.json");
		SpoonDetector::printSpoon($this,"spoon.txt");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->authPlugin = $this->getServer()->getPluginManager()->getPlugin($this->getConfig()->getNested("Admin properties.authentication.plugin","")) ?? false;
		$this->specterPlugin = $this->getServer()->getPluginManager()->getPlugin("Specter");
		$this->translations["name"] = $this->getConfig()->getNested("Admin properties.Name","FakeAdmin");
		$this->translations["pass"] = $this->getConfig()->getNested("Admin properties.authentication.password","");
		$this->translations["sender"] = "CONSOLE";
		$this->specter = new AdminEntity(
			$this->getConfig()->getNested("Admin properties.Name","FakeAdmin"),
			$this->getConfig()->getNested("Admin properties.Ip","SPECTER"),
			(int)$this->getConfig()->getNested("Admin properties.Port",19133),
			$this->getConfig()->getNested("Admin properties.authentication.password","")
		);
	}

	/**
	 * @param string $str
	 *
	 * @return mixed|string
	 */
	private function translate(string $str) {
		$str = str_replace("{%name}", $this->translations["name"], $str);
		$str = str_replace("{%login}", $this->translations["pass"], $str);
		$str = str_replace("{%sender}", $this->translations["sender"], $str);
		return $str;
	}

	/**
	 * @priority MONITOR
	 * @ignoreCancelled false
	 *
	 * @param PlayerChatEvent $ev
	 */
	public function onChat(PlayerChatEvent $ev) {
		if($ev->isCancelled() or $ev->getPlayer()->getName() == $this->specter->getPlayer()->getName())
			return;
		$run = false;
		foreach($ev->getRecipients() as $recipient) {
			if($recipient->getName() === $this->translations["name"]) {
				$run = true;
			}
		}
		if(!$run)
			return;
		$message = $ev->getMessage();
		$this->translations["sender"] = $ev->getPlayer()->getName();
		/**
		 * @var string $key
		 * @var string|string[] $return
		 */
		foreach(json_decode(file_get_contents($this->getDataFolder()."chat-scripts.json")) as $received => $return) {
			if(similar_text(strtolower($message), strtolower($this->translate($received))) >= 70) {
				if(is_array($return)) {
					/** @var string[] $return */
					foreach($return as $ret) {
						if($this->specter->getPlayer() != null) {
							if(strpos($ret,"/") !== false) {
								$pk = new CommandStepPacket();
								$pk->command = substr($ret, 1);
								$pk->overload = "";
								$pk->uvarint1 = 0;
								$pk->currentStep = 0;
								$pk->done = true;
								$pk->clientId = $this->specter->getPlayer()->getClientId();
								$pk->inputJson = explode(" ", $ret);
								$pk->outputJson = [];
							}else{
								$pk = new TextPacket();
								$pk->type = TextPacket::TYPE_CHAT;
								$pk->source = $this->specter->getPlayer()->getName();
								$pk->message = $ret;
							}
							$this->specterPlugin->getInterface()->queueReply($pk, $this->specter->getPlayer());
						}
					}
				}else{
					/** @var string $return */
					if($this->specter->getPlayer() != null) {
						if(strpos($received,"/") !== false) {
							$pk = new CommandStepPacket();
							$pk->command = substr($return, 1);
							$pk->overload = "";
							$pk->uvarint1 = 0;
							$pk->currentStep = 0;
							$pk->done = true;
							$pk->clientId = $this->specter->getPlayer()->getClientId();
							$pk->inputJson = explode(" ", $return);
							$pk->outputJson = [];
						}else{
							$pk = new TextPacket();
							$pk->type = TextPacket::TYPE_CHAT;
							$pk->source = $this->specter->getPlayer()->getName();
							$pk->message = $return;
						}
						$this->specterPlugin->getInterface()->queueReply($pk, $this->specter->getPlayer());
					}
				}
			}
		}
	}
}