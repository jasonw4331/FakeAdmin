<?php
namespace jasonwynn10\FakeAdmin;

use _64FF00\PurePerms\PurePerms;

use jasonwynn10\FakeAdmin\Entity\FakeAdminHuman;
use Leet\LeetAuth\LeetAuth;

use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\CommandStepPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\plugin\PluginBase;

use specter\network\SpecterPlayer;
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
		Entity::registerEntity("FakeAdmin", true);
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
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 *
	 * @param PlayerPreLoginEvent $ev
	 */
	public function onPreLogin(PlayerPreLoginEvent $ev) {
		if($ev->getPlayer()->getName() === $this->specter->getPlayer()->getName()) {
			$ev->setCancelled(false);
			$ev->getPlayer()->setBanned(false);
			$ev->getPlayer()->setGamemode(SpecterPlayer::CREATIVE);
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
		if($ev->getPlayer()->getName() === $this->specter->getPlayer()->getName())
			$ev->setCancelled(false);
	}

	/**
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 *
	 * @param PlayerJoinEvent $ev
	 */
	public function onJoin(PlayerJoinEvent $ev) {
		if($ev->getPlayer()->getName() !== $this->specter->getPlayer()->getName())
			return;
		$ev->setCancelled(false);
		$ev->getPlayer()->setOp(true);
		/** @var PurePerms $pureperms */
		if(($pureperms = $this->getServer()->getPluginManager()->getPlugin("PurePerms")) != null) {
			$pureperms->getUserDataMgr()->setPermission($ev->getPlayer(),"*");
		}
	}

	/**
	 * @priority HIGHEST
	 * @ignoreCancelled false
	 *
	 * @param PlayerKickEvent $ev
	 */
	public function onKick(PlayerKickEvent $ev) {
		if($ev->isCancelled() or $ev->getPlayer()->getName() !== $this->specter->getPlayer()->getName())
			return;
		$bool = false;
		# AuthMePE
		if($ev->getReason() === "§cDue to security issue, \n§cthis account has been blocked temporary.  \n§cPlease try again later.\n\n\n\n§6-AuthMePE Authentication System") {
			$bool = true;
		}
		if(similar_text(strtolower("\n§cMax amount of tries reached!\n§eTry again §d0 §eminutes later."),strtolower($ev->getReason())) >= 70) {
			$bool= true;
		}
		# HereAuth
		if($ev->getReason() === "Login from the same device") {
			$bool = true;
		}
		if($ev->getReason() === "You created too many accounts!") {
			$bool = true;
		}
		if(similar_text(strtolower($ev->getReason()), strtolower("Player of the same name from another device is already online")) >= 50) {
			$bool = true;
		}
		# LeetAuth
		if($this->getServer()->getPluginManager()->getPlugin("LeetAuth") != null) {
			$leetAuth = LeetAuth::getPlugin();
			if($ev->getReason() === $leetAuth->getMessageHandler()->userLoggingIn) {
				$bool = true;
			}
		}
		# PocketMine
		if($ev->getReason() === "Flying is not enabled on this server") {
			$bool = true;
		}
		# SimpleAuth
		if($ev->getReason() === "already logged in") {
			$bool = true;
		}
		$ev->setCancelled($bool);
	}

	/**
	 * @priority MONITOR
	 * @ignoreCancelled false
	 *
	 * @param PlayerChatEvent $ev
	 */
	public function onChat(PlayerChatEvent $ev) {
		if($ev->isCancelled() or $ev->getPlayer()->getName() == $this->specter->getPlayer()->getName() or $this->getConfig()->getNested("", false) !== false)
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
		$this->translations["name"] = $this->specter->getPlayer()->getName();
		/**
		 * @var string $key
		 * @var string|string[] $return
		 */
		foreach(json_decode(file_get_contents($this->getDataFolder()."chat-scripts.json")) as $received => $return) {
			if(similar_text(strtolower($message), strtolower($received)) >= 70) {
				if(is_array($return)) {
					/** @var string[] $return */
					foreach($return as $ret) {
						$ret = $this->translate($ret);
						if($this->specter->getPlayer() != null) {
							if(strpos($ret,"/") !== false and $this->getConfig()->getNested("Admin properties.reply to messages.command", true) === true) {
								$pk = new CommandStepPacket();
								$pk->command = substr($ret, 1);
								$pk->overload = "";
								$pk->uvarint1 = 0;
								$pk->currentStep = 0;
								$pk->done = true;
								$pk->clientId = $this->specter->getPlayer()->getClientId();
								$pk->inputJson = explode(" ", $ret);
								$pk->outputJson = [];
								$this->specterPlugin->getInterface()->queueReply($pk, $this->specter->getPlayer());
							}elseif($this->getConfig()->getNested("Admin properties.reply to messages.chat", true)){
								$pk = new TextPacket();
								$pk->type = TextPacket::TYPE_CHAT;
								$pk->source = $this->specter->getPlayer()->getName();
								$pk->message = $ret;
								$this->specterPlugin->getInterface()->queueReply($pk, $this->specter->getPlayer());
							}
						}
					}
				}else{
					/** @var string $return */
					$return = $this->translate($return);
					if($this->specter->getPlayer() != null) {
						if(strpos($return,"/") !== false and $this->getConfig()->getNested("Admin properties.reply to messages.command", true) === true) {
							$pk = new CommandStepPacket();
							$pk->command = substr($return, 1);
							$pk->overload = "";
							$pk->uvarint1 = 0;
							$pk->currentStep = 0;
							$pk->done = true;
							$pk->clientId = $this->specter->getPlayer()->getClientId();
							$pk->inputJson = explode(" ", $return);
							$pk->outputJson = [];
							$this->specterPlugin->getInterface()->queueReply($pk, $this->specter->getPlayer());
						}elseif($this->getConfig()->getNested("Admin properties.reply to messages.chat", true)){
							$pk = new TextPacket();
							$pk->type = TextPacket::TYPE_CHAT;
							$pk->source = $this->specter->getPlayer()->getName();
							$pk->message = $return;
							$this->specterPlugin->getInterface()->queueReply($pk, $this->specter->getPlayer());
						}
					}
				}
			}
		}
	}

	/** Using specter is quite a hassle and does not seem really necessary to me. Any ideas on it @jasonwynn10? */

	/**
	 * @param Location $location
	 *
	 * @return FakeAdminHuman
	 */
	public function createFakeAdmin(Location $location): FakeAdminHuman {
		$nbt = new CompoundTag("", [
			"Pos" => new ListTag("Pos", [
				new DoubleTag("", $location->x),
				new DoubleTag("", $location->y),
				new DoubleTag("", $location->z)
			]),
			"Motion" => new ListTag("Motion", [
				new DoubleTag("", 0),
				new DoubleTag("", 0),
				new DoubleTag("", 0)
			]),
			"Rotation" => new ListTag("Rotation", [
				new FloatTag("", $location->yaw),
				new FloatTag("", $location->pitch)
			])
		]);
		$entity = Entity::createEntity("FakeAdmin", $location->getLevel(), $nbt);
		if($entity instanceof FakeAdminHuman) {
			return $entity;
		}
		return null;
	}
}