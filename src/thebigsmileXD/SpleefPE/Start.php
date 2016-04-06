<?php

namespace thebigsmileXD\SpleefPE;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;
use pocketmine\Player;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class Start extends PluginTask{

	public function __construct(Plugin $owner, $lobby){
		parent::__construct($owner);
		$this->lobby = $lobby;
	}

	public function onRun($currentTick){
		//$this->getOwner()->getLogger()->info("start.php");
		$this->getOwner()->startGame($this->lobby);
	}

	public function cancel(){
		$this->getHandler()->cancel();
	}
}