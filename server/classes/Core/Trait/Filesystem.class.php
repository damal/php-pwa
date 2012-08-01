<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Работа с файловой системой
Версия	: 1.0.0/ALPHA
Дата	: 2012-05-10
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/



trait Core_Trait_Filesystem{


	/*==============================================================================================
	Функции
	==============================================================================================*/


	#--------------------------------------------------
	# Изменение CHMOD для файла или директории
	#--------------------------------------------------
	public function chmod($files, $mode, $umask = 0000){

		if(empty($files)) return false;
		if(!is_array($files)) $files = array($files);
		foreach ($files as $file) chmod($file, $mode & ~$umask);

		return true;
	}#end function



	#--------------------------------------------------
	# Создание директории
	#--------------------------------------------------
	public function mkdir($dirs, $mode = 0777){

		$ret = true;
		if(empty($dirs)) return false;
		if(!is_array($dirs)) $dirs = array($dirs);

		foreach($dirs as $dir){
			if (is_dir($dir))continue;
			$ret = @mkdir($dir, $mode, true) && $ret;
		}

		return $ret;
	}#end function



	#--------------------------------------------------
	# Отображает размер файла: 342.23 Mb, 23.33 Gb
	#--------------------------------------------------
	public function displayFilesize($filesize){

		if(is_numeric($filesize)){

			$decr = 1024; $step = 0;
			$prefix = array('Byte','KB','MB','GB','TB','PB');

			while(($filesize / $decr) > 0.9){
				$filesize = $filesize / $decr;
				$step++;
			} 

			return round($filesize,2).' '.$prefix[$step];

		}

		return 'NaN';
	}#end function


}#end class


?>