<?php

namespace thebigsmileXD\SpleefPE;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\utils\Config;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\entity\Arrow;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\entity\Effect;

class Main extends PluginBase implements Listener{

	public function onLoad(){
		$this->getLogger()->info(TextFormat::GREEN . "Loading " . $this->getDescription()->getFullName());
	}

	public function onEnable(){
		$this->makeSaveFiles();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getLogger()->info(TextFormat::GREEN . "Enabling " . $this->getDescription()->getFullName() . " by " . $this->getDescription()->getAuthors()[0]);
		$this->getPlayerConfig(); // load player config
		$this->games = array();
		$this->getLevelConfig();
		foreach(array_keys($this->level->getAll()) as $levelname){
			if(!$this->getServer()->isLevelLoaded($levelname)){
				$this->getServer()->loadLevel($levelname);
			}
		}
	}

	private function makeSaveFiles(){
		@mkdir($this->getDataFolder());
		$this->saveDefaultConfig();
		if(!$this->getConfig()->get("player-defaults") || empty($this->getConfig()->get("player-defaults"))){
			$this->getConfig()->setNested("player-defaults", array("kills" => 0, "deaths" => 0, "wins" => 0, "played" => 0, "prefix" => ""));
		}
		if(!$this->getConfig()->get("lobby-defaults") || empty($this->getConfig()->get("lobby-defaults"))){
			$this->getConfig()->setNested("lobby-defaults", array("minplayers" => 2, "maxplayers" => 6));
		}
		if(!$this->getConfig()->get("reward-command") || empty($this->getConfig()->get("reward-command"))){
			$this->getConfig()->set("reward-command", "give {PLAYER} diamond 10");
		}
		$this->getConfig()->save();
		$this->saveResource("players.yml");
		$this->saveResource("lobbys.yml");
		$this->getLevelConfig();
		@mkdir($this->getDataFolder() . "/worlds");
		foreach(array_keys($this->level->getAll()) as $levelname){
			$this->copyr($this->getServer()->getDataPath() . "/worlds/" . $levelname, $this->getDataFolder() . "/worlds/" . $levelname);
		}
	}

	public function getPlayerConfig(){
		$this->players = new Config($this->getDataFolder() . "players.yml", Config::YAML);
	}

	public function getLevelConfig(){
		$this->level = new Config($this->getDataFolder() . "level.yml", Config::YAML);
	}

	public function setPlayerConfig(){
		$this->players->save();
		$this->players->reload();
	}

	public function setLevelConfig(){
		$this->level->save();
		$this->level->reload();
	}

	public function onDisable(){
		$this->getServer()->getLogger()->info(TextFormat::RED . "Disabling " . $this->getDescription()->getFullName());
		$this->getLevelConfig();
		foreach(array_keys($this->level->getAll()) as $levelname){
			// $this->deleteDir($this->getServer()->getDataPath() . "/worlds/" . $levelname);
			$this->copyr($this->getDataFolder() . "/worlds/" . $levelname, $this->getServer()->getDataPath() . "/worlds/" . $levelname);
		}
	}

	public function checkPlayer(Player $player){
		if(in_array($player->getName(), $this->players->getAll()["players"])){
			return true;
		}
		return false;
	}

	public function runIngame($sender){
		if($sender instanceof Player) return true;
		else{
			$sender->sendMessage(TextFormat::RED . "Please run this command ingame");
			return false;
		}
	}

	public function getLevelByName($level){
		foreach($this->getServer()->getLevels() as $olevel){
			if(strtolower($olevel->getName()) === strtolower($level)){
				return $olevel;
			}
		}
		// $this->getLogger()->info(TextFormat::RED . "No level called " . $level . " exists");
		return false;
	}

	public function copyr($source, $dest){
		// Check for symlinks
		if(is_link($source)){
			return symlink(readlink($source), $dest);
		}
		
		// Simple copy for a file
		if(is_file($source)){
			return copy($source, $dest);
		}
		
		// Make destination directory
		if(!is_dir($dest)){
			mkdir($dest);
		}
		
		// Loop through the folder
		$dir = dir($source);
		while(false !== $entry = $dir->read()){
			// Skip pointers
			if($entry == '.' || $entry == '..'){
				continue;
			}
			
			// Deep copy directories
			$this->copyr("$source/$entry", "$dest/$entry");
		}
		
		// Clean up
		$dir->close();
		return true;
	}

