<?php

namespace thebigsmileXD\SpleefPE;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;
use pocketmine\Player;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class Wait extends PluginTask{

	public function __construct(Plugin $owner, $text, $lobby){
		parent::__construct($owner);
		$this->plugin = $owner;
		$this->text = $text;
		$this->lobby = $lobby;
	}

	public function onRun($currentTick){
		$this->getOwner()->waitTask($this->text, $this->lobby);
	}

	public function cancel(){
		$this->getHandler()->cancel();
	}
}
?>