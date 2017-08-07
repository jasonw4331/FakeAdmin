<?php
namespace jasonwynn10\FakeAdmin\network;

use jasonwynn10\FakeAdmin\Main;

use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\CommandStepPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\network\mcpe\protocol\SetHealthPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\SourceInterface;
use pocketmine\Player;
use pocketmine\utils\BinaryStream;
use pocketmine\utils\UUID;

class FAInterface implements SourceInterface {

	/** @var FakeAdmin[]|\SplObjectStorage $sessions */
	private $sessions;
	/** @var Main $plugin */
	private $plugin;
	/** @var array $ackStore */
	private $ackStore;
	/** @var array $replyStore */
	private $replyStore;

	public function __construct(Main $main) {
		$this->plugin = $main;
		$this->sessions = new \SplObjectStorage();
		$this->ackStore = [];
		$this->replyStore = [];
	}

	/**
	 * Sends a DataPacket to the interface, returns an unique identifier for the packet if $needACK is true
	 *
	 * @param Player $player
	 * @param DataPacket $packet
	 * @param bool $needACK
	 * @param bool $immediate
	 *
	 * @return int
	 */
	public function putPacket(Player $player, DataPacket $packet, bool $needACK = false, bool $immediate = true) {
		if($player instanceof FakeAdmin) {
			switch (get_class($packet)) {
				case ResourcePacksInfoPacket::class:
					$pk = new ResourcePackClientResponsePacket();
					$pk->status = ResourcePackClientResponsePacket::STATUS_COMPLETED;
					$pk->handle($player->getSessionAdapter());
					break;
				case TextPacket::class:
					/** @var TextPacket $packet */
					switch($packet->type) {
						case TextPacket::TYPE_RAW:
							/**
							 * @var string $key
							 * @var string|string[] $return
							 */
							foreach(json_decode(file_get_contents($this->plugin->getDataFolder()."chat-scripts.json")) as $received => $return) {
								if(similar_text(strtolower($packet->message), strtolower($received)) >= 70) {
									if(is_array($return)) {
										/** @var string[] $return */
										foreach($return as $ret) {
											$ret = $this->plugin->translate($ret);
											if(strpos($ret,"/") !== false and $this->plugin->getConfig()->getNested("Admin properties.reply to messages.command", true) === true) {
												$pk = new CommandStepPacket();
												$pk->command = substr($ret, 1);
												$pk->overload = "";
												$pk->uvarint1 = 0;
												$pk->currentStep = 0;
												$pk->done = true;
												$pk->clientId = $this->getPlayer()->getClientId();
												$pk->inputJson = explode(" ", $ret);
												$pk->outputJson = [];
												$this->replyStore[$player->getName()][] = $pk;
											}elseif($this->plugin->getConfig()->getNested("Admin properties.reply to messages.chat", true)){
												$pk = new TextPacket();
												$pk->type = TextPacket::TYPE_RAW;
												$pk->source = $this->getPlayer()->getName();
												$pk->message = $ret;
												$this->replyStore[$player->getName()][] = $pk;
											}
										}
									}else{
										/** @var string $return */
										$return = $this->plugin->translate($return, $packet->source);
										if($this->getPlayer() != null) {
											if(strpos($return,"/") !== false and $this->plugin->getConfig()->getNested("Admin properties.reply to messages.command", true) === true) {
												$pk = new CommandStepPacket();
												$pk->command = substr($return, 1);
												$pk->overload = "";
												$pk->uvarint1 = 0;
												$pk->currentStep = 0;
												$pk->done = true;
												$pk->clientId = $this->getPlayer()->getClientId();
												$pk->inputJson = explode(" ", $return);
												$pk->outputJson = [];
												$this->replyStore[$player->getName()][] = $pk;
											}elseif($this->plugin->getConfig()->getNested("Admin properties.reply to messages.chat", true)){
												$pk = new TextPacket();
												$pk->type = TextPacket::TYPE_RAW;
												$pk->source = $this->getPlayer()->getName();
												$pk->message = $return;
												$this->replyStore[$player->getName()][] = $pk;
											}
										}
									}
								}
							}
						break;
						case TextPacket::TYPE_CHAT:
							/**
							 * @var string $key
							 * @var string|string[] $return
							 */
							foreach(json_decode(file_get_contents($this->plugin->getDataFolder()."chat-scripts.json")) as $received => $return) {
								if(similar_text(strtolower($packet->message), strtolower($received)) >= 70) {
									if(is_array($return)) {
										/** @var string[] $return */
										foreach($return as $ret) {
											$ret = $this->plugin->translate($ret);
											if(strpos($ret,"/") !== false and $this->plugin->getConfig()->getNested("Admin properties.reply to messages.command", true) === true) {
												$pk = new CommandStepPacket();
												$pk->command = substr($ret, 1);
												$pk->overload = "";
												$pk->uvarint1 = 0;
												$pk->currentStep = 0;
												$pk->done = true;
												$pk->clientId = $this->getPlayer()->getClientId();
												$pk->inputJson = explode(" ", $ret);
												$pk->outputJson = [];
												$this->replyStore[$player->getName()][] = $pk;
											}elseif($this->plugin->getConfig()->getNested("Admin properties.reply to messages.chat", true)){
												$pk = new TextPacket();
												$pk->type = TextPacket::TYPE_CHAT;
												$pk->source = $this->getPlayer()->getName();
												$pk->message = $ret;
												$this->replyStore[$player->getName()][] = $pk;
											}
										}
									}else{
										/** @var string $return */
										$return = $this->plugin->translate($return, $packet->source);
										if($this->getPlayer() != null) {
											if(strpos($return,"/") !== false and $this->plugin->getConfig()->getNested("Admin properties.reply to messages.command", true) === true) {
												$pk = new CommandStepPacket();
												$pk->command = substr($return, 1);
												$pk->overload = "";
												$pk->uvarint1 = 0;
												$pk->currentStep = 0;
												$pk->done = true;
												$pk->clientId = $this->getPlayer()->getClientId();
												$pk->inputJson = explode(" ", $return);
												$pk->outputJson = [];
												$this->replyStore[$player->getName()][] = $pk;
											}elseif($this->plugin->getConfig()->getNested("Admin properties.reply to messages.chat", true)){
												$pk = new TextPacket();
												$pk->type = TextPacket::TYPE_CHAT;
												$pk->source = $this->getPlayer()->getName();
												$pk->message = $return;
												$this->replyStore[$player->getName()][] = $pk;
											}
										}
									}
								}
							}
						break;
						case TextPacket::TYPE_WHISPER:
							/**
							 * @var string $key
							 * @var string|string[] $return
							 */
							foreach(json_decode(file_get_contents($this->plugin->getDataFolder()."chat-scripts.json")) as $received => $return) {
								if(similar_text(strtolower($packet->message), strtolower($received)) >= 70) {
									if(is_array($return)) {
										/** @var string[] $return */
										foreach($return as $ret) {
											$ret = $this->plugin->translate($ret);
											if(strpos($ret,"/") !== false and $this->plugin->getConfig()->getNested("Admin properties.reply to messages.command", true) === true) {
												$pk = new CommandStepPacket();
												$pk->command = substr($ret, 1);
												$pk->overload = "";
												$pk->uvarint1 = 0;
												$pk->currentStep = 0;
												$pk->done = true;
												$pk->clientId = $this->getPlayer()->getClientId();
												$pk->inputJson = explode(" ", $ret);
												$pk->outputJson = [];
												$this->replyStore[$player->getName()][] = $pk;
											}elseif($this->plugin->getConfig()->getNested("Admin properties.reply to messages.chat", true)){
												$pk = new TextPacket();
												$pk->type = TextPacket::TYPE_WHISPER;
												$pk->source = $this->getPlayer()->getName();
												$pk->message = $ret;
												$this->replyStore[$player->getName()][] = $pk;
											}
										}
									}else{
										/** @var string $return */
										$return = $this->plugin->translate($return, $packet->source);
										if($this->getPlayer() != null) {
											if(strpos($return,"/") !== false and $this->plugin->getConfig()->getNested("Admin properties.reply to messages.command", true) === true) {
												$pk = new CommandStepPacket();
												$pk->command = substr($return, 1);
												$pk->overload = "";
												$pk->uvarint1 = 0;
												$pk->currentStep = 0;
												$pk->done = true;
												$pk->clientId = $this->getPlayer()->getClientId();
												$pk->inputJson = explode(" ", $return);
												$pk->outputJson = [];
												$this->replyStore[$player->getName()][] = $pk;
											}elseif($this->plugin->getConfig()->getNested("Admin properties.reply to messages.chat", true)){
												$pk = new TextPacket();
												$pk->type = TextPacket::TYPE_WHISPER;
												$pk->source = $this->getPlayer()->getName();
												$pk->message = $return;
												$this->replyStore[$player->getName()][] = $pk;
											}
										}
									}
								}
							}
						break;
					}
				break;
				case SetHealthPacket::class:
					/** @var SetHealthPacket $packet */
					if($packet->health <= 0){
						if($this->plugin->getConfig()->get("autoRespawn")){
							$pk = new RespawnPacket();
							$this->replyStore[$player->getName()][] = $pk;
						}
					}else{
						$player->spec_needRespawn = true;
					}
				break;
				case StartGamePacket::class:
					$pk = new RequestChunkRadiusPacket();
					$pk->radius = 8;
					$this->replyStore[$player->getName()][] = $pk;
					break;
				case PlayStatusPacket::class:
					/** @var PlayStatusPacket $packet */
					switch ($packet->status) {
						case PlayStatusPacket::PLAYER_SPAWN:
							/*$pk = new MovePlayerPacket();
							$pk->x = $player->getPosition()->x;
							$pk->y = $player->getPosition()->y;
							$pk->z = $player->getPosition()->z;
							$pk->yaw = $player->getYaw();
							$pk->pitch = $player->getPitch();
							$pk->bodyYaw = $player->getYaw();
							$pk->onGround = true;
							$pk->handle($player);*/
							//TODO
							break;
					}
					break;
				case MovePlayerPacket::class:
					/** @var MovePlayerPacket $packet */
					$eid = $packet->entityRuntimeId;
					if($eid === $player->getId() && $player->isAlive() && $player->spawned === true && $player->getForceMovement() !== null) {
						$packet->mode = MovePlayerPacket::MODE_NORMAL;
						$packet->yaw += 25; #TODO: revert to old method
						$this->replyStore[$player->getName()][] = $packet;
						//TODO
					}
					break;
				case BatchPacket::class:
					/** @var BatchPacket $packet */
					$packet->decode();

					foreach($packet->getPackets() as $buf) {
						$pk = PacketPool::getPacket($buf);

						if(!$pk->canBeBatched()) {
							throw new \InvalidArgumentException("Received invalid " . get_class($pk) . " inside BatchPacket");
						}

						$pk->setBuffer($buf, 1);
						$this->putPacket($player, $pk, false, $immediate);
					}
					break;
			}
			if($needACK) {
				$id = count($this->ackStore[$player->getName()]);
				$this->ackStore[$player->getName()][] = $id;
				return $id;
			}
		}
		return null;
	}

