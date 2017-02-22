<?php

namespace Auth;

use Auth\EventListener;

use pocketmine\plugin\PluginBase;

use pocketmine\command\CommandExecutor;

use pocketmine\Player;

use pocketmine\utils\Config;
/* AUTH by SuperPuperSteve
** v0.1 - First release

** v0.2 TODOs - passwd, unregister

** v0.3 TODOs - info about player, console commands

** v0.4 TODOs - custom language, more configs, count failed auths

** v0.5 TODOs - caching, class for db
*/
class Auth extends PluginBase {
		
	public $db;
	public $authorized = array();
	
	/* При включении плагина - подключение к БД */
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->sendLog('§eИнициализация плагина...');
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		
		$this->saveDefaultConfig();
		$this->config = yaml_parse_file($this->getDataFolder() . 'config.yml');
		
		$this->getCommand("register")->setExecutor(new Commands\RegisterCommand($this));
		$this->getCommand("login")->setExecutor(new Commands\LoginCommand($this));
		
		$this->db = new \mysqli($this->config['ip'], $this->config['username'], $this->config['password']);
		
		if($this->db->connect_errno) {
			$this->sendLog("§cОшибка подключения к БД MySQL: {$this->db->connect_error}", 'error');
		} else {
			$this->sendLog('§aУспешное подключение к БД!','info');
			$this->db_init($this->db);
		}
		
	}
		
	public function onDisable(){
		$this->sendLog('§eОтключение от базы данных...', 'info');
		$this->db->close();
	}
	
	private function db_init($conn){
		$this->sendLog('§eИнициализация базы данных...','info');
		
		$info = $conn->query("CREATE DATABASE IF NOT EXISTS {$this->config['database']} ");
		$conn->select_db($this->config['database']);
		$conn->query("
					CREATE TABLE IF NOT EXISTS `{$this->config['table_prefix']}pass` (
						`nickname` varchar(16) NOT NULL,
						`firstlogin` bigint(20) NOT NULL,
						`lastlogin` bigint(20) NOT NULL,
						`password_hash` text NOT NULL,
						`ip` varchar(16) NOT NULL,
						`cid` text NOT NULL,
						PRIMARY KEY (`nickname`)
					);
		");
	}
	
	/* Получить подключение к базе данных */
	public function getDB(){
		return $this->db;
	}
	
	/* Костыль: удобное отправление сообщения в консоль */
	public function sendLog($message, string $type = 'info'){
		$this->getServer()->getLogger()->{$type}("[Auth] $message");
	}
	
	/* Авторизировать игрока - позволять выполнять действия на сервере и обновить last login */
	public function authorize(Player $player){
		$nick = strtolower($player->getName());
		
		$this->authorized[$nick] = true;
		$time = time();
		$ip = $player->getAddress();
		$cid = $player->getClientId();
		
		$this->db->query(
			"UPDATE `{$this->config['table_prefix']}pass`
			SET lastlogin=$time, ip='$ip', cid='$cid'
			WHERE nickname='$nick'"
			);
	}
	
	/* Деавторизация игрока */
	public function deauthorize(Player $player){
		unset($this->authorized[strtolower($player->getName())]);
	}
	
	/* Проверка на авторизованность ника */
	public function isAuthorized(Player $player){
		return isset($this->authorized[strtolower($player->getName())]);
	}
}