<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс инициализации приложения
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/



class Core{

	use Core_Trait_SingletonUnique;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/


	#Настройки класса, применяются из переданных настроек при инициализации класса 
	#или из настроек
	protected $options = array();


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
		'log_file'	=> 'Core/Core'
	);



	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Старт, алиас для getInstance
	#--------------------------------------------------
	public static function start($options=null){
		return Core::getInstance($options);
	}#end function



	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	protected function init($options=null){


		#Подключение файла /server/config/defines.config.php
		#Декларирование констант
		Config::getConfig('Core/defines');


		#Установка опций
		$this->options = (is_array($options) ? array_merge($this->options, $options) : Config::getOptions('Core/main'));


		#Локализация
		setlocale(LC_ALL, $this->options['php_locale']);
		setlocale(LC_NUMERIC, $this->options['php_numeric']);
		date_default_timezone_set($this->options['php_timezone']);

		#XCache
		if(!XCache::_isEnabled()){
			Config::setOption('Core/main','php_xcache',false);
			$this->options['php_xcache'] = false;
		}


		#Инициализация событий
		Event::getInstance();


		#Инициализация отладчика
		Debug::getInstance();


		#Инициализация OpenSSL
		foreach($this->options['x509'] as $key=>$value){

			#Разбор массива настроек сертификатов
			switch($key){

				#Сертификаты подписи
				case 'sign':

					#Просмотр сертификатов для подписи и инициализация объекта сертификата
					foreach($value as $cert_name=>$cert_data){

						#Исключаем из списка подписей наименования: default, sha1, md5, crc32
						#Поскольку они являются зарезервированными
						if($cert_name == 'default' || $cert_name == 'sha1' || $cert_name == 'md5' || $cert_name == 'crc32'){
							#Выбор сертификата по-умолчанию
							if($cert_name == 'default' && !empty($cert_data)){
								Config::getConfig('Core/main')->set('x509_sign_default',$cert_data);
								$this->options['x509_sign_default'] = $cert_data;
							}
						}else{
							if(is_array($cert_data)) Core_OpenSSL_RSA::getInstance('sign/'.$cert_name, $cert_data);
						}

					}

				break;

				#Сертификаты шифрования
				case 'crypt':

					#Просмотр сертификатов для шифрования и инициализация объекта сертификата
					foreach($value as $cert_name=>$cert_data){

						if($cert_name == 'default'){
							if(!empty($cert_data)&&isset($value[$cert_data])&&is_array($value[$cert_data])){
								Config::getConfig('Core/main')->set('x509_crypt_default',$cert_data);
								$this->options['x509_crypt_default'] = $cert_data;
							}
						}else{
							if(is_array($value)) OpenSSL::getInstance('crypt/'.$cert_name, $cert_data);
						}

					}

				break;

				#Прочие сертификаты
				case 'cert':

					#Просмотр сертификатов и инициализация объекта сертификата
					foreach($value as $cert_name=>$cert_data){
						if(is_array($value)) OpenSSL::getInstance('cert/'.$cert_name, $cert_data);
					}

				break;
			}
		}

		#Инициализация Hash
		Hash::getInstance(array(
			'black_fields'	=> $this->options['hash']['black_fields'],
			'connection'	=> 'sign'
		));

		#Инициализация сессии
		Session::getInstance(array(
			'session_name'	=> $this->options['session_name']
		));


		#Инициализация LOG файла аудита
		LogFile::getInstance(
			'core/security/audit',
			array(
				#Путь к файлу журнала, пример: /var/www/test/server/logs/Core/Security/audit.log
				'file' => DIR_LOGS.'/Core/Security/audit.log'
			)
		);

		#Инициализация соединения с базами данных
		foreach(Config::getOptions('Core/databases') as $key=>$value){
			if(is_array($value)) Database::getInstance($key, $value);
		}

		#Инициализация ACL
		Acl::getInstance(Config::getOptions('Core/acl'));

	}#end function







	/*==============================================================================================
	Информационные функции
	==============================================================================================*/


	#--------------------------------------------------
	# Версия ядра
	#--------------------------------------------------
	public static function coreVersion(){ 
		return 'Core: v.1.0.0/ALPHA';
	}#end function








	/*==============================================================================================
	Автоподключение
	==============================================================================================*/


	#--------------------------------------------------
	# Автоподключение классов
	#--------------------------------------------------
	public function autoloadClassHandler($class_name){
		$class_file = realpath(DIR_CLASSES.'/'.str_replace('_','/',$class_name).'.class.php');
		if(!is_file($class_file)||!is_readable($class_file)) return false;
		require_once($class_file);
		return true;
	}#end function









	/*==============================================================================================
	Запуск функций 
	==============================================================================================*/

	/*
	#--------------------------------------------------
	# Запуск произвольной функции через ядро
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $call_function (*) - имя вызываемой функции или непосредственно сама функция.
	# $event_name - название события, генерируемое ядром при завершении работы функции,
	# произвольное текстовое значение (например: "create_client","boom" и т.д.), если null - событие не генерируется
	# $args - переменная, передаваемая в вызываемую функцию (строка, массив, объект, ресурс, что угодно)
	#
	# В момент вызова, функции передается ассоциированный массив, состоящий из следующих элементов:
	# 'app' - ссылка на экземпляр текущего класса Core - $this
	# 'args' - передаваемый в функцию аргумент (строка, массив, объект, ресурс, что угодно)
	# 'event' - название события, с которым была вызвана функция, передается $event_name
	#
	# Возвращает:
	# Результат работы функции
	#
	# Примеры вызова:
	# 1) $result = $this->exec('boomFunct', 'boom', 'argument as text variable');
	# 2) $result = $this->exec(function($data){ return 'hello world!';}, 'test_event', null);
	*/
	public function exec($call_function, $event_name=null, $args=null){

		#$call_function можно вызвать как функцию
		if(is_callable($call_function)){
			$result = call_user_func($call_function, array(
				'app' =>$this,
				'args'=>$args,
				'event'=>$event_name
			));
		}
		#$call_function не определен как функция
		else{
			#Если переданное имя функции - текст, пробуем подключить файл функций
			if(is_string($call_function)&&!function_exists($call_function)){
				$function_file = $call_function;
				$first = strpos($function_file,'_');
				$last = strrpos($function_file,'_');
				if($first != $last){
					$function_file[$first] = '/';
					$function_file = substr($function_file, 0, $last);
				}else
				if($first == $last && $first !== false ){
					$function_file = substr($function_file, 0, $last);
				}
				$function_file = realpath(DIR_FUNCTIONS.'/'.$function_file.'.functions.php');
				if(is_file($function_file)&&is_readable($function_file)) require_once($function_file);
			}
			#Если переданное имя функции - массив, пробуем подключить файл класса
			else
			if(is_array($call_function)&&!method_exists($call_function[0],$call_function[1])){
				$this->autoloadClassHandler((is_object($call_function[0])?get_class($call_function[0]):$call_function[0]));
			}
			$result = call_user_func($call_function, array(
				'app' =>$this,
				'args'=>$args,
				'event'=>$event_name
			));
		}
		if(!is_null($event_name)) $this->Event->fireEventNoResult($event_name, $result);

		return $result;
	}#end function



}#end class



?>