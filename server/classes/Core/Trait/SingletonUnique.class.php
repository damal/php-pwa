<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: паттерн Singleton (одиночка)
Версия	: 1.0.0/ALPHA
Дата	: 2012-05-10
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/

if(!defined('DIR_ROOT')) die('Direct access not allowed!');

trait Core_Trait_SingletonUnique{


	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Массив объектов классов
	protected static $_instance = null;





	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	final private function __construct(){}



	#--------------------------------------------------
	# Клонирование объекта
	#--------------------------------------------------
	final private function __clone(){}



	#--------------------------------------------------
	# Конструктор класса для дочерних классов
	#--------------------------------------------------
	#
	# В дочернем классе функция должна быть описана следующим образом:
	# protected function _init(...){...}
	protected function init(){}




	#--------------------------------------------------
	# будет выполнен при вызове недоступного статичного метода
	#--------------------------------------------------
	public static function __callStatic($method, $args){
		return call_user_func_array(array(self::getInstance(), ltrim($method,'_')), $args);
	}#end function




	/*==============================================================================================
	Функции
	==============================================================================================*/




	#--------------------------------------------------
	# Создание экземпляра класса
	#--------------------------------------------------
	final static public function getInstance(){

		if(!is_null(self::$_instance)) return self::$_instance;

		$args	= func_get_args();
		$class	= get_called_class();

		self::$_instance = new $class();

		#Вызов функции init для вновь созданного класса
		#Функция __construct была намерянно отключена и не может быть использована в дочерних классах
		call_user_func_array(
			array(
				self::$_instance,
				'init'
			),
			$args
		);

		return self::$_instance;
	}#end function



	#--------------------------------------------------
	# Возвращает массив $_instances
	#--------------------------------------------------
	final static public function getAllInstances(){
		return self::$_instance;
	}#end function


}#end class


?>