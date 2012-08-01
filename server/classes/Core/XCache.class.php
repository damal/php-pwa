<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс работы с XCache (http://xcache.lighttpd.net/)
Версия	: 1.0.0/ALPHA
Дата	: 2012-06-21
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/

class XCache{

	use Core_Trait_SingletonUnique;



	/*==============================================================================================
	Переменные класса
	==============================================================================================*/


	private $active = false;





	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	private function init(){

		#Следует ли использовать XCache
		$this->active = (function_exists('xcache_get')) ? Config::getOption('Core/main','php_xcache',false) : false;

	}#end function



	#--------------------------------------------------
	# Запись данных в недоступные свойства
	#--------------------------------------------------
	public function __set($name, $value){
		if(!$this->active) return false;
		xcache_set($name, $value, 0);
	}#end function



	#--------------------------------------------------
	# Чтение данных из недоступных свойств
	#--------------------------------------------------
	public function __get($name){
		if(!$this->active) return false;
		return xcache_get($name);
	}#end function



	#--------------------------------------------------
	# будет выполнен при использовании isset() или empty() на недоступных свойствах.
	#--------------------------------------------------
	public function __isset($name){
		if(!$this->active) return false;
		return xcache_isset($name);
	}#end function



	#--------------------------------------------------
	# будет выполнен при вызове unset() на недоступном свойстве
	#--------------------------------------------------
	public function __unset($name){
		if(!$this->active) return false;
		xcache_unset($name);
	}#end function





	/*==============================================================================================
	Функции
	==============================================================================================*/


	public function isEnabled(){
		return $this->active;
	}#end function


	public function set($key, $value, $ttl=0){
		if(!$this->active) return false;
		return xcache_set($key, $value, $ttl);
	}#end function



	public function get($key){
		if(!$this->active) return false;
		return xcache_get($key);
	}#end function



	public function delete($key){
		if(!$this->active) return false;
		return xcache_unset($key);
	}#end function



	public function exists($key){
		if(!$this->active) return false;
		return xcache_isset($key);
	}#end function


	#--------------------------------------------------
	# Загрузка контента из кеша или из файла
	#--------------------------------------------------
	public function getFileContent($file=''){

		if(empty($file)) return false;
		if(!$this->active) return (!is_file($file)) ? false : file_get_contents($file);

		if(xcache_isset($file)){
			if(xcache_isset($file.'/actual')&&xcache_get($file.'/actual')==true) return xcache_get($file);
		}

		if(!is_file($file)||!is_readable($file)) return false;

		$data = file_get_contents($file);
		xcache_set($file, $data);
		xcache_set($file.'/actual', true);

		return $data;
	}#end function


}#end class

?>