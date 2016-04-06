<?php

namespace thebigsmileXD\SpleefPE;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;
use pocketmine\Player;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\level\Position;

class Teleport extends PluginTask{

	public function __construct(Plugin $owner, Player $player, Position $position, $lobby){
		parent::__construct($owner);
		$this->player = $player;
		$this->position = $position;
		$this->lobby = $lobby;
	}

	public function onRun($currentTick){
		//$this->getOwner()->getLogger()->info("Teleport.php");
		$this->player->teleport($this->position, $this->getOwner()->level->getNested($this->lobby . ".yaw"), $this->getOwner()->level->getNested($this->lobby . ".pitch"));
	}

	public function cancel(){
		$this->getHandler()->cancel();
	}
}