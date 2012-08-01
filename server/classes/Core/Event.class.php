<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс обработки событий
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/


/*
Функции класса:

Информационные функции:
#--------------------------------------------------
appVersion - Возвращает текущую версию ядра

Механизм обработки событий функций:
#--------------------------------------------------
addListener - Регистрация подписчика на событие (добавление функции-обработчика для события)
fireEvent - Создание события и вызов функций-обработчиков, возвращает результаты работы функций-обработчиков
fireEventNoResult - Создание события и вызов функций-обработчиков, без возврата результатов работы функций-обработчиков
exec - Запуск произвольной функции через механизм ядра


Обработчики ошибок и завершения работы скрипта:
#--------------------------------------------------
errorHandler - обработчик ошибок
exceptionHandler - обрабочик исключений
shutdownHandler - обработчик завершения работы скрипта

*/





class Event{

	use Core_Trait_SingletonUnique;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Ассоциированный массив подписчиков на события
	private $ev_list = array();


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
		'log_file'	=> 'Core/Event'
	);




	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	private function init(){

		#Установка обработчика ошибок
		@set_error_handler(array($this, 'errorHandler'), E_ALL);

		#Установка обработчика завершения работы скрипта
		@set_exception_handler(array($this, 'exceptionHandler'));

		#Установка обработчика завершения работы скрипта
		@register_shutdown_function(array($this, 'shutdownHandler'));

	}#end function









	/*==============================================================================================
	Механизм обработки событий функций
	==============================================================================================*/



	#-------------------------------------------------
	# Возвращает массив событий
	#--------------------------------------------------
	public function eventMap(){
		return $this->ev_list;
	}#end function



	/*
	#-------------------------------------------------
	# Регистрация подписчика на событие (добавление функции-обработчика для события)
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $event_name(*) - идентификатор события или массив идентификаторов событий, 
	#                  произвольное текстовое значение (например: "create_client","boom" и т.д.)
	# $call_function(*) - название вызываемой функции или непосредственно сама функция-обработчик события.
	#
	# Возвращает:
	# Ссылку на экземпляр класса - $this
	#
	# Примеры вызова:
	# 1) $this->addListener('boom',function($data){print_r($data);});
	# 2) $this->addListener('boom','functBoom')->addListener('test','functTest')->addListener('xxx','functTest');
	# 3) $this->addListener(array('boom','test','event'),function($data){print_r($data);});
	*/
	public function addListener($event_name, $call_function){
		if(empty($event_name)) return $this;
		if(!is_array($event_name)){
			$event_name = 'e'.$event_name;
			return $this->addListener_($event_name, $call_function);
		}
		foreach($event_name as $name){
			$name = 'e'.$name;
			$this->addListener_($name, $call_function);
		}
		return $this;
	}#end function



	#-------------------------------------------------
	# Регистрация подписчика, внутренняя
	#--------------------------------------------------
	private function addListener_($event_name, $call_function){
		if(!isset($this->ev_list[$event_name])||!is_array($this->ev_list[$event_name])) $this->ev_list[$event_name] = array();
		array_push($this->ev_list[$event_name], $call_function);
		return $this;
	}#end function



	/*
	#-------------------------------------------------
	# Удаление подписчика из события (удаление функции-обработчика для события)
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $event_name(*) - название события, произвольное текстовое значение (например: "create_client","boom" и т.д.)
	# $call_function(*) - название вызываемой функции или непосредственно сама функция-обработчик события, 
	# которую требуется удалить из очереди подписчиков на событие
	#
	# Возвращает:
	# Ссылку на экземпляр класса - $this
	#
	# Примеры вызова:
	# 1) $this->removeListener('boom',function($data){print_r($data);});
	# 2) $this->removeListener('boom','functBoom')->removeListener('test','functTest')->removeListener('xxx','functTest');
	*/
	public function removeListener($event_name, $call_function){
		$event_name = 'e'.$event_name;
		if(isset($this->ev_list[$event_name])&&is_array($this->ev_list[$event_name])){
			if( ($index = array_search($call_function, $this->ev_list[$event_name], true)) !== false){
				array_splice($this->ev_list[$event_name], $index, 1);
			}
		}
		return $this;
	}#end function



	/*
	#-------------------------------------------------
	# Удаление всех подписчиков из события (очистка события)
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $event_name(*) - название события, произвольное текстовое значение (например: "create_client","boom" и т.д.)
	#
	# Возвращает:
	# Ссылку на экземпляр класса - $this
	#
	# Примеры вызова:
	# 1) $this->clearEvent('boom');
	*/
	public function clearEvent($event_name){
		$event_name = 'e'.$event_name;
		if(isset($this->ev_list[$event_name])&&is_array($this->ev_list[$event_name])){
			unset($this->ev_list[$event_name]);
			$this->ev_list[$event_name] = array();
		}
		return $this;
	}#end function



	/*
	#--------------------------------------------------
	# Создание события и вызов функций-обработчиков
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $event_name(*) - название события, произвольное текстовое значение (например: "create_client","boom" и т.д.)
	# $args(*) - переменная, передаваемая в каждую функцию-обработчик (строка, массив, объект, ресурс, что угодно)
	#
	# Функция находит в массиве событий ev_list событие с именем $event_name и по-очереди запускает
	# функции-обработчики события.
	# В момент вызова, функции-обрабочику передается ассоциированный массив, состоящий из следующих элементов:
	# 'app' - ссылка на экземпляр текущего класса Core - $this
	# 'args' - какой-то передаваемый в функцию аргумент (строка, массив, объект, ресурс, что угодно)
	# 'event' - название события, по которому была вызвана функция-обработчик, передается $event_name,
	# для определения по какому событию была вызвана функция, т.к. функция может быть подписана на несколько событий
	#
	# Возвращает:
	# Массив результатов, каждый элементо которого представляет собой ассоциированный массив,
	# состоящий из двух элементов: 
	# 'funct' - имя функции-обработчика, если функция анонимная (лямбда-функция), то возвращается значение "object"
	#           если функция представляет собой метод класса, то возвращается значение в формате [имя класса]->[имя метода] (пример: "Core->appVersion")
	# 'data' - результат работы функции-обработчика
	#
	# Примеры вызова:
	# 1) $result = $this->fireEvent('boom', array('xxx'=>23,'yyy'=>233,'desc'=>"Description"));
	#
	# Примеры результата:
	# Array(
	# 	Array(
	# 		'funct'=>'functTest',
	# 		'data'=>array(12,23,44,555,666,77,2342) //Результат работы функции functTest - массив чисел
	# 	),
	# 	Array(
	# 		'funct'=>'functBoom',
	# 		'data'=>"Boom!Boom!Boom!"  //Результат работы функции functBoom - текстовая строка
	# 	)
	# );
	*/
	public function fireEventWithResult($event_name=''){

		if(empty($event_name)) return array();
		$event_name = 'e'.$event_name;

		#Массив результатаов
		$result = array();

		if(isset($this->ev_list[$event_name]) && is_array($this->ev_list[$event_name])){

			#Массив значений, переданных в функцию
			$args	= func_get_args();

			foreach($this->ev_list[$event_name] as $call_function){

				#Функция существует и найдена
				if(is_callable($call_function)){
					array_push(
						$result, 
						array(
							'funct'=>(is_array($call_function) ? get_class($call_function[0]).'->'.$call_function[1] : (is_object($call_function)?'object':$call_function)),
							'data'=>call_user_func_array($call_function, $args)
						)
					);
				}

			}#foreach

		}#isset($this->ev_list[$event_name])

		return $result;
	}#end function



	/*
	#--------------------------------------------------
	# Создание события и вызов функций-обработчиков, без результатов
	#--------------------------------------------------
	#
	# Данная функция является аналогом функции fireEventWithResult, за исключением того,
	# что не возвращаются результаты работы функций-обработчиков события
	*/
	public function fireEvent($event_name=''){

		if(empty($event_name)) return false;
		$event_name = 'e'.$event_name;

		#Обработчики события найдены
		if(isset($this->ev_list[$event_name]) && is_array($this->ev_list[$event_name])){

			#Массив значений, переданных в функцию
			$args	= func_get_args();

			foreach($this->ev_list[$event_name] as $call_function){

				#Функция существует и найдена
				if(is_callable($call_function)) call_user_func_array($call_function, $args);

			}#foreach

		}#Обработчики события найдены

		return $this;
	}#end function












	/*==============================================================================================
	Обработчики ошибок и завершения работы скрипта
	==============================================================================================*/



	#--------------------------------------------------
	# Обработка ошибок
	#--------------------------------------------------
	public function errorHandler($errno, $errstr, $errfile, $errline){

		$this->fireEvent(
			EVENT_PHP_ERROR,
			array(
				'errno'=>$errno,
				'errstr'=>$errstr,
				'errfile'=>$errfile,
				'errline'=>$errline
			)
		);

		return true;
	}#end function



	#--------------------------------------------------
	# Обработка исключений
	#--------------------------------------------------
	public function exceptionHandler($exception){ 
		$this->errorHandler(
			$exception->getCode(),
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine()
		);
		return true;
	}#end function



	#--------------------------------------------------
	# Обработка завершения работы скрипта
	#--------------------------------------------------
	public function shutdownHandler(){ 
		$error = error_get_last();
		if ($error && (
			$error['type'] == E_ERROR || 
			$error['type'] == E_PARSE || 
			$error['type'] == E_COMPILE_ERROR
			)
		){
			$this->errorHandler(
				$error['type'], 
				$error['message'], 
				$error['file'], 
				$error['line']
			);
		}
		return true;
	}#end function


}#end class



?>