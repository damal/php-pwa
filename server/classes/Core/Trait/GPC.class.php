<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Работа с массивами $_GET $_POST $_COOKIE $_FILES
Версия	: 1.0.0/ALPHA
Дата	: 2012-05-11
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/



trait Core_Trait_GPC{


	//~ /*==============================================================================================
	//~ Функции $_FILES
	//~ ==============================================================================================*/
//~ 
//~ 
	//~ /*
	//~ #--------------------------------------------------
	//~ # Преобразование массива $_FILES 
	//~ #--------------------------------------------------
	//~ # Исходный формат $_FILES:
	//~ # Array (
	//~ #	[image] => Array(
	//~ #		[name] => Array([0] => 400.png)
	//~ #		[type] => Array([0] => image/png)
	//~ #		[tmp_name] => Array([0] => /tmp/php5Wx0aJ)
	//~ #		[error] => Array([0] => 0)
	//~ #		[size] => Array([0] => 15726)
	//~ #	)
	//~ # )
	//~ #
	//~ # Получаемый формат:
	//~ # Array(
	//~ #	[image] => Array(
	//~ #		[0] => Array(
	//~ #			[name] => 400.png
	//~ #			[type] => image/png
	//~ #			[tmp_name] => /tmp/php5Wx0aJ
	//~ #			[error] => 0
	//~ #			[size] => 15726
	//~ #		)
	//~ #	)
	//~ # )
	//~ */
	//~ 
	//~ public static function restructFilesArray(array $_files, $top=true){
//~ 
		//~ $files = array();
		//~ foreach($_files as $name => $file){
			//~ $sub_name = ($top) ? $file['name'] : $name;
//~ 
			//~ if(is_array($sub_name)){
//~ 
				//~ foreach(array_keys($sub_name) as $key){
					//~ $files[$name][$key] = array(
						//~ 'name'	 => $file['name'][$key],
						//~ 'type'	 => $file['type'][$key],
						//~ 'tmp_name' => $file['tmp_name'][$key],
						//~ 'error'	=> $file['error'][$key],
						//~ 'size'	 => $file['size'][$key],
					//~ );
					//~ $files[$name] = multiple($files[$name], false);
				//~ }
//~ 
			//~ }else{
				//~ $files[$name] = $file;
			//~ }
		//~ }
//~ 
		//~ return $files;
	//~ }#end function

	#--------------------------------------------------
	# Получение $_GET, $_POST, $_COOKIE
	#--------------------------------------------------
	public static function getVar($key, $type = array('post', 'get', 'cookie', 'files')) {
		if ($g_key = self::hasVar($key, $type))
			return $GLOBALS[$g_key][$key];
	}
	
	#--------------------------------------------------
	# Проверка на наличие элемента в $_GET, $_POST, $_COOKIE
	#--------------------------------------------------
	public static function hasVar($key, $type = array('post', 'get')) {
		if (!is_array($type))
			$type = array($type);
		foreach ($type as $v) {
			$g_key = '_'.ltrim(strtoupper($v, '_'));
			if (array_key_exists($key, $GLOBALS[$g_key]))
				return $g_key;
		}
		return false;
	}





























































}#end class



?>