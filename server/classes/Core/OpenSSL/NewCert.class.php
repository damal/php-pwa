<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы / безопасность
Описание: Класс создания сертификата X509
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/




class Core_OpenSSL_NewCert extends Core_OpenSSL_Cert{



	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Запрос сертификата
	protected $request_resource = null;


	#Количество дней действия сертификата
	public $valid_days = 3650;


	#Настройки по-умолчанию для генерации сертификата
	private $options = array(

		#Параметры нового сертификата
		'cert'=>array(
			'countryName'				=> 'RU',						#Код страны
			'stateOrProvinceName'		=> 'Russian Federation',		#Провинция, область
			'localityName'				=> 'Rostov-on-Don',				#Город
			'organizationName'			=> 'Sintez Ltd.',				#Организация
			'organizationalUnitName'	=> 'CardSystems',				#Подразделение
			'commonName'				=> 'Stanislav V. Tretyakov',	#Домен или имя, кому выдан сертификат
			'emailAddress'				=> 'support@tk-ug.ru'			#Адрес электронной почты
		),
		#Параметры SSL
		'ssl'=>array(
			'digest_alg'		=> 'sha1',
			'x509_extensions'	=> 'v3_ca',						#Selects which extensions should be used when creating a x509 certificate.
			'req_extensions'	=> 'v3_req',					#Selects which extensions should be used when creating a CSR.
			'private_key_bits'	=> 2048,						#Specifies how many bits should be used to generate a private key.
			'private_key_type'	=> OPENSSL_KEYTYPE_RSA,			#Specifies the type of private key to create. This can be one of OPENSSL_KEYTYPE_DSA, OPENSSL_KEYTYPE_DH or OPENSSL_KEYTYPE_RSA
			'encrypt_key'		=> true,						#Should an exported key (with passphrase) be encrypted
			'config'			=> '/usr/local/php5/extras/openssl/openssl.cnf'	#Path to to the openssl.conf file
			//'config'			=> '/etc/pki/tls/openssl.cnf'	#Path to to the openssl.conf file
		),
		#Количество дней действия сертификата
		'valid_days'			=> 3650,
		#Папка для сохранения файлов сертификата
		'cert_folder'			=> DIR_CERTS,
		#Имя файла сертификата
		'cert_name'				=> 'main',
		#Пароль для закрытого ключа
		'key_password'			=> ''

	);




	/*==============================================================================================
	Инициализация
	==============================================================================================*/





	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	public function __construct($options=null, $valid_days = 3650){

		#Установка опций
		if (is_array($options))
			$this->options = array_merge($this->options, $options);

		#Срок действия сертификата
		$this->valid_days = $valid_days;

		$this->setKey(openssl_pkey_new($this->options['cert']), $this->options['key_password']); #Создание закрытого ключа
		$this->setCsr(openssl_csr_new($this->options['cert'], $this->getKey(), $this->options['ssl'])); #Создание запроса на сертификат
		$this->setCert(openssl_csr_sign(
			$this->getCsr(),
			null,
			array(
				$this->getKey(),
				$this->options['key_password']
			),
			$this->options['valid_days'],
			$this->options['ssl']
		));

		$cert_file = $this->options['cert_folder'].'/'.$this->options['cert_name'];
		$this->exportCert($cert_file.'.crt');
		$this->exportKey($cert_file.'.key', $this->options['key_password']);
		$this->exportCsr($cert_file.'.csr');
		$this->exportPassword($cert_file.'.pwd', $this->options['key_password']);

		

	}#end function



	#--------------------------------------------------
	# Задание запроса на выдачу сертификата
	#--------------------------------------------------
	function setCsr($crtResource){

		if (!is_resource($crtResource)) return false;
		$this->request_resource = $crtResource; 

		return true;
	}#end function



	#--------------------------------------------------
	# Получение запроса на выдачу сертификата
	#--------------------------------------------------
	function getCsr(){

		if (empty($this->request_resource)) return false;

		return $this->request_resource;
	}#end function




	/*
	#--------------------------------------------------
	# Экспортирует X509 сертификат в файл
	#--------------------------------------------------
	#
	# Принимаемые параметры:
	# $filename - имя файла, в которое требуется экспортировать сертификат
	*/
	private function exportCert($filename = null){

		if(empty($filename)) return false;
		$filepath = pathinfo($filename);
		if(!is_writable($filepath['dirname'])) return false;

		if( openssl_x509_export_to_file($this->getCert(), $filename, true) === false) return $this->doErrorEvent(14, __FUNCTION__, __LINE__, openssl_error_string());

		return true;
	}#end function



	/*
	#--------------------------------------------------
	# Экспортирует закрытый ключ сертификата в файл
	#--------------------------------------------------
	#
	# Принимаемые параметры:
	# $filename - имя файла, в которое требуется экспортировать закрытый ключ
			case  16 : return 'Ошибка экспорта пароля закрытого ключа в файл';
			case  17 : return 'Ошибка экспорта запроса на сертификат в файл';
	*/
	private function exportKey($filename = null, $password = null){

		if(empty($filename)||is_null($password)) return false;
		$filepath = pathinfo($filename);
		if(!is_writable($filepath['dirname'])) return false;

		if( openssl_pkey_export_to_file($this->getKey(), $filename, $password, $this->options['ssl']) === false ) return $this->doErrorEvent(15, __FUNCTION__, __LINE__, openssl_error_string());

		return true;
	}#end function



	/*
	#--------------------------------------------------
	# Экспортирует запрос на выпуск сертификата в файл
	#--------------------------------------------------
	#
	# Принимаемые параметры:
	# $filename - имя файла, в которое требуется экспортировать запрос
	*/
	private function exportCsr($filename = null){

		if(empty($filename)) return false;
		$filepath = pathinfo($filename);
		if(!is_writable($filepath['dirname'])) return false;

		if( openssl_csr_export_to_file($this->getCsr(), $filename, true) === false ) return $this->doErrorEvent(17, __FUNCTION__, __LINE__, openssl_error_string());

		return true;
	}#end function



	/*
	#--------------------------------------------------
	# Экспортирует пароль закрытого ключа в файл
	#--------------------------------------------------
	#
	# Принимаемые параметры:
	# $filename - имя файла, в которое требуется экспортировать пароль
	*/
	private function exportPassword($filename = null, $password = null){

		if(empty($filename)||is_null($password)) return false;
		$filepath = pathinfo($filename);
		if(!is_writable($filepath['dirname'])) return false;

		if( file_put_contents($filename, $password) === false ) return $this->doErrorEvent(16, __FUNCTION__, __LINE__, $filename);

		return true;
	}#end function



}#end class

?>