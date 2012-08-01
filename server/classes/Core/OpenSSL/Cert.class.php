<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы / безопасность
Описание: Класс работы с сертификатами X509 и закрытыми ключами
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/




class Core_OpenSSL_Cert{

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/


	#X509 сертификат
	protected $cert_resource = null;

	#Закрытый ключ
	protected $key_resource = null;

	#Пароль для закрытого ключа
	protected $key_pass = '';

	#Массив свойств X509 сертификата
	public $cert_info = array();


	#Номер ошибки
	protected $errno = 0;			#Номер последней ошибки в классе, 0 - нет ошибок


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
		'log_file'	=> 'Core/Database'
	);



	/*==============================================================================================
	Информационные функции
	==============================================================================================*/



	#--------------------------------------------------
	#Генератор события об ошибке
	#--------------------------------------------------
	private function doErrorEvent($errno, $function='', $line=0, $info=null){

		Event::getInstance()->fireEvent(
			EVENT_OPENSSL_ERROR,
			array(
				'errno'			=> ($this->errno == 0 ? E_USER_NOTICE : ($this->errno < 100 ? E_USER_ERROR : E_USER_WARNING)),
				'errstr'		=> $this->getErrstr(),
				'file'			=> __FILE__,
				'line'			=> $line,
				'user_errno'	=> $this->errno,
				'class'			=> __CLASS__,
				'function'		=> $function,
				'info'			=> $info
			)
		);
		return false;
	}#end function


	#--------------------------------------------------
	#Получение описания ошибки исходя из номера ошибки
	#--------------------------------------------------
	public function getErrstr($errno=0){

		$errno = $errno == 0 ? $this->errno : $errno;
		switch($errno){
			#Нет ошибок
			case   0 : return 'Нет ошибок';

			#Системные ошибки, от 1 до 99
			case   1 : return 'Вызов недопустимого метода или функции класса';

			case  10 : return 'Не задан файл X509 сертификата';
			case  11 : return 'Не задан файл закрытого ключа';

			case  14 : return 'Ошибка экспорта сертификата в файл';
			case  15 : return 'Ошибка экспорта закрытого ключа в файл';
			case  16 : return 'Ошибка экспорта пароля закрытого ключа в файл';
			case  17 : return 'Ошибка экспорта запроса на сертификат в файл';

			#Предупреждения, от 100 и далее
			case 100 : return '';

			default: return '';
		}

	}#end function









	/*==============================================================================================
	Инициализация
	==============================================================================================*/



	/*
	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $cert_file - путь к файлу сертификата
	# $key_file - путь к файлу закрытого ключа
	# $password - пароль доступа к закрытому ключу
	*/
	public function __construct($cert_file=null, $key_file=null, $password=null){

		if(!is_null($cert_file)){
			if(is_file($cert_file) || is_resource($cert_file)){
				$this->setCert($cert_file);
				$this->parse();
			}else{
				return $this->doErrorEvent(10, __FUNCTION__, __LINE__, $cert_file);
			}
		}else{
			$this->cert_resource = null;
		}

		if(!is_null($cert_file)){
			if(is_file($key_file) || is_resource($key_file)){
				$this->setKey($key_file, $password);
			} else {
				return $this->doErrorEvent(11, __FUNCTION__, __LINE__, $key_file);
			}
		} else {
			$this->key_resource = null;
		}

		$this->key_pass = $password;
	}#end function



	#--------------------------------------------------
	# Деструктор класса
	#--------------------------------------------------
	public function __destruct(){
		if($this->cert_resource) @openssl_x509_free($this->cert_resource);
		if($this->key_resource) @openssl_free_key($this->key_resource);
	}#end function



	#--------------------------------------------------
	# Чтение данных из недоступных свойств
	#--------------------------------------------------
	public function __get($name){

		if(!is_array($this->cert_info) || !array_key_exists($name, $this->cert_info)) return $this->doErrorEvent(1, __FUNCTION__, __LINE__, $name);

		return $this->cert_info[$name];
	}#end function








	/*==============================================================================================
	Основные функции
	==============================================================================================*/



	#--------------------------------------------------
	# Получение сертификата
	#--------------------------------------------------
	public function getCert(){
		return (empty($this->cert_resource) ? null : $this->cert_resource);
	}#end function



	#--------------------------------------------------
	# Получение закрытого ключа
	#--------------------------------------------------
	public function getKey(){
		return (empty($this->key_resource) ? null : $this->key_resource);
	}#end function



	#--------------------------------------------------
	# Получение пароля для закрытого ключа
	#--------------------------------------------------
	public function getPassword(){
		return $this->key_pass;
	}#end function




	#--------------------------------------------------
	# Чтение сертификата из файла
	#--------------------------------------------------
	public function setCert($cert_file){

		if(is_string($cert_file)&&is_file($cert_file)){
			$this->cert_resource = openssl_x509_read(XCache::_getFileContent($cert_file));
			$this->parse();
		}else if(is_resource($cert_file)){
			$this->cert_resource = $cert_file;
			$this->parse();
		}else{
			return $this->doErrorEvent(10, __FUNCTION__, __LINE__, $cert_file);
		}

		return true;
	}#end function



	#--------------------------------------------------
	# Чтение закрытого ключа из файла
	#--------------------------------------------------
	public function setKey($key_file=null, $password = null){

		$this->key_pass = $password;

		if(is_string($key_file)&&is_file($key_file)){
			$this->key_resource = openssl_get_privatekey(XCache::_getFileContent($key_file), $password);
		}else if(is_resource($key_file)){
			$this->key_resource = $key_file; 
		}else {
			return $this->doErrorEvent(11, __FUNCTION__, __LINE__, $key_file);
		}

		if(!is_null($password)) $this->setPassword($password);

		return true;
	}#end function



	#--------------------------------------------------
	# Изменение пароля для закрытого ключа
	#--------------------------------------------------
	public function setPassword($password){
		return $this->key_pass = $password;
	}#end function



	#--------------------------------------------------
	# Проверка соответствия закрытого ключа указанному сертификату
	#--------------------------------------------------
	public function check(){

		if(empty($this->cert_resource) || empty($this->key_resource)) return false;

		if (openssl_x509_check_private_key($this->cert_resource, $this->key_resource)){
			return true;
		}

		return false;
	}#end function



	/*
	#--------------------------------------------------
	# Разбарает X509 сертификат и фозвращает массив его параметров
	#--------------------------------------------------
	#
	# Принимаемые параметры:
	# $shotnames - признак,  указывающий о необходимости вернуть "сокращенные" наименования параметров, например:
	# вместо "commonName" будет возвращено "CN"
	*/
	public function parse($shotnames = false){

		if (empty($this->cert_resource)) return false;

		$this->cert_info = openssl_x509_parse($this->cert_resource, $shotnames);

		return $this->cert_info;
	}#end function










}#end class

?>