	/**
	 * Terminates the connection
	 *
	 * @param Player $player
	 * @param string $reason
	 *
	 */
	public function close(Player $player, string $reason = "unknown reason") {
		$this->sessions->detach($player);
		unset($this->ackStore[$player->getName()]);
		unset($this->replyStore[$player->getName()]);
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name) {
		// TODO: Implement setName() method.
	}

	/**
	 * @param $username
	 * @param string $address
	 * @param int $port
	 *
	 * @return bool
	 */
	public function openSession($username, $address = "127.0.0.1", $port = 58383) {
		if(!isset($this->replyStore[$username])) {
			$player = new FakeAdmin($this, null, $address, $port);
			$this->sessions->attach($player, $username);
			$this->ackStore[$username] = [];
			$this->replyStore[$username] = [];
			$this->plugin->getServer()->addPlayer($username, $player);

			$pk = new class() extends LoginPacket{
				public function decodeAdditional() {
				}
			};
			$pk->username = $username;
			$pk->gameEdition = 0;
			$pk->protocol = ProtocolInfo::CURRENT_PROTOCOL;
			$pk->clientUUID = UUID::fromData($address, $port, $username)->toString();
			$pk->clientId = 1;
			$pk->identityPublicKey = "key here";
			$pk->skin = str_repeat("\x80", 64 * 32 * 4); //TODO: Make this a real skin!
			$pk->skinId = "Standard_Alex"; //TODO make this settable with the skin

			$pk->handle($player->getSessionAdapter());

			return true;
		}
		else{
			return false;
		}
	}

