<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс протоколирования событий Debug
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/




class Debug{

	use Core_Trait_SingletonUnique;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/



	#Настройки и значения по-умолчанию для работы класса
	private $options = array();


	#Типы ошибок PHP
	#Декларированы в /server/config/Core/debug.config.php
	public static $phperrortypes = array();


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
		'log_file'	=> 'Core/Debug'
	);



	/*==============================================================================================
	Инициализация
	==============================================================================================*/



	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	protected function init($options=null){

		#Применение параметров
		$this->options = array_merge($this->options, (is_array($options) ? $options : Config::getOptions('Core/debug')));

		#Применение опций
		if(isset($this->options['error_types'])&&is_array($this->options['error_types'])){
			Debug::$phperrortypes = array_merge(Debug::$phperrortypes, $this->options['error_types']);
		}

		#Добавление функции-обработчика $this->errorLog() для события CORE_EVENT_ERROR (ошибки и предупреждения)
		Event::_addListener(EVENT_PHP_ERROR, array($this,'errorLog'));

	}#end function







	/*==============================================================================================
	Информационные функции
	==============================================================================================*/

	/*
	#--------------------------------------------------
	# Возвращение описания ошибки PHP по ее коду
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $errno - код группы ошибкок, по которой требуется получить описание 
	# $type - тип возвражаемой информации, может принимать значения:
	# 	'all' - функция вернет целиком ассоциированный массив array('code'=>'','name'=>'','desc'=>'')
	# 	'type'- функция вернет тип группы ошибок в виде текста (E_ERROR, E_WARNING, E_NOTICE и т.п.)
	# 	'name'- функция вернет имя группы ошибок 
	# 	'desc'- функция вернет описание группы ошибок
	#
	# Возвращает:
	# Запрошенный тип информации или NULL, если группа ошибок не найдена
	*/
	public function getPHPErrorInfo($errno=0, $type='type'){

		$errno = 'e'.$errno;
		$einfo = (!isset($this::$phperrortypes[$errno])||!is_array($this::$phperrortypes[$errno])) ? $this::$phperrortypes['e'.E_UNDEFINED] : $this::$phperrortypes[$errno];
		switch($type){
			case 'type': return $einfo['type'];
			case 'name': return $einfo['name'];
			case 'desc': return $einfo['desc'];
			default: return $einfo;
		};

	}#end function










	/*==============================================================================================
	Обработчик ошибок
	==============================================================================================*/



	#--------------------------------------------------
	# Логирование ошибок
	#--------------------------------------------------
	public function errorLog($event_name, $args){ 

		$errno	= $args['errno'];
		$errstr	= $args['errstr'];
		$errfile= $args['errfile'];
		$errline= $args['errline'];

		$einfo = $this->getPHPErrorInfo($errno, 'all');

		$message = 
			$errno."\t".
			$einfo['type']."\t".
			$errstr."\t".
			$errfile."\t".
			$errline;

		$backtrace = null;

		if($einfo['trace'] == true){
			if(empty($backtrace)) $backtrace = Debug::getBacktrace();
			$message .="\n".$backtrace;
		}


		#Запись в LOG-файл
		Debug::writeLog(array(
			'log_file'		=> DIR_LOGS.'/php_error.log',
			'message'		=> $message,
			'email'			=> $einfo['email'],
			'nofile'		=> false
		));

		#Вывод на экран
		if($this->options['display_errors']==true && $einfo['display'] == true){
			#Вывод ошибки на экран
			echo 
			"<pre>\nPHP ERROR:\n".
			"======================================================================\n".
			"PHP ERRNO: ".$errno."\n".
			"PHP ERROR: ".$einfo['type']."\n".
			"PHP ETYPE: ".$einfo['name']."\n".
			"Message  : ".$errstr."\n".
			"File     : ".$errfile."\n".
			"Line     : ".$errline."\n";

			if($einfo['trace'] == true){
				if(empty($backtrace)) $backtrace = Debug::getBacktrace();
				echo "\nBACKTRACE:\n\n".$backtrace;
			}
			echo
			"======================================================================\n\n</pre>";
		}

		return true;
	}#end function




	#--------------------------------------------------
	# Логирование ошибок в файл
	#--------------------------------------------------
	static public function writeLog($data=null){ 

		if(empty($data)) return false;
		if(!isset($data['message'])||empty($data['message'])) return false;
		if(!isset($data['log_file'])||empty($data['log_file'])) return false;

		$use_email		= (isset($data['email'])&&is_array($data['email'])) ? true : false;
		$use_backtrace	= (isset($data['backtrace'])&&$data['backtrace']==true) ? true : false;
		$no_logfile		= (isset($data['nofile'])&&$data['nofile']==true) ? true : false;
		$log_file		= (isset($data['log_file'])) ? $data['log_file'] : E_UNDEFINED;
		$message		= $data['message'];

		#Если требуется добавить бектрейс
		$message.= ($use_backtrace == true ) ? "\t".json_encode(Debug::getBacktrace('array')) : "\t";

		#Логирование в файл
		if(!$no_logfile){
			error_log(
				date('d.m.Y H:i:s')."\t".
				$message."\n",
				3,
				$log_file
			);
		}

/*
		#Сообщение на электронную почту
		if($use_email){

			$emails = NULL;
			if(isset($GLOBALS['OPTIONS']['log_email'][$real_type])) 
				$emails = $GLOBALS['OPTIONS']['log_email'][$real_type];

			if(is_array($emails) && count($emails)>0){
				$mail_message = 
						"<html><h2>".$real_type."</h2><br/>\n".
						'TIMESTAMP: <b>'.date('d.m.Y h:i:s')."</b><br/>\n".
						'LOG_FILE: '.$log_file."<br/>\n".
						'LOG_FROM: '.$log_from."<br/>\n".
						'LOG_TYPE: '.$real_type."<br/><br/>\n\n".
						"MESSAGE:<br/><br/>\n\n".$message.
						'</html>';
				$mail_headers = 
					"subject: ".$real_type."=>".$log_from."\n".
					"Content-Type: text/html; charset=windows-1251";
				foreach($emails as $email){
					error_log($mail_message, 1, $email, $mail_headers);
				}
			}

		}#Сообщение на электронную почту


		echo "\nEXCEPTION ".$errno." [type=".$this->getPHPErrorInfo($errno)."]: ".$errstr." at file:".$errfile.", line:".$errline."\n<br>\n";
		echo $this::getBacktrace();
*/
		return true;
	}#end function



	#--------------------------------------------------
	#Функция трассировки
	#--------------------------------------------------
	static public function getBacktrace($type='text', $prefix="\t", $suffix="\n"){


		$output = ($type == 'text' ? '' : array());
		$backtrace = debug_backtrace();
		array_shift($backtrace);
		$index = 0;

		foreach ($backtrace as $bt){

			$args = '';
			foreach ($bt['args'] as $a) {
				if (!empty($args)) $args .= ', ';
				switch (gettype($a)) {
					case 'integer':
					case 'double':
						$args .= $a;
					break;
					case 'string':
						$a = (substr($a, 0, 264)).((strlen($a) > 264) ? '...' : '');
						$args .= "\"$a\"";
						break;
					case 'array':
						$args .= 'Array('.count($a).')';
						break;
					case 'object':
						$args .= 'Object('.get_class($a).')';
					break;
					case 'resource':
						$args .= 'Resource('.strstr($a, '#').')';
					break;
					case 'boolean':
						$args .= $a ? 'True' : 'False';
					break;
					case 'NULL':
						$args .= 'Null';
					break;
					default:
						$args .= 'Unknown';
				}
			}
			if(!isset($bt['file'])) $bt['file']='[noFile]';
			if(!isset($bt['line'])) $bt['line']='-1';
			if(!isset($bt['class'])) $bt['class']='';
			if(!isset($bt['type'])) $bt['type']='';
			if(!isset($bt['function'])) $bt['function']='';
			$line = $index.': '.$bt['file'].' ('.$bt['line'].'): '.$bt['class'].$bt['type'].$bt['function'].'('.$args.')';
			if($type =='text')
				$output .= $prefix.$line.$suffix;
			else
				array_push($output, $line);
			$index++;

		}
		//#0 /opt/www/dev/test/classes/crs.php(43): crs->load()
		return $output;
	}#end function






	/*==============================================================================================
	Обработчик ошибок
	==============================================================================================*/







}#end class


?>