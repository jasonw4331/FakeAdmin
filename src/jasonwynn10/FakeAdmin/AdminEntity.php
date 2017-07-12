<?php
namespace jasonwynn10\FakeAdmin;

use pocketmine\Server;
use specter\api\DummyPlayer;

class AdminEntity extends DummyPlayer {
    public function __construct($name, $address = null, $port = null, Server $server = null){
        parent::__construct($name, $address, $port, $server);
    }
}