	/**
	 * @return bool
	 */
	public function process() : bool {
		foreach($this->ackStore as $name => $acks) {
			$player = $this->plugin->getServer()->getPlayer($name);
			if($player instanceof FakeAdmin) {
				/** @noinspection PhpUnusedLocalVariableInspection */
				foreach($acks as $id) {

					//$player->handleACK($id); // TODO method removed. Though, plugin shouldn't have ACK to fill.
					$this->plugin->getLogger()->debug("Filled ACK.");
				}
			}
			$this->ackStore[$name] = [];
		}
		/**
		 * @var string $name
		 * @var DataPacket[] $packets
		 */
		foreach($this->replyStore as $name => $packets) {
			$player = $this->plugin->getServer()->getPlayer($name);
			if($player instanceof FakeAdmin) {
				foreach($packets as $pk) {
					$pk->handle($player->getSessionAdapter());
				}
			}
			$this->replyStore[$name] = [];
		}
		return true;
	}

	/**
	 * @param DataPacket $pk
	 * @param $player
	 */
	public function queueReply(DataPacket $pk, $player) {
		$this->replyStore[$player][] = $pk;
	}

	public function shutdown() {
		// TODO: Implement shutdown() method.
	}

	public function emergencyShutdown() {
		// TODO: Implement emergencyShutdown() method.
	}

	/**
	 * @return FakeAdmin|null
	 */
	private function getPlayer() {
		$p = $this->plugin->getServer()->getPlayer($this->plugin->getConfig()->getNested("Admin properties.Name","FakeAdmin"));
		return $p instanceof FakeAdmin ? $p : $p;
	}
}