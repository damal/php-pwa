<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы / безопасность
Описание: Класс работы с OpenSSL
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/


class Core_OpenSSL_RSA{

	use Core_Trait_SingletonArray, Core_Trait_BaseError;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Ссылка на объект класса, паттерн Singleton
	protected static $instance = array();


	#Параметры для OpenSSL сертификата
	protected $options = array(

		'cert'				=> null,	#Ссылка на объект экземпляра класса Core_OpenSSL_Cert
		'cert_file'			=> null,	#Ссылка на файл сертификата
		'key_file'			=> null,	#Ссылка на файл закрытого ключа
		'cert_password'		=> 'Copyright (c) Stanislav V. Tretyakov',	#Пароль для доступа к закрытому ключу, имеет приоритет перед pwd_file
		'pwd_file'			=> null,	#Ссылка на файл с паролем к закрытому ключу, будет загружен из файла, если cert_password = null

		#NULL
		'null'=>null
	);


	public $_cert	= null; #Ссылка на объект экземпляра класса Core_OpenSSL_Cert
	public $envelope_key = null;
	private $cert_init = false;


	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		#Системные ошибки, от 1 до 99
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_PHP_ERROR, 'Вызов недопустимого метода или функции класса'),
		10	=> array(EVENT_OPENSSL_ERROR, 'Нет сертификата'),
		15	=> array(EVENT_OPENSSL_ERROR, 'Ошибка входных данных'),
		16	=> array(EVENT_OPENSSL_ERROR, 'Ошибка шифрования'),
		18	=> array(EVENT_OPENSSL_ERROR, 'Ошибка расшифровки'),
		19	=> array(EVENT_OPENSSL_ERROR, 'Ошибка подписи данных'),
		20	=> array(EVENT_OPENSSL_ERROR, 'Ошибка при проверке подписанных данных'),
	);



	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/OpenSSL_RSA'
	);







	/*==============================================================================================
	Инициализация
	==============================================================================================*/



	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	protected function init($connection = 'main', $options = null){

		#Установка опций
		if(is_array($options)) $this->options = array_merge($this->options, $options);
/*
		#Применение объекта сертификата
		if(empty($this->options['cert'])||(!($this->options['cert'] instanceof Core_OpenSSL_Cert)&&!($this->options['cert'] instanceof Core_OpenSSL_NewCert))){
			try{
				$this->_cert = new Core_OpenSSL_Cert();
				if(file_exists(DIR_CERTS.$this->options['cert_file'])&&is_readable(DIR_CERTS.$this->options['cert_file'])) $this->_cert->setCert(DIR_CERTS.$this->options['cert_file']);
				if(is_null($this->options['cert_password'])){
					$this->options['cert_password'] = (file_exists(DIR_CERTS.$this->options['pwd_file'])&&is_readable(DIR_CERTS.$this->options['pwd_file'])) ? XCache::_getFileContent(DIR_CERTS.$this->options['pwd_file']) : '';
				}
				if(file_exists(DIR_CERTS.$this->options['key_file'])&&is_readable(DIR_CERTS.$this->options['key_file'])) $this->_cert->setKey(DIR_CERTS.$this->options['key_file'], $this->options['cert_password']);
			}catch(Exception $e){
				//Core::getInstance()->exceptionHandler($e);
			}
		}else{
			$this->_cert = $this->options['cert'];
		}
*/
	}#end function




	/*==============================================================================================
	Инициализация сертификата
	==============================================================================================*/

	private function certInit(){

		if($this->cert_init) return true;

		#Применение объекта сертификата
		if(empty($this->options['cert'])){
			try{
				$this->_cert = new Core_OpenSSL_Cert();
				$this->_cert->setCert(DIR_CERTS.$this->options['cert_file']);

				if(is_null($this->options['cert_password'])){
					$this->options['cert_password'] = XCache::_getFileContent(DIR_CERTS.$this->options['pwd_file']);
				}

				$this->_cert->setKey(DIR_CERTS.$this->options['key_file'], $this->options['cert_password']);

			}catch(Exception $e){
				Core::getInstance()->exceptionHandler($e);
				return false;
			}
		}else{
			if(!($this->options['cert'] instanceof Core_OpenSSL_Cert)&&!($this->options['cert'] instanceof Core_OpenSSL_NewCert)) return false;
			$this->_cert = $this->options['cert'];
		}

		$this->cert_init = true;

		return true;
	}#end function











	/*==============================================================================================
	RSA функции
	==============================================================================================*/


	/*
	#--------------------------------------------------
	# Шифрование данных с использованием X509 публичного ключа
	#--------------------------------------------------
	#
	# Аргументы:
	# $data (*) - текст для шифрования
	#
	# Возвращает зашифрованные данные.
	# В случае ошибки возвращается FALSE
	*/
	public function encrypt($data=null){

		if(!$this->certInit()) $this->doErrorEvent(10, __FUNCTION__, __LINE__, var_export($this->_cert,true));
		if(!is_string($data)) return $this->doErrorEvent(15, __FUNCTION__, __LINE__, var_export($data,true)); #Ошибка входных данных

		$crypted_data = null;
		$envelope_key = $this->options['cert_password'];

		if( openssl_seal($data, $crypted_data, $envelope_key, array($this->_cert->getCert()))===false) 
			return $this->doErrorEvent(16, __FUNCTION__, __LINE__,  openssl_error_string()); #Ошибка шифрования

		$this->envelope_key = $envelope_key['0'];

		return $crypted_data;
	}#end function



	/*
	#--------------------------------------------------
	# Расшифровка данных с использованием закрытого ключа
	#--------------------------------------------------
	#
	# Аргументы:
	# $data (*) - шифрованные данные
	
	# Возвращает расшифрованные данные.
	# В случае ошибки возвращается FALSE
	*/
	public function decrypt($data=null){

		if(!$this->certInit()) $this->doErrorEvent(10, __FUNCTION__, __LINE__, var_export($this->_cert,true));
		if(!is_string($data)) return $this->doErrorEvent(15, __FUNCTION__, __LINE__, var_export($data,true)); #Ошибка входных данных

		$decrypted_data = null;
		$envelope_key = $this->options['cert_password'];

		if( openssl_open($data, $decrypted_data, $envelope_key, $this->_cert->getKey()) === false)
			return $this->doErrorEvent(18, __FUNCTION__, __LINE__,  openssl_error_string());

		return $decrypted_data;
	}#end function




	/*
	#--------------------------------------------------
	# Подпись данных
	#--------------------------------------------------
	#
	# Аргументы:
	# $data (*) - данные для подписи
	# $algorithm - алгоритм подписи, по-умолчанию OPENSSL_ALGO_SHA1, может быть: OPENSSL_ALGO_DSS1, OPENSSL_ALGO_SHA1, OPENSSL_ALGO_MD5
	#
	# Возвращает SHA1 подписанных данных.
	# В случае ошибки возвращается FALSE
	*/
	public function sign($data=null, $algorithm = OPENSSL_ALGO_SHA1){

		if(!$this->certInit()) $this->doErrorEvent(10, __FUNCTION__, __LINE__, var_export($this->_cert,true));
		if(!is_string($data)) return $this->doErrorEvent(15, __FUNCTION__, __LINE__, var_export($data,true)); #Ошибка входных данных

		$signature = null;

		if( openssl_sign($data, $signature, $this->_cert->getKey(), $algorithm) === false)
			return $this->doErrorEvent(19, __FUNCTION__, __LINE__,  openssl_error_string());

		return $signature;
	}#end functions




	/*
	#--------------------------------------------------
	# Проверка подписанных данных
	#--------------------------------------------------
	#
	# Аргументы:
	# $data (*) - данные для подписи
	# $signature (*) - сигнатура подписи (SHA1)
	# $algorithm - алгоритм подписи, по-умолчанию OPENSSL_ALGO_SHA1, может быть: OPENSSL_ALGO_DSS1, OPENSSL_ALGO_SHA1, OPENSSL_ALGO_MD5
	#
	# Возвращает 1 если подпись корректна, 0 - если подпись не корректна.
	# В случае ошибки возвращается FALSE
	*/
	public function verify($data=null, $signature = null, $algorithm = OPENSSL_ALGO_SHA1){

		if(!$this->certInit()) $this->doErrorEvent(10, __FUNCTION__, __LINE__, var_export($this->_cert,true));
		if(!is_string($data)) return $this->doErrorEvent(15, __FUNCTION__, __LINE__, var_export($data,true)); #Ошибка входных данных
		if(!is_string($signature)) return $this->doErrorEvent(15, __FUNCTION__, __LINE__, var_export($signature,true)); #Ошибка входных данных

		if( ($check = openssl_verify($data, $signature, $this->_cert->getCert(), $algorithm)) == -1)
			return $this->doErrorEvent(20, __FUNCTION__, __LINE__,  openssl_error_string());

		return $check;
	}#end functions








}#end class



?>