<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс работы с сессиями
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/





class Session{

	use Core_Trait_SingletonUnique;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/


	private $options = array(

		#Системные
		'session_name'		=>'CCSESSID',			#Наименование переменной для идентификатора сессии в Cookie клиента
		'autostart'			=> false,				#Признак автоматического запуска сессии
		'prefixes'			=> array(),				#Массив префиксов, которые автоматически обрабатываются методами __get и __set
													#имеет вид: array('user_','client_'); 
													#Пример: для префикса 'user_', запрос Session->user_id будет возвращаться $_SESSION[$this->session_name]['user_id'];
		#NULL
		'null'=>null
	);


	public static $session_expire	= 3600;					#Время действия сессии в секундах
	private $session_name 			= null;					#Внутреннее имя сессии, для обращения к $_SESSION[$this->session_name][*]*
	private $session_id				= null;					#Идентификатор текущей сессии из  session_id()
	private $prefixes				= array();				#Массив поддерживаемых префиксов
															#Внутренний массив поддерживаемых префиксов, имеет вид:
															#array( 'user_'=>5 );
	private $session_ip				= null;					#IP адрес клиента, для которого запущена сессия
	private $session_ip_real		= null;					#IP адрес клиента, для которого запущена сессия, указанный в HTTP_FORVARDED_FOR


	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		#Системные ошибки, от 1 до 99
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_PHP_ERROR, 'Вызов недопустимого метода или функции класса'),
	);

	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/Session'
	);



	/*==============================================================================================
	Инициализация
	==============================================================================================*/

	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	private function init($options = null){

		#Применение пользовательских опций
		if(is_array($options)) 
			$this->options = array_merge($this->options, $options);

		#Вычисление префиксов
		if(is_array($this->options['prefixes'])) $this->addPrefix($this->options['prefixes']);

		#Использовать XCache для хранения сессий
		if(Config::getOption('Core/main','php_xcache',false)){
			session_set_save_handler(
				array(__CLASS__, 'xopen'),
				array(__CLASS__, 'xclose'),
				array(__CLASS__, 'xread'),
				array(__CLASS__, 'xwrite'),
				array(__CLASS__, 'xdestroy'),
				array(__CLASS__, 'xgc')
			);
		}

		Session::$session_expire = Config::getOption('Core/main','session_expire',(int)get_cfg_var('session.gc_maxlifetime'));

		$this->session_name = $this->options['session_name'];
		if($this->options['autostart']==true) $this->start();

	}#end function



	#--------------------------------------------------
	# Деструктор класса
	#--------------------------------------------------
	public function __destruct(){
		if($this->getStatus(false)) session_write_close();
	}#end function




	#--------------------------------------------------
	# Чтение данных из недоступных свойств
	#--------------------------------------------------
	public function __get($name){

		if(!$this->getStatus()) return false;
		foreach($this->prefixes as $key=>$len){
			if(strncmp($key, $name, $len)==0){
				if(!isset($_SESSION[$this->session_name][$name])) return false;
				return $_SESSION[$this->session_name][$name];
			}
		}

		return false;
	}#end function



	#--------------------------------------------------
	# Запись данных в недоступные свойства
	#--------------------------------------------------
	public function __set($name, $value){

		if(!$this->getStatus()) return false;
		foreach($this->prefixes as $key=>$len){
			if(strncmp($key, $name, $len)==0){
				$_SESSION[$this->session_name][$name] = $value;
				return true;
			}
		}

		return false;
	}#end function



	#--------------------------------------------------
	# будет выполнен при использовании isset() или empty() на недоступных свойствах.
	#--------------------------------------------------
	public function __isset($name){

		if(!$this->getStatus()) return false;
		foreach($this->prefixes as $key=>$len){
			if(strncmp($key, $name, $len)==0){
				return isset($_SESSION[$this->session_name][$name]);
			}
		}

		return false;
	}#end function




	#--------------------------------------------------
	# будет выполнен при вызове unset() на недоступном свойстве
	#--------------------------------------------------
	public function __unset($name){

		if(!$this->getStatus()) return false;
		foreach($this->prefixes as $key=>$len){
			if(strncmp($key, $name, $len)==0){
				if(!isset($_SESSION[$this->session_name][$name])) return true;
				unset($_SESSION[$this->session_name][$name]);
				return true;
			}
		}

		return true;
	}#end function








	/*==============================================================================================
	Функции: работа с сессией через XCache
	==============================================================================================*/


	public static function xopen($save_path, $session_name){
		return true;
	}

	public static function xclose(){
		return true;
	}

	public static function xread($session_id){
		return (string)xcache_get('sess/'.$session_id);
	}

	public static function xwrite($session_id, $session_data){
		return xcache_set('sess/'.$session_id, $session_data, Session::$session_expire);
	}

	public static function xdestroy($session_id){
		xcache_unset('sess/'.$session_id);
		return true;
	}

	public static function xgc($max_lifetime){
		return true;
	}









	/*==============================================================================================
	Функции: работа с сессией
	==============================================================================================*/



	#--------------------------------------------------
	# Старт сессии
	#--------------------------------------------------
	public function start(){

		#Если сессия запущена - возвращаем true
		if(!empty($this->session_id)) return true;

		#Старт сессии
		session_name($this->session_name);
		session_cache_expire(floor(Session::$session_expire/60));
		if( session_start() === false) return false;
		if(!isset($_SESSION[$this->session_name])) $_SESSION[$this->session_name] = array();

		$this->session_id		= session_id();
		$this->session_ip		= $this->getIP(false);
		$this->session_ip_real	= $this->getIP(true);

		$_SESSION[$this->session_name]['session_id'] = $this->session_id;
		$_SESSION[$this->session_name]['session_ip'] = $this->session_ip;
		$_SESSION[$this->session_name]['session_ip_real'] = $this->session_ip_real;

		return true;
	}#end function



	#--------------------------------------------------
	# Остановка сессии
	#--------------------------------------------------
	public function stop(){

		#Старт сессии
		if($this->getStatus(false)){
			$this->session_id		= null;
			$this->session_ip		= null;
			$this->session_ip_real	= null;
			return session_destroy();
		}

		return true;
	}#end function


	/*
	#--------------------------------------------------
	# Проверка статсуа сессии
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $autostart - признак автоматического старта сессии, если сессия отсутствует
	#
	# Возвращает:
	# TRUE, если сессия запущена, FALSE - если сессия не запущена
	*/
	public function getStatus($autostart = true){

		#Проверка статуса сессии
		$sess_id = session_id();
		$session_exists = (empty($sess_id) ? false : true);
		if($autostart && !$session_exists) return $this->start();

		return $session_exists;
	}#end function



	#--------------------------------------------------
	# Добавление префикса для обработки в __get и __set
	#--------------------------------------------------
	public function addPrefix($prefixes=null){

		if(empty($prefixes)) return false;
		if(!is_array($prefixes)){
			$this->prefixes[$prefixes] = strlen($prefixes);
			return true;
		}
		foreach($prefixes as $item){
			$this->prefixes[$item] = strlen($item);
		}

		return true;
	}#end function



	#--------------------------------------------------
	# Добавление префикса для обработки в __get и __set
	#--------------------------------------------------
	public function removePrefix($prefixes=null){

		if(empty($prefixes)) return false;
		if(!is_array($prefixes)){
			if(array_key_exists($prefixes, $this->prefixes)) unset($this->prefixes[$prefixes]);
			return true;
		}
		foreach($prefixes as $item){
			if(array_key_exists($item, $this->prefixes)) unset($this->prefixes[$prefixes]);
		}

		return true;
	}#end function



	/*
	#--------------------------------------------------
	# Получение значения из сессии
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $name - имя ключа интересуемого значения
	# если $name линейный массив ключей array('key1','key2'), 
	# функция вернет ассоциированный массив вида: array('key1'=>'value1','key2'=>'value2')
	# если указан NULL, имя не задано или ключа не существует - функция вернет false
	*/
	public function get($name=null){

		#Проверка сессии
		if(!$this->getStatus()) return false;
		if(empty($name)) return false;
		if(!is_array($name)) return (isset($_SESSION[$this->session_name][$name]) ? $_SESSION[$this->session_name][$name] : false);
		$result = array();
		foreach($name as $item){
			if(isset($_SESSION[$this->session_name][$item])) array_push($result, $_SESSION[$this->session_name][$item]);
		}

		return (count($result)>0 ? $result : false);
	}#end function



	/*
	#--------------------------------------------------
	# Запись значения в сессию
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $name - имя ключа значения
	# $value - значение
	# если $name - ассоциированный массив пар ключей и значений вида: array('key1'=>'value1','key2'=>'value2')
	# функция вернет функция запишет его в сессию, игнорируя $value
	# если указан NULL, имя не задано или ключа не существует - функция вернет false
	*/
	public function set($name=null, $value=null){

		#Проверка сессии
		if(!$this->getStatus()) return false;
		if(empty($name)) return false;
		$_SESSION[$this->session_name][$name] = $value;

		return true;
	}#end function





	/*
	#--------------------------------------------------
	# Проверка существования значения в сессии
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $name - имя ключа интересуемого значения
	# Возвращает TRUE, если параметр существует и FALSE в противном случае
	*/
	public function paramIsset($name=null){

		#Проверка сессии
		if(!$this->getStatus()) return false;
		if(empty($name)) return false;

		return (isset($_SESSION[$this->session_name][$name]) ? true : false);
	}#end function




	/*
	#--------------------------------------------------
	# Удаление значения из сессии
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $name - имя ключа интересуемого значения
	# Возвращает TRUE, если параметр удален и FALSE в случае ошибки
	*/
	public function paramUnset($name=null){

		#Проверка сессии
		if(!$this->getStatus()) return false;
		if(empty($name)) return false;

		if(isset($_SESSION[$this->session_name][$name])) unset($_SESSION[$this->session_name][$name]);

		return true;
	}#end function




	/*
	#--------------------------------------------------
	# Запись ассоциированного массива в сессию
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $data - ассоциированный массив вида: array('key'=>'value')
	#
	# Возвращает:
	# TRUE, если записано успешно, FALSE - в случае ошибки
	*/
	public function setArray($data = null){

		if(!is_array($data)) return false;

		#Проверка активности сессии
		if(!$this->getStatus()) return false;

		foreach($data as $key=>$value){
			$_SESSION[$this->session_name][$key] = $value;
		}

		return true;
	}#end function



	/*
	#--------------------------------------------------
	# Запись в сессию значения большой вложенности
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $key_array - линейный массив массив вида: array('key1', 'key2', 'key3')
	# $value - значение
	#
	# Результат работы функции будет иметь вид:
	# вызов: setMd(array('key1', 'key2', 'key3'), 'value')
	# $_SESSION[$this->session_name]['key1']['key2']['key3'] = $value
	*/
	public function setMd($key_array = null, $value = null){

		if(!$this->getStatus()) return false;
		if(empty($key_array)) return false;
		array_unshift($key_array, $this->session_name);
		$result = &$_SESSION;
		foreach($key_array as $v){
			if(!isset($result[$v]))$result[$v] = array();
			$result = &$result[$v];
		}
		$result = $value;

		return true;
	}#end function



	/*
	#--------------------------------------------------
	# Читает из сессии значения большой вложенности
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $key_array - линейный массив массив вида: array('key1', 'key2', 'key3')
	#
	# Результат работы функции будет иметь вид:
	# вызов: getMd(array('key1', 'key2', 'key3'), 'value')
	# возвратит значение, хранящееся в $_SESSION[$this->session_name]['key1']['key2']['key3'];
	*/
	public function getMd($key_array = null){

		if(!$this->getStatus()) return false;
		if(empty($key_array)) return false;
		array_unshift($key_array, $this->session_name);
		$result = &$_SESSION;
		foreach($key_array as $v){
			if(isset($result[$v])){
				$result = &$result[$v];
			}else
				return false;
		}

		return $result;
	}#end function



	/*
	#--------------------------------------------------
	# Удаляет из сессии значение
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $key - ключ или линейный массив ключей вида: array('key1', 'key2', 'key3')
	#
	# Возвращает:
	# TRUE, если элементы удалены, FALSE - в случае ошибки
	*/
	public function delete($key = null){

		if(!$this->getStatus()) return false;
		if(empty($key)) return false;

		if(!is_array($key)){
			if(isset($_SESSION[$this->session_name][$key])) unset($_SESSION[$this->session_name][$key]);
			return true;
		}

		foreach($key as $item){
			if(isset($_SESSION[$this->session_name][$item])) unset($_SESSION[$this->session_name][$item]);
		}

		return true;
	}#end function



	/*
	#--------------------------------------------------
	# Удаляет из сессии значения большой вложенности
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $key_array - линейный массив массив вида: array('key1', 'key2', 'key3')
	#
	# Результат работы функции будет иметь вид:
	# вызов: deleteMd(array('key1', 'key2', 'key3'))
	# удалит ключ и значение, хранящееся в $_SESSION[$this->session_name]['key1']['key2']['key3'];
	*/
	public function deleteMd($key_array = null){

		if(!$this->getStatus()) return false;
		if(empty($key_array)) return false;
		$path = "['".$this->session_name."']['".implode("']['", $key_array)."']";
		eval('if(isset($_SESSION'.$path.')) unset($_SESSION'.$path.');');

		return true;
	}#end function













	/*==============================================================================================
	Функции: работа с пользователем в рамках сессии
	==============================================================================================*/



	#--------------------------------------------------
	# Определение IP адреса пользователя
	#--------------------------------------------------
	public function getIP($real_ip = false){
		if($real_ip == true) return $_SERVER['REMOTE_ADDR'];
		$user_ip = '';
		if ( getenv('HTTP_FORWARDED_FOR') ) $user_ip = getenv('HTTP_FORWARDED_FOR');
		elseif ( getenv('HTTP_X_FORWARDED_FOR') ) $user_ip = getenv('HTTP_X_FORWARDED_FOR');
		elseif ( getenv('HTTP_X_COMING_FROM') ) $user_ip = getenv('HTTP_X_COMING_FROM');
		elseif ( getenv('HTTP_VIA') ) $user_ip = getenv('HTTP_VIA');
		elseif ( getenv('HTTP_XROXY_CONNECTION') ) $user_ip = getenv('HTTP_XROXY_CONNECTION');
		elseif ( getenv('HTTP_CLIENT_IP') ) $user_ip = getenv('HTTP_CLIENT_IP');
		elseif ( getenv('REMOTE_ADDR') ) $user_ip = getenv('REMOTE_ADDR');
		$user_ip = trim($user_ip);
		if ( empty($user_ip) ) return $_SERVER['REMOTE_ADDR'];
		if ( !preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $user_ip) ) return $_SERVER['REMOTE_ADDR'];
		return $user_ip;
	}#end function




	/*
	#--------------------------------------------------
	# Проверка корректности сессии пользователя
	#--------------------------------------------------
	#
	# Принимает параметры:
	# $params - ассоциированный массив параметров проверки, имеет вид
	# array(
	#  'key'=>'value' #key - имя в сессии  $_SESSION[$this->session_name][key], value = проверяемое значение, если value = null, то проверяется только факт наличия в сессии значения
	#)
	# $ignore_unsets - признак, когда TRUE, то отсутсвие параметра key в сессии не приводит к возврату FALSE, т.е. будут проверяться на соответствие только существующие параметры
	#
	# Возвращает FALSE если данные сессии корректны или значение, которое привело к выводу о некорректной сессии
	* Если не удалось инициализировать сессию - будет возвращено 'session'
	* Если IP некорректны - будет возвращено 'session_ip'
	* Если ID сессии некорректны - будет возвращено 'session_id'
	*/
	public function badSession($params=null, $check_ips = true, $check_sesison_id = true, $ignore_unsets = false){

		if(!$this->getStatus()) return 'session';
		if(!is_array($params)) $params = array();

		#Проверка Идентификатора сессии в сессии и текущего идентификатора сессии
		if($check_sesison_id){
			if($this->session_id != session_id()) return 'session_id';
		}

		#Проверка IP в сессии и текущего IP пользователя
		if($check_ips){
			if(
				($this->session_ip != $this->getIP(false)) || 
				($this->session_ip_real != $this->getIP(true))
			) return 'session_ip';
		}

		foreach($params as $key=>$value){
			if(isset($_SESSION[$this->session_name][$key])){
				if(!is_null($value) && strcmp($_SESSION[$this->session_name][$key],$value)!=0) return $key;
			}else{
				if(!$ignore_unsets) return $key;
			}
		}

		return false;
	}#end function











}#end class




?>