	public function deleteDir($dirPath){
		if(!is_dir($dirPath)){
			throw new InvalidArgumentException("$dirPath must be a directory");
		}
		if(substr($dirPath, strlen($dirPath) - 1, 1) != '/'){
			$dirPath .= '/';
		}
		$files = glob($dirPath . '*', GLOB_MARK);
		foreach($files as $file){
			if(is_dir($file)){
				$this->deleteDir($file);
			}
			else{
				unlink($file);
			}
		}
		rmdir($dirPath);
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "spleef":
				{
					$command = strtolower($command);
					if(count($args) > 0){
						switch($args[0]){
							case "join":
								{
									if($this->runIngame($sender)){
										if(isset($args[1])){
											if($this->joinLobby($sender, $args[1])){}
										}
										else{
											if($this->joinLobby($sender)){}
										}
										return true;
									}
									else
										return true;
								}
							case "leave":
								{
									if($this->runIngame($sender)){
										$this->leaveLobby($sender, $sender->getLevel());
										return true;
									}
									else
										return true;
								}
							case "addworld":
								{
									if(isset($args[1]) && $this->getLevelByName($args[1])){
										if(isset($args[2]) && is_numeric($args[2]) && isset($args[3]) && is_numeric($args[3])){
											$this->addWorld($sender, $this->getLevelByName($args[1]), $args[2], $args[3]);
											return true;
										}
										else{
											$this->addWorld($sender, $this->getLevelByName($args[1]));
											return true;
										}
									}
									elseif(isset($args[1])){
										$sender->sendMessage(TextFormat::RED . "The world " . TextFormat::AQUA . $args[1] . TextFormat::RED . " doesn't exist, check case\n" . TextFormat::AQUA . $args[1] . TextFormat::RED . " must be a valid ManyWorld-World");
										return true;
									}
									else{
										$sender->sendMessage(TextFormat::RED . "Invalid arguments");
										return false;
									}
								}
							case "removeworld":
								{
									if(isset($args[1]) && $this->getLevelByName($args[1])){
										$this->removeWorld($sender, $this->getLevelByName($args[1]));
										return true;
									}
									elseif(isset($args[1])){
										$sender->sendMessage(TextFormat::RED . "The world " . TextFormat::AQUA . $args[1] . TextFormat::RED . " doesn't exist\n" . TextFormat::AQUA . $args[1] . TextFormat::RED . " must be a valid ManyWorld-World");
										return true;
									}
									else{
										$sender->sendMessage(TextFormat::RED . "Invalid arguments");
										return false;
									}
								}
							case "spawn":
								{
									if($this->runIngame($sender)){
										if(isset($args[1])){
											switch($args[1]){
												case "add":
													{
														$this->addSpawn($sender);
														return true;
													}
												case "tp":
													{
														if(isset($args[2]) && is_numeric($args[2])){
															$this->tpSpawn($sender, strtolower($sender->getLevel()->getName()), intval($args[2]));
															return true;
														}
														else
															return false;
													}
												case "ls":
													{
														$this->listSpawns($sender);
														return true;
													}
												case "rm":
													{
														if(isset($args[2]) && is_numeric($args[2])){
															$this->removeSpawn($sender, intval($args[2]));
															return true;
														}
														else
															return false;
													}
											}
										}
										else
											return false;
									}
									else
										return true;
								}
							case "ls":
								{
									$this->listWorlds($sender);
									return true;
								}
							case "list":
								{
									$this->listWorlds($sender);
									return true;
								}
							case "reset":
								{
									if(isset($args[1])){
										$this->getPlayerConfig();
										if($this->playerExists($args[1])){
											$this->players->remove($args[0]);
											$this->setPlayerConfig();
											$sender->sendMessage(TextFormat::GREEN . "Player " . TextFormat::AQUA . $args[0] . TextFormat::GREEN . " successfully resetted");
											return true;
										}
										else{
											$sender->sendMessage(TextFormat::RED . "Player " . TextFormat::AQUA . $args[0] . TextFormat::RED . " doesn't exist.\n" . TextFormat::RED . "Check the name case");
											return true;
										}
									}
									else
										return false;
								}
							case "running":
								{
									if(isset($args[1]) && isset($this->games)){
										if(isset($this->games[strtolower($args[1])]) && $this->isRunning($this->getLevelByName($args[1]))){
											$sender->sendMessage(TextFormat::AQUA . $args[1] . TextFormat::GREEN . " is running");
											return true;
										}
										else{
											$sender->sendMessage(TextFormat::AQUA . $args[1] . TextFormat::RED . " is not running");
											return true;
										}
									}
									else
										return false;
								}
							case "starting":
								{
									if(isset($args[1]) && isset($this->games)){
										if(isset($this->games[strtolower($args[1])]) && $this->isStarting($this->getLevelByName($args[1]))){
											$sender->sendMessage(TextFormat::AQUA . $args[1] . TextFormat::GREEN . " is starting");
											return true;
										}
										else{
											$sender->sendMessage(TextFormat::AQUA . $args[1] . TextFormat::RED . " is not starting");
											return true;
										}
									}
									else
										return false;
								}
							case "stop":
								{
									if(isset($args[1]) && isset($this->games)){
										if(isset($this->games[strtolower($args[1])]) && ($this->isRunning($this->getLevelByName($args[1])) || $this->isStarting($this->getLevelByName($args[1])))){
											$this->stopGame($this->getLevelByName($args[1]), $sender);
											return true;
										}
										else{
											$sender->sendMessage(TextFormat::AQUA . $args[1] . TextFormat::RED . " is not running");
											return true;
										}
									}
									else
										return false;
								}
							default:
								return false;
						}
					}
					else
						return false;
				}
			default:
				return false;
		}
	}

	public function joinLobby(Player $player, $lobby = false){
		$this->getPlayerConfig();
		$this->getLevelConfig();
		if(empty($this->level->getAll())){
			$player->sendMessage(TextFormat::RED . "No worlds exist");
			return false;
		}
		if(!$this->playerExists($player)){
			$this->players->setNested($player->getName(), $this->getConfig()->getNested("player-defaults"));
			$this->setPlayerConfig();
		}
		if($this->isPlaying($player) || $this->isWaiting($player)){
			$player->sendMessage(TextFormat::RED . "You are already playing SpleefPE");
			return false;
		}
		if($lobby !== false){ // prüfen
			$lobby = strtolower($lobby);
			$this->getLevelConfig();
			$join = array_keys($this->level->getAll());
			if(in_array($lobby, $join)){
				if(!isset($this->games)){
					$this->games = array();
				}
				if(!isset($this->games[$lobby])){
					$this->games[$lobby] = array("players" => array(), "status" => "starting");
				}
				if(!isset($this->games[$lobby]["players"])){
					$this->games[$lobby]["players"] = array();
				}
				if(!isset($this->games[$lobby]["status"])){
					$this->games[$lobby]["status"] = "starting";
				}
				// if(isset($this->level->getAll()[$lobby])&&$this->games[$lobby]["running"]===false){
				if(!$this->isRunning($this->getLevelByName($lobby))){
					if($this->level->getNested($lobby . ".spawns") === false){
						$this->stopGame($this->getLevelByName($lobby), $player);
					}
					else{
						$maxplayers = count(array_keys($this->level->getAll()[$lobby]["spawns"]));
					}
					if(count($this->games[$lobby]["players"]) >= $maxplayers){
						$player->sendMessage(TextFormat::RED . "Lobby is full");
						return true;
					}
					// if($player->teleport(new Position($this->level->getNested($lobby . ".x"), $this->level->getNested($lobby . ".y"), $this->level->getNested($lobby . ".z"), $this->getLevelByName($lobby)), $this->level->getNested($lobby . ".yaw"), $this->level->getNested($lobby . ".pitch"))){
					if($this->copyr($this->getDataFolder() . "/worlds/" . $lobby, $this->getServer()->getDataPath() . "/worlds/" . $lobby)){
						$this->spawnPlayer($player, $lobby);
						// array_push($this->games[$lobby]["players"], $player->getName());
						$this->games[$lobby]["players"][$player->getName()] = array("kills" => 0, "deaths" => 0, "status" => "waiting");
						if($this->level->getNested($lobby . ".minplayers") === false){
							$minplayers = $this->getConfig()->getNested("lobby-defaults.minplayers");
						}
						else{
							$minplayers = $this->level->getNested($lobby . ".minplayers");
						}
						if(count($this->games[$lobby]["players"]) == 1){
							$this->getServer()->broadcastMessage(TextFormat::GREEN . "The player " . $player->getName() . " has started a SpleefPE match on lobby " . TextFormat::AQUA . $lobby . "\n" . TextFormat::GREEN . "Type " . TextFormat::AQUA . "/spleef join " . $lobby . TextFormat::GREEN . " to join!");
						}
						if(count($this->games[$lobby]["players"]) >= $minplayers){
							$this->initGame($lobby, $player);
							return true;
						}
						else{
							$player->sendMessage(TextFormat::AQUA . "Waiting for other players [" . count($this->games[$lobby]["players"]) . "|" . $maxplayers . "] (" . $minplayers . " players needed)");
							return true;
						}
					}
				}
				else{
					$player->sendMessage(TextFormat::RED . "This lobby is already running");
					return true;
				}
			}
			else{
				$player->sendMessage(TextFormat::RED . "No world found with that name");
				return true;
			}
		}
		else{
			$this->getLevelConfig();
			$join = array_keys($this->level->getAll());
			if(count($join) !== 0){
				$random = $join[0];
			}
			else
				$random = $join[mt_rand(0, count($join) - 1)];
			$player->sendMessage(TextFormat::GREEN . "Joining lobby " . TextFormat::AQUA . $random);
			$this->joinLobby($player, $random);
		}
		return true;
	}

	public function leaveLobby(Player $sender, Level $level){
		$this->getPlayerConfig();
		$lobby = strtolower($level->getName());
		if($this->isPlaying($sender) || $this->isWaiting($sender)){ // added starting
			$senders = array();
			foreach(array_keys($this->games[$lobby]["players"]) as $player){
				array_push($senders, $this->getServer()->getPlayer($player));
			}
			$this->games[$lobby]["players"][$sender->getName()] = null;
			unset($this->games[$lobby]["players"][$sender->getName()]);
			$sender->getServer()->broadcastMessage(TextFormat::RED . "[-]" . $sender->getDisplayName(), $senders);
			$sender->getInventory()->clearAll();
			$sender->setGamemode(0);
			if($sender->isOnline()) $sender->teleport($sender->getServer()->getDefaultLevel()->getSpawn());
			if($this->isRunning($level) || $this->isStarting($level)){
				// $this->getLogger()->info("is running");
				// $this->getLogger()->info(count(array_keys($this->games[$lobby]["players"])));
				if(count(array_keys($this->games[$lobby]["players"])) <= 1){
					// $this->getLogger()->info("want stop");
					// BUG?
					// if($this->isStarting($level)) $this->stopGame($level, $this->getServer()->getPlayer(array_keys($this->games[$lobby]["players"])[0]));
					// else
					$senders = array();
					foreach(array_keys($this->games[$lobby]["players"]) as $player){
						array_push($senders, $this->getServer()->getPlayer($player));
					}
					if($this->isStarting($level)) $this->getServer()->broadcastMessage(TextFormat::RED . "There are too less players to start.\n" . TextFormat::AQUA . "Waiting for players..", $senders);
					else $this->stopGame($level, $sender);
				}
			}
			$this->setPlayerConfig();
		}
		else{
			$sender->sendMessage(TextFormat::RED . "You are not playing SpleefPE");
			return false;
		}
	}

	public function spawnPlayer(Player $player, $lobby){
		$message = TextFormat::GREEN . "[+]" . $player->getDisplayName();
		$player->getInventory()->clearAll();
		$player->getInventory()->setHotbarSlotIndex(0, 0);
		$this->games[$lobby]["players"][$player->getName()]["status"] = "waiting";
		$player->getServer()->broadcastMessage($message, $player->getLevel()->getPlayers());
		$player->setGamemode(2);
		$this->tpSpawn($player, $lobby, count(array_keys($this->games[$lobby]["players"])) - 1);
		return true;
	}

	public function respawnPlayer(Player $player, $lobby){
		if($player->isOnline()){
			$this->games[$lobby]["players"][$player->getName()]["status"] = "playing";
			// $this->tpRandom($player, $lobby);
			$player->teleport($player->getLevel()->getSpawn());
			$this->getLogger()->info("trying to respawn " . $player->getName());
			$player->setGamemode(2);
			// $player->setHealth($player->getMaxHealth());
			$player->setHealth(20);
			$player->setFood(20);
			$player->getInventory()->clearAll();
			$player->getInventory()->addItem(Item::fromString("Iron Shovel"));
			$player->getInventory()->setHotbarSlotIndex(0, 0);
			return true;
		}
	}

	public function addSpawn(Player $player){
		$this->getLevelConfig();
		$location = $player->getLocation();
		$lobby = strtolower($player->getLevel()->getName());
		if($this->level->getNested($lobby . ".spawns") !== null){
			$spawns = $this->level->getNested($lobby . ".spawns");
			$count = count(array_keys($spawns));
		}
		else{
			$count = 0;
		}
		$this->level->setNested($lobby . ".spawns." . $count, array("x" => round($location->getFloorX(), 0), "y" => round($location->getFloorY(), 0), "z" => round($location->getFloorZ(), 0)));
		$this->setLevelConfig();
		$player->sendMessage(TextFormat::GREEN . "Spawn set");
		return true;
	}

	public function tpSpawn(Player $player, $lobby, $spawn = false){
		$this->getLevelConfig();
		$spawns = $this->level->getNested($lobby . ".spawns");
		if($this->level->getNested($lobby . ".spawns") === null || empty($this->level->getNested($lobby . ".spawns"))){
			if(count($spawns) === 0){
				$this->getServer()->getLogger()->info(TextFormat::RED . "No spawns set for lobby " . TextFormat::AQUA . $lobby);
				
				if($player->teleport(new Position($this->level->getNested($lobby . ".x"), $this->level->getNested($lobby . ".y"), $this->level->getNested($lobby . ".z"), $this->getLevelByName($lobby)), $this->level->getNested($lobby . ".yaw"), $this->level->getNested($lobby . ".pitch"))){
					// $this->getServer()->getLogger()->info("tp");
					return true;
				}
				else{
					
					$player->sendMessage(TextFormat::RED . "Teleportation failed, contact staff.\n" . TextFormat::RED . "Error: " . TextFormat::AQUA . "No spawn set for " . $lobby);
					$this->stopGame($this->getLevelByName($lobby), $player);
					return true;
				}
			}
		}
		elseif($spawn !== false){
			if($this->level->getNested($lobby . ".spawns." . $spawn)){
				if($player->teleport(new Position($this->level->getNested($lobby . ".spawns." . $spawn . ".x"), $this->level->getNested($lobby . ".spawns." . $spawn . ".y"), $this->level->getNested($lobby . ".spawns." . $spawn . ".z"), $this->getLevelByName($lobby)), 0, 0)){
					return true;
				}
				else{
					$player->sendMessage(TextFormat::RED . "Teleportation failed, contact staff.\n" . TextFormat::RED . "Error: " . TextFormat::AQUA . "No Lobby set for " . $lobby);
					return true;
				}
			}
			else{
				$player->sendMessage(TextFormat::RED . "This spawn does not exist");
				return true;
			}
		}
		else{
			$random = array_keys($spawns, mt_rand(0, count(array_keys($spawns))));
			$random = array_rand(array_keys($this->level->getNested($lobby . ".spawns")));
			$this->tpSpawn($player, strtolower($player->getLevel()->getName()));
		}
	}

	public function initGame($lobby, $sender){
		$lobby = strtolower($lobby);
		if(isset($this->games[$lobby])){
			if($this->isRunning($this->getLevelByName($lobby))){
				$sender->sendMessage(TextFormat::AQUA . $lobby . TextFormat::RED . " is already running");
				return true;
			}
			else{
				$players = array();
				foreach(array_keys($this->games[$lobby]["players"]) as $player){
					array_push($players, $this->getServer()->getPlayer($player));
				}
				if($this->isStarting($this->getLevelByName($lobby))){
					$this->games[$lobby]["status"] = "starting";
					for($number = 0; $number <= 30; $number++){
						if($number == 0) $this->getServer()->getScheduler()->scheduleDelayedTask(new Wait($this, "GO!", $lobby), (30 - $number) * 20);
						else $this->getServer()->getScheduler()->scheduleDelayedTask(new Wait($this, $number, $lobby), (30 - $number) * 20);
					}
					$this->scheduledStart = $this->getServer()->getScheduler()->scheduleDelayedTask(new Start($this, $lobby), 30 * 20)->getTaskId();
					return true;
				}
				// else
				// $this->getLogger()->info("not starting");
			}
		}
		else{
			return true;
		}
	}

	public function waitTask($text, $lobby){
		// $this->getLogger()->info("waiting");
		$players = array();
		if(isset($this->games) && isset($this->games[$lobby]) && isset($this->games[$lobby]["players"])){
			if($this->level->getNested($lobby . ".minplayers") === false){
				$minplayers = $this->getConfig()->getNested("lobby-defaults.minplayers");
			}
			else{
				$minplayers = $this->level->getNested($lobby . ".minplayers");
			}
			foreach(array_keys($this->games[$lobby]["players"]) as $player){
				array_push($players, $this->getServer()->getPlayer($player));
			}
			if(count(array_keys($this->games[$lobby]["players"])) >= $minplayers){
				if($this->isStarting($this->getLevelByName($lobby))){
					$this->getServer()->broadcastTip(TextFormat::LIGHT_PURPLE . "|:| " . TextFormat::AQUA . $text . TextFormat::RESET . TextFormat::LIGHT_PURPLE . " |:|", $players);
				}
				else{
					$this->getServer()->getScheduler()->cancelTasks($this);
				}
			}
			else{
				$this->getServer()->getScheduler()->cancelTasks($this);
				$this->getServer()->broadcastMessage(TextFormat::RED . "There are not enough players in the lobby");
				$this->games[$lobby]["status"] = "waiting";
			}
		}
		else{
			$this->getServer()->getScheduler()->cancelTasks($this);
		}
	}

	public function startGame($lobby){
		$players = array();
		foreach(array_keys($this->games[$lobby]["players"]) as $player){
			array_push($players, $this->getServer()->getPlayer($player));
		}
		$this->getServer()->broadcastMessage(TextFormat::GREEN . "Game started!\n" . TextFormat::GREEN . TextFormat::ITALIC . "Good luck!", $players);
		$this->games[strtolower($lobby)]["status"] = "running";
		$this->getPlayerConfig();
		foreach($players as $player){
			$this->respawnPlayer($player, $lobby);
			$nr = $this->players->getNested($player->getName() . ".played") + 1;
			$this->players->setNested($player->getName() . ".played", $nr);
		}
		$this->setPlayerConfig();
		return;
	}

	public function stopGame(Level $lobby, $player){
		$lobbyo = $lobby;
		$lobby = strtolower($lobby->getName());
		if(in_array($lobby, array_keys($this->games))){
			if(!$this->isRunning($lobbyo) && !$this->isStarting($lobbyo)){
				$player->sendMessage(TextFormat::RED . "The lobby " . TextFormat::AQUA . $lobby . TextFormat::RED . " is not running nor starting!");
			}
			else{
				if($player instanceof ConsoleCommandSender || $player instanceof Player){
					if($player instanceof Player && $this->isRunning($lobbyo)){
						// if($this->isPlaying($player)){
						$players = array_keys($this->games[$lobby]["players"]);
						unset($players[$player->getName()]);
						$winner = $players[0];
						$this->games[$lobby]["status"] = "stop";
						$winner = $this->getServer()->getPlayer($winner);
						$this->getServer()->broadcastMessage(TextFormat::AQUA . $winner->getDisplayName() . TextFormat::GREEN . " has won the SpleefPE match in " . TextFormat::AQUA . $lobby . TextFormat::GREEN . "!", $this->getServer()->getOnlinePlayers());
						$winner->sendMessage(TextFormat::GOLD . "Congratz!\n" . TextFormat::GREEN . "You won the SpleefPE match in " . TextFormat::AQUA . $lobby . TextFormat::GREEN . "!");
						$this->getPlayerConfig();
						$nr = $this->players->getNested($winner->getName() . ".wins") + 1;
						$this->players->setNested($winner->getName() . ".wins", $nr);
						$this->setPlayerConfig();
						if($this->getConfig()->get("reward-command")){
							$player->getServer()->dispatchCommand(new ConsoleCommandSender(), str_ireplace("{PLAYER}", $winner->getName(), $this->getConfig()->get("reward-command")));
						}
						foreach($players as $player){
							$this->leaveLobby($this->getServer()->getPlayer($player), $lobbyo);
						}
						// }
						// else
						// $this->getLogger()->info(TextFormat::RED . $player->getName() . " tried to stop an spleef game, invalid");
					}
					$this->games[$lobby]["status"] = "stop";
					$this->games[$lobby]["players"] = null;
					/*
					 * }
					 * else{
					 */
					// $this->getServer()->broadcastMessage(TextFormat::AQUA . $player . TextFormat::GREEN . " has stopped the SpleefPE game " . TextFormat::AQUA . $lobby . TextFormat::GREEN . "!", $this->getServer()->getOnlinePlayers());
					unset($this->games[$lobby]["players"]);
					// $this->games[$lobby] = null;
					unset($this->games[$lobby]);
					// reset world
					$this->resetLevel($lobbyo);
					return;
				}
				else{
					$this->getServer()->getPlayer($player)->sendMessage(TextFormat::RED . "You can't stop spleef games");
				}
			}
		}
		// $this->getLogger()->info("not in array");
		return;
	}

	public function inSameLobby($players){
		$temp = false;
		if(isset($this->games)){
			foreach(array_keys($this->games) as $game){
				foreach($players as $player){
					if(!array_key_exists($player->getName(), $this->games[$game]["players"])){
						return false;
					}
				}
			}
		}
		return true;
	}

	public function addWorld($sender, Level $level, $minplayers = false, $maxplayers = false){
		$this->getLevelConfig();
		if($level instanceof Level){
			if(in_array(strtolower($level->getName()), $this->level->getAll())){
				$sender->sendMessage(TextFormat::RED . "World " . TextFormat::AQUA . $level->getName() . TextFormat::RED . " already exist");
				return true;
			}
			else{
				$this->level->set(strtolower($level->getName()), array());
				$this->level->setNested(strtolower($level->getName()), array("minplayers" => $minplayers, "maxplayers" => $maxplayers));
				$this->setLevelConfig();
				$this->getLevelConfig();
				$this->copyr($this->getServer()->getDataPath() . "/worlds/" . $level->getName(), $this->getDataFolder() . "/worlds/" . $level->getName());
				if($this->level->getAll()[strtolower($level->getName())] !== null){
					$sender->sendMessage(TextFormat::GREEN . "World " . TextFormat::AQUA . $level->getName() . TextFormat::GREEN . " successfully added");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED . "Error while adding world " . TextFormat::AQUA . $level->getName() . "\n" . TextFormat::RED . "Error: Wasn't able to add world to config");
					return false;
				}
			}
		}
		else{
			$sender->sendMessage(TextFormat::RED . "World " . TextFormat::AQUA . $level->getName() . TextFormat::RED . " is not a level");
			return false;
		}
	}

	public function removeWorld($player, Level $level){
		$this->getLevelConfig();
		if(isset($this->level->getAll()[strtolower($level->getName())])){
			$this->level->remove(strtolower($level->getName()));
			$this->setLevelConfig();
			$player->sendMessage(TextFormat::GREEN . "World " . TextFormat::AQUA . $level->getName() . TextFormat::GREEN . " successfully removed");
			return true;
		}
		else{
			$player->sendMessage(TextFormat::RED . "World " . TextFormat::AQUA . $level->getName() . TextFormat::RED . " doesn't exist");
			return false;
		}
	}

	public function isPlaying(Player $player){
		if(isset($this->games) && isset($this->games[strtolower($player->getLevel()->getName())])){
			foreach(array_keys($this->games) as $game){
				if(in_array($player->getName(), array_keys($this->games[$game]["players"]))){
					if($this->games[strtolower($player->getLevel()->getName())]["players"][$player->getName()]["status"] === "playing") return true;
				}
			}
			// $this->getLogger()->info("Not playing");
		}
		else
			// $this->getLogger()->info(TextFormat::RED . "Games not set");
			return false;
	}

	public function isWaiting(Player $player){
		if(isset($this->games)){
			foreach(array_keys($this->games) as $game){
				if(array_keys($this->games[$game]["players"]) !== null && in_array($player->getName(), array_keys($this->games[$game]["players"]))){
					if($this->games[strtolower($player->getLevel()->getName())]["players"][$player->getName()]["status"] === "waiting") return true;
				}
			}
			// $this->getLogger()->info("Not waiting");
		}
		else
			// $this->getLogger()->info(TextFormat::RED . "Games not set");
			return false;
	}

	public function isStarting(Level $level){
		if(isset($this->games)){
			if(isset($this->games[strtolower($level->getName())]) && isset($this->games[strtolower($level->getName())]["status"]) && $this->games[strtolower($level->getName())]["status"] === "starting"){
				// $this->getLogger()->info(TextFormat::AQUA . $level->getName() . TextFormat::GREEN . " is starting");
				return true;
			}
			else{
				// $this->getLogger()->info(TextFormat::AQUA . $level->getName() . TextFormat::RED . " is not starting");
				return false;
			}
		}
		else
			return false;
	}

	public function isRunning(Level $level){
		if(isset($this->games)){
			if(isset($this->games[strtolower($level->getName())]) && isset($this->games[strtolower($level->getName())]["status"]) && $this->games[strtolower($level->getName())]["status"] === "running"){
				// $this->getLogger()->info(TextFormat::AQUA . $level->getName() . TextFormat::GREEN . " is running");
				return true;
			}
			else{
				// $this->getLogger()->info(TextFormat::AQUA . $level->getName() . TextFormat::RED . " is not running");
				return false;
			}
		}
		else
			return false;
	}

	public function listWorlds($sender){
		$this->getLevelConfig();
		if(count($this->level->getAll()) === 0){
			$message = TextFormat::RED . "No worlds set. Use " . TextFormat::AQUA . "/spleef addworld <name>" . TextFormat::RED . " to add one.";
		}
		else{
			$message = TextFormat::GREEN . "Following SpleefPE-worlds exist:\n" . TextFormat::AQUA;
			$message .= join(", ", array_keys($this->level->getAll()));
		}
		$sender->sendMessage($message);
		return true;
	}

	public function listSpawns(Player $sender){
		$this->getLevelConfig();
		$lobby = strtolower($sender->getLevel()->getName());
		if(isset($this->level->getAll()[$lobby]["spawns"]) && count($this->level->getAll()[$lobby]["spawns"]) === 0){
			$message = TextFormat::RED . "No spawns set. Use " . TextFormat::AQUA . "/spleef spawn add" . TextFormat::RED . " to add one.";
		}
		else{
			$message = TextFormat::GREEN . "Following spawns exist:\n" . TextFormat::AQUA;
			$message .= join(" ,", array_keys($this->level->getAll()[$lobby]["spawns"]));
		}
		$sender->sendMessage($message);
		return true;
	}

	public function removeSpawn(Player $sender, $index){
		$this->getLevelConfig();
		$lobby = strtolower($sender->getLevel()->getName());
		if(!isset($this->level->getAll()[$lobby]["spawns"][$index])){
			$message = TextFormat::RED . "Spawn " . TextFormat::AQUA . $index . TextFormat::RED . " does not exist";
		}
		else{
			$this->level->getAll()[$lobby]["spawns"][$index] = null;
			unset($this->level->getAll()[$lobby]["spawns"][$index]);
			$this->setLevelConfig();
			$message = TextFormat::GREEN . "Spawn " . TextFormat::AQUA . $index . TextFormat::GREEN . " removed";
		}
		$sender->sendMessage($message);
		return true;
	}

	public function playerExists(Player $player){
		$this->getPlayerConfig();
		if(isset($this->players->getAll()[$player->getName()])){
			return true;
		}
		return false;
	}

	public function onLeave(PlayerQuitEvent $event){
		if($this->isPlaying($event->getPlayer()) || $this->isWaiting($event->getPlayer())){
			$this->leaveLobby($event->getPlayer(), $event->getPlayer()->getLevel());
		}
	}

	public function onKick(PlayerKickEvent $event){
		if($this->isPlaying($event->getPlayer()) || $this->isWaiting($event->getPlayer())){
			$this->leaveLobby($event->getPlayer(), $event->getPlayer()->getLevel());
		}
	}

	public function onWorldChange(EntityLevelChangeEvent $event){
		if($event->getEntity() instanceof Player && isset($this->games[strtolower($event->getEntity()->getLevel()->getName())]["players"][$event->getEntity()->getName()])){
			$this->leaveLobby($event->getEntity(), $event->getEntity()->getLevel());
		}
	}

	public function damageHandler(EntityDamageEvent $event){
		$entity = $event->getEntity();
		$cause = $event->getCause();
		// $message = "Unknown";
		if($entity instanceof Player && $this->isPlaying($entity)){
			if($cause == EntityDamageEvent::CAUSE_ENTITY_ATTACK){
				// $this->getServer()->broadcastMessage("cause entity attack");
				if($event instanceof EntityDamageByEntityEvent){
					$killer = $event->getDamager();
					if($this->isPlaying($killer)){
						if($killer instanceof Player){
							// $message = $killer->getName();
							// if($event->getDamage() >= $entity->getHealth()){
							$event->setCancelled(true);
							// $this->killHandler($entity, $killer);
							// }
						}
					}
					// $this->getServer()->broadcastMessage($entity->getName() . " was hit by " . $message);
				}
				// else
				// $this->getLogger()->info("not playing, so not handling");
			}
			elseif($cause == EntityDamageEvent::CAUSE_VOID){
				// $this->getServer()->broadcastMessage("cause projectile");
				// if($event instanceof EntityDamageByChildEntityEvent){
				// $killer = $event->getDamager();
				/*
				 * if($killer instanceof Arrow){
				 * $this->getServer()->broadcastMessage("Is arrow");
				 * $killer = $killer->shootingEntity;
				 * }
				 */
				// if($killer instanceof Player && $this->isPlaying($killer)){
				// $this->getServer()->broadcastMessage($entity->getName() . " was hit by arrow shot by " . $killer->getName());
				$event->setCancelled(true);
				$this->killHandler($entity);
				// }
			}
			// else
			// $this->getLogger()->info("not playing or not player");
		}
		// elseif($this->isPlaying($entity) && $event->getDamage() >= $entity->getHealth() && $entity->getGamemode() === 0){
		// $this->getServer()->broadcastMessage("cause other");
		// $event->setCancelled(true);
		// $this->killHandler($entity);
		// }
		// else{
		// $this->getServer()->broadcastMessage("no critical cause or not gm0, maybe spectator in block?");
		// }
		// }
	}

	public function killHandler(Player $entity){
		// $this->getServer()->broadcastMessage("killHandler tries to handle");
		// player add deaths
		// $nr = $this->games[strtolower($entity->getLevel()->getName())]["players"][$entity->getName()]["deaths"] + 1;
		// $this->games[strtolower($entity->getLevel()->getName())]["players"][$entity->getName()]["deaths"] = $nr;
		// save to config.. WHY EVEN?! can't i do ranks just only by times played and wins?
		$this->getPlayerConfig();
		$this->players->getAll()[$entity->getName()]["deaths"]++;
		$this->setPlayerConfig();
		$entity->sendTip(TextFormat::RED . "You fell into void");
		$this->leaveLobby($entity, $entity->getLevel());
		if(isset($this->games[strtolower($entity->getLevel()->getName())])){
			if(count(array_keys($this->games[strtolower($entity->getLevel()->getName())]["players"])) <= 1){
				$this->stopGame($entity->getLevel(), $this->getServer()->getPlayer(array_keys($this->games[strtolower($entity->getLevel()->getName())]["players"])[0]));
				return;
			}
			$this->getServer()->broadcastTip(TextFormat::RED . $entity->getName() . " fell into void\n" . TextFormat::GREEN . count(array_keys($this->games[strtolower($entity->getLevel()->getName())]["players"])) . " players remaining");
		}
		$this->leaveLobby($entity, $entity->getLevel());
	}

	public function onBlockBreak(PlayerInteractEvent $event){
		if($event->getPlayer() instanceof Player && $this->isPlaying($event->getPlayer())){
			// effect if in config
			if($this->getConfig()->exists("blocks." . $event->getBlock()->getName()) && $this->getConfig()->exists("blocks." . $event->getBlock()->getName() . ".effect")){
				$effect = Effect::getEffectByName($this->getConfig()->getNested("blocks." . $event->getBlock()->getName() . ".effect"));
				if($effect instanceof Effect){
					$effect->setDuration(200);
					$effect->setVisible(false);
					$event->getPlayer()->addEffect($effect);
				}
			}
			$event->getPlayer()->getLevel()->setBlockIdAt($event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ(), 0);
		}
	}

	public function resetLevel(Level $level){
		$levelname = $level->getName();
		if($this->getServer()->isLevelLoaded($levelname)){
			$this->getServer()->unloadLevel($level, true);
			if($this->copyr($this->getDataFolder() . "/worlds/" . $levelname, $this->getServer()->getDataPath() . "/worlds/" . $levelname)){
				$this->getServer()->loadLevel($levelname);
			}
		}
	}
} 