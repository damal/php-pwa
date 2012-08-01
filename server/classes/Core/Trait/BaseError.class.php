<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Вазовый класс ядра
Версия	: 1.0.0/ALPHA
Дата	: 2012-05-10
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/




trait Core_Trait_BaseError{

/*
	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		0 => array(0, 'Нет ошибки'),
		1 => array(0, 'Вызов недопустимого метода или свойства класса')
	);
*/

	# Уровень обработки ошибок: 
	# 0 - Никак не реагировать на ошибки
	# 1 - Записывать в LOG файл только системные ошибки (код от 0 до 99).
	# 2 - Записывать в LOG файл все ошибки и предупреждения
	# 3 - Записывать в LOG файл все + выдавать сообщения о системных ошибках
	# 4 - Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях
	# 5 - Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях, при системных ошибках - завершать работу
	protected $error_level = 4;

	#Номер последней ошибки в классе, 0 - нет ошибок
	# Ошибки делятся на несколько групп:
	# от 1 до 1000 - ошибки, генерируемые E_USER_ERROR
	# от 100 и более - предупреждения, генерируемые E_USER_WARNING
	protected $errno	=	0;








	/*==============================================================================================
	Функции: обработка ошибок
	==============================================================================================*/


	#Функция, вызываемая в классе при возникновении ошибки (вызове функции doErrorEvent)
	#$event_name - передается идентификатор события
	#$data - информация об ошибке
	protected function doErrorAction($event_name, $data){}



	#--------------------------------------------------
	#Получение номера ошибки при возвращении функцией класса FALSE
	#--------------------------------------------------
	public function getErrno(){
		return $this->errno;
	}#end function



	#--------------------------------------------------
	#Получение описания ошибки исходя из номера ошибки
	#--------------------------------------------------
	public function getErrstr($errno=0){

		$errno = $errno == 0 ? $this->errno : $errno;
		$errors = static::$errors;

		return (array_key_exists($errno, $errors) !== false) ? $errors[$errno][1] : '';
	}#end function



	#--------------------------------------------------
	#Генератор события об ошибке
	#--------------------------------------------------
	#--------------------------------------------------
	#Генератор события об ошибке ACL
	#--------------------------------------------------
	protected function doErrorEvent($errno=0, $function='', $line=0, $info=null){

		return $this->doErrorEventCustom(array(
			'errno'			=> $errno,
			'module'		=> self::$class_about['module'],
			'namespace'		=> self::$class_about['namespace'],
			'file'			=> self::$class_about['file'],
			'line'			=> $line,
			'function'		=> $function,
			'info'			=> $info,
			'backtrace'		=> (empty(self::$class_about['backtrace']) ? false : true)
		));

	}#end function



	#--------------------------------------------------
	#Генератор события об ошибке
	#--------------------------------------------------
	protected function doErrorEventCustom($data=null){


		if(empty($data)||!is_array($data)) return false;

		#Преобразование элементов массива в скалярные
		foreach($data as $k=>$v){
			if(!is_scalar($v)) $data[$k] = print_r($v,true);
			$data[$k] = strtr($data[$k], "\r\n\t", "   ");
		}

		$user_errno		= (isset($data['errno']) ? $data['errno'] : 0);

		$errors = static::$errors;
		$e = (array_key_exists($user_errno, $errors) !== false) ? $errors[$user_errno] : array(0,'Неизвестная ошибка #'.$user_errno);
		$this->errno = $user_errno;

		$module			= (isset($data['module']) ? $data['module'] : $class_about['module']);
		$errstr			= (isset($data['errstr']) ? $data['errstr'] : $e[1]);
		$backtrace		= (isset($data['backtrace'])&&$data['backtrace']==true) ? ($user_errno < 100 ? true : false) : false;
		$file			= (isset($data['file']) ? $data['file'] : $class_about['file']);
		$line			= (isset($data['line']) ? $data['line'] : 0);
		$function		= (isset($data['function']) ? $data['function'] : 'undefined');
		$class			= (isset($data['class']) ? $data['class'] : get_called_class());
		$namespase		= (isset($data['namespase']) ? $data['namespase'] : self::$class_about['namespace']);
		$info			= (isset($data['info']) ? $data['info'] : '');
		$email			= (isset($data['email'])&&is_array($data['email'])) ? $data['email'] : false;
		$php_errno		= (isset($data['php_errno'])) ? $data['php_errno']: ($user_errno == 0 ? E_UNDEFINED : ($user_errno < 100 ? E_USER_ERROR : E_USER_WARNING));
		$log_file		= (isset($data['log_file']) ? DIR_LOGS.'/'.ltrim($data['log_file'], " .\r\n\t\\/").'.log' : DIR_LOGS.'/'.trim($module, " .\r\n\t\\/").'/'.trim($class, " .\r\n\t\\/").'.log');
		$nofile			= (isset($data['nofile'])&&$data['nofile']==true) ? true : false;

		#Массив сведений об ошибке
		$einfo = array(
				'log_file'		=> $log_file,
				'module'		=> $module,
				'php_errno'		=> $php_errno,
				'errno'			=> $user_errno,
				'errstr'		=> $errstr,
				'errfile'		=> $file,
				'errline'		=> $line,
				'namespase'		=> $namespase,
				'class'			=> $class,
				'function'		=> $function,
				'info'			=> $info,
				'backtrace'		=> $backtrace,
				'email'			=> $email,
				'nofile'		=> $nofile
		);

		#Отправка события в ядро
		Event::getInstance()->fireEvent($e[0], $einfo);

		#Обработка события
		#События PHP ошибок не обрабатываются классом, поскольку
		#их обработка ведется в классе Debug по сгенерированному событию
		if($e[0] != EVENT_PHP_ERROR) $this->classErrorHandler($e[0], $einfo);

		#Функция, вызываемая при возникновении ошибки
		$this->doErrorAction($e[0], $einfo);

		return false;
	}#end function



	/*
	#--------------------------------------------------
	#Обработка ошибок
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $event_name (*) - идентификатор события, вызвавшего функцию-обрабочик
	# $data (*) - массив данных об ошибке, имеет следующий вид:
	# Array(
	#	[php_errno] => 256	#Ошибка в стиле PHP (E_USER_ERROR, E_USER_WARNING)
	#	[errno] => 2	#Номер ошибки в классе
	#	[errstr] => Внутренняя ошибка: В таблице объектов доступа отсутствуют данные	#Описание ошибки
	#	[file] => W:\home\test\www\server\classes\Acl.class.php	#Файл, где возникла ошибка
	#	[line] => 547	#Строка
	#	[class] => Acl	#Наименование класса, в котором произошла ошибка
	#	[function] => dbLoadObjects	#Функция, в которой произошла ошибка
	#	[namespase] => home/test	#Имя области
	#	[info] => ''	#Дополнительная информация
	#	[backtrace] => true		#Признак, указывающий о необходимости записи в LOG файл данных Backtrace
	#	[email] => true		#Признак, указывающий о необходимости отправки сообщения на email
	# )
	#
	# Уровень обработки ошибок: 
	# 0 - Никак не реагировать на ошибки
	# 1 - Записывать в LOG файл только системные ошибки (код от 0 до 99).
	# 2 - Записывать в LOG файл все ошибки и предупреждения
	# 3 - Записывать в LOG файл все + выдавать сообщения о системных ошибках
	# 4 - Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях
	# 5 - Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях, при системных ошибках - завершать работу
	*/
	protected function classErrorHandler($event_name, $data){

		if($this->error_level > CORE_MAX_ERROR_LEVEL) $this->error_level = CORE_MAX_ERROR_LEVEL;

		#Проверяем уровень обработки ошибок в классе
		if($this->error_level == 0) return;

		#Записывать в LOG файл только системные ошибки (код от 0 до 99), иначе не обрабатываем
		if($this->error_level == 1 && $data['errno'] >= 100) return;

		$message = 
			$data['module']."\t".
			$data['class']."\t".
			$data['function']."\t".
			$data['errfile']."\t".
			$data['errline']."\t".
			$data['errno']."\t".
			$data['errstr']."\t".
			$data['info'];

		#Запись в LOG-файл
		Debug::writeLog(array(
			'log_file'		=> $data['log_file'],
			'message'		=> $message,
			'backtrace'		=> $data['backtrace'],
			'email'			=> $data['email'],
			'nofile'		=> $data['nofile']
		));

		#Записывать в LOG файл все + выдавать сообщения о системных ошибках, иначе не обрабатываем
		if($this->error_level < 3 || ($this->error_level == 3 && $data['errno'] >= 100)) return;

		#Вывод ошибки на экран
		echo 
		"<pre>\nCLASS INTERNAL ERROR:\n".
		"======================================================================\n".
		"Module   : ".$data['module']."\n".
		"Class    : ".$data['class']."\n".
		"Function : ".$data['function']."\n".
		"File     : ".$data['errfile']."\n".
		"Line     : ".$data['errline']."\n".
		"Error No : ".$data['errno']."\n".
		"Desc     : ".$data['errstr']."\n".
		"Info     : ".print_r($data['info'],true)."\n".
		"\nBACKTRACE:\n\n".Debug::getBacktrace().
		"======================================================================\n\n</pre>";

		# Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях, при системных ошибках - завершать работу, иначе не обрабатываем
		if($this->error_level < 5 || ($this->error_level == 5 && $data['errno'] >= 100)) return;

		#Завершаем работу скрипта
		die('EXIT');

	}#end function









}#end class

?>