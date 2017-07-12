<?php
namespace jasonwynn10\FakeAdmin;

use specter\api\DummyPlayer;

class AdminEntity extends DummyPlayer {
	/** @var string $password */
	protected $password;
    public function __construct($name, $address = null, $port = null, $password = ""){
        parent::__construct($name, $address, $port);
        $this->password = $password;
    }
}