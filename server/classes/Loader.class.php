<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс-загрузчик
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/


if(!defined('EVENT_PHP_ERROR')) define('EVENT_PHP_ERROR',0xE00001);
if(!defined('EVENT_CONFIG_ERROR')) define('EVENT_CONFIG_ERROR',0xEC04F1);

class Loader{

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Ссылка на объект класса, паттерн Singleton
	private static $instance = null;


	#Массив модулей
	private $modules = array();








	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# паттерн Singleton, возврат указателя на объект
	#--------------------------------------------------
	public static function getInstance(){
		if(is_null(self::$instance)){
			$class = get_called_class();
			self::$instance = new $class();
		}
		return self::$instance;
	}#end function



	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	private function __construct(){

		/*
		# Инициализация констант - пути к директориям
		# /						-> DIR_ROOT			-> /var/www/home 
		# /server				-> DIR_SERVER		-> /var/www/home/server
		# /server/classes		-> DIR_CLASSES		-> /var/www/home/server/classes
		# /server/functions		-> DIR_FUNCTIONS	-> /var/www/home/server/functions
		# /server/logs			-> DIR_LOGS			-> /var/www/home/server/logs
		# /server/config		-> DIR_CONFIG		-> /var/www/home/server/config
		# /server/certs			-> DIR_CERTS		-> /var/www/home/server/certs
		# /client				-> DIR_CLIENT		-> /var/www/home/client
		*/

		#Массив путей к папакам внутри корневой папки
		$arr_paths = array(
			array('DIR_SERVER',		'/server'),				#Путь к корневой папке серверной части
			array('DIR_CLASSES',	'/server/classes'),		#Путь к папке с файлами классов
			array('DIR_FUNCTIONS',	'/server/functions'),	#Путь к папке с файлами функций
			array('DIR_LOGS',		'/server/logs'),		#Путь к папке с LOG файлами
			array('DIR_CONFIG',		'/server/config'),		#Путь к папке с файлами настроек
			array('DIR_CERTS',		'/server/certs'),		#Путь к папке с файлами сертификатов
			array('DIR_MODULES',	'/server/modules'),		#Путь к папке с файлами модулей
			array('DIR_CLIENT',		'/client'),				#Путь к папке с клиентскими файлами

			array('APP_START',		true)					#Признак, указывающий корректный запуск приложения
		);
		foreach($arr_paths as $item){
			//if(!defined($item[0])) define($item[0], realpath(DIR_ROOT.$item[1]));
			if(!defined($item[0])) define($item[0], DIR_ROOT.$item[1]);
		}



		#Инициализация функции автоматического подключения классов
		spl_autoload_register(array($this, 'autoloadClassHandler'));



		#Загрузка конфигураций модулей
		#Подключение файла /server/config/modules.config.php
		$this->modules = Config::getOptions('modules');


		$aload = array(
			'functions' => array(DIR_FUNCTIONS, '.functions.php'), #2. Подключение функций
			'classes' => array(DIR_CLASSES, '.class.php'), #3. Подключение классов
			'scripts' => array(DIR_MODULES, '') #4. Подключение скриптов
		);

		#Обработка модулей и подключение файлов
		foreach($this->modules as $name=>$module){

			#Если не массив - пропускаем
			if(!is_array($module)) continue;

			#Если в настройках модуля явно не установлено, что модуль активен - пропускаем
			if(!isset($module['active'])||$module['active']==false) continue;

			#1. Подключение конфигураций
			if(isset($module['config'])&&is_array($module['config'])){
				foreach($module['config'] as $incl) Config::getConfig($incl);
			}

			#2,3,4: Подключение функций, классов, скриптов
			foreach($aload as $k=>$v){
				if(isset($module[$k])&&is_array($module[$k])){
					foreach($module[$k] as $incl){
						$filename = $v[0].'/'.ltrim($incl, " .\r\n\t\\/").$v[1];
						if(is_file($filename)&&is_readable($filename)) include($filename);
					}
				}
			}

			#5: Вызов пользовательских функций
			if(isset($module['call'])&&is_array($module['call'])){
				foreach($module['call'] as $funct){
					if(empty($funct)) continue;
					if(!is_array($funct)){
						$fname = $funct;
						$fargs = array();
					}else{
						$fname = $funct[0];
						$fargs = (isset($funct[1]) ? $funct[1] : array());
					}
					call_user_func_array($fname, $fargs);
				}
			}

		}#Обработка модулей и подключение файлов


	}#end function



	#--------------------------------------------------
	# Клонирование
	#--------------------------------------------------
	private function __clone(){
		
	}#end function



	/*==============================================================================================
	Автоподключение
	==============================================================================================*/


	#--------------------------------------------------
	# Автоподключение классов
	#--------------------------------------------------
	public function autoloadClassHandler($class_name){
		$class_name = strtr($class_name,'_','/');
		$class_file = is_file(DIR_CLASSES.'/'.$class_name.'.class.php') ? DIR_CLASSES.'/'.$class_name.'.class.php' : DIR_CLASSES.'/Core/'.$class_name.'.class.php';
		require($class_file);
		return true;
	}#end function



}#end class


#Старт
if(!defined('DIR_ROOT')) die('Fatal error: DIR_ROOT not defined!');
Loader::getInstance();

?>