<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс работы LOG файлами
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Авторы	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/



class LogFile{

	use Core_Trait_SingletonArray, Core_Trait_BaseError;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Настройки по-умолчанию для экземпляра класса
	private $options = array(
		'file'			=> null
	);



	private $link_id		= null;				# Идентификатор файла
	private $fname			= null;				# Имя файла
	private $fpos			= -1;				# Позиция курсора в файле
	private $fstat			= array();			# Информация о файле
	private $fsize			= 0;				# Размер файла



	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		#Системные ошибки, от 1 до 99
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_PHP_ERROR, 'Вызов недопустимого метода или функции класса'),
		5	=> array(EVENT_LOGFILE_ERROR, 'Невозможно создать/открыть LOG файл')
	);

	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/LogFile'
	);




	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	private function init($connection = 'core', $options = null){

		#Установка опций
		if (is_array($options))
			$this->options = array_merge($this->options, $options);

		#Открфтие файла
		if(!empty($this->options['file'])) $this->openFile($this->options['file']);

	}#end function



	#--------------------------------------------------
	# Деструктор класса
	#--------------------------------------------------
	public function __destruct() {

		$this->closeFile();

	}#end function
















	/*==============================================================================================
	Открытие / закрытие файла
	==============================================================================================*/



	#--------------------------------------------------
	# Проверяет наличие пути и
	#--------------------------------------------------
	private function checkPath($dir){
		$dir = dirname($dir);
		if(!is_dir($dir)) @mkdir($dir, 0777, true);
	}#end function



	#--------------------------------------------------
	# Открытие файла
	#--------------------------------------------------
	public function openFile($file = null){

		if(empty($file)) return false;
		if($this->link_id) $this->closeFile();

		$exists = (file_exists($file)) ? true : false;

		$this->checkPath($file);

		$this->link_id = @fopen($file, 'a+'); 
		if(!$this->link_id) return $this->doErrorEvent(5, __FUNCTION__, __LINE__, $file);
		if(!$exists) @chmod($file, 0777);

		$this->fname = $file;

		return true;
	}#end function




	#--------------------------------------------------
	# Закрытие файла
	#--------------------------------------------------
	public function closeFile(){

		if($this->link_id){
			fclose($this->link_id);
			$this->link_id = null;
		}

	}#end function









	/*==============================================================================================
	Информационные функции
	==============================================================================================*/



	/*
	#--------------------------------------------------
	# Получает информацию о файле используя открытый файловый указатель
	#--------------------------------------------------
	#
	# Результатом выполнения данного примера будет что-то подобное:
	# Array
	# (
	#	[dev] => 771
	#	[ino] => 488704
	#	[mode] => 33188
	#	[nlink] => 1
	#	[uid] => 0
	#	[gid] => 0
	#	[rdev] => 0
	#	[size] => 1114
	#	[atime] => 1061067181
	#	[mtime] => 1056136526
	#	[ctime] => 1056136526
	#	[blksize] => 4096
	#	[blocks] => 8
	# )
	*/
	public function getStat($what = null){

		if($this->link_id){
			clearstatcache();
			$this->fstat = fstat($this->link_id);
			$this->fsize = $this->fstat['size'];
		}

		return (empty($what)?$this->fstat:$this->fstat[$what]);
	}#end function







	/*==============================================================================================
	Позиция курсора в файле
	==============================================================================================*/


	#--------------------------------------------------
	# Возврат текущей позиции курсора
	#--------------------------------------------------
	public function getPos(){

		if(!$this->link_id) $this->fpos = -1;

		return $this->fpos;
	}#end function



	#--------------------------------------------------
	# Перевод позиции курсора в указанное место
	#--------------------------------------------------
	public function setPos($offset = 0){

		if($this->link_id){
			if($this->getStat('size') <= $offset) $offset = $this->fsize - 1;
			$this->fpos = ($offset >= 0 ? $offset : -1);
			fseek($this->link_id, ($offset >=0 ? $offset : 0), SEEK_SET);
			return $this->fpos;
		}

		return false;
	}#end function



	#--------------------------------------------------
	# Перевод позиции курсора в конец файла
	#--------------------------------------------------
	public function toEnd(){

		if($this->link_id){
			fseek($this->link_id, 0, SEEK_END);
			$this->fpos = ftell($this->link_id);
			return $this->fpos;
		}

		return false;
	}#end function



	#--------------------------------------------------
	# Перевод позиции курсора в начало файла
	#--------------------------------------------------
	public function toBegin(){

		if($this->link_id){
			$this->fpos = 0;
			fseek($this->link_id, 0, SEEK_SET);
			return $this->fpos;
		}

		return false;
	}#end function













	/*==============================================================================================
	Чтение из открытого LOG файла
	==============================================================================================*/


	#--------------------------------------------------
	# Чтение строки
	#--------------------------------------------------
	public function getLine($delim = "\n"){

		if($this->link_id){

			#Устанавливаем позицию курсора на начало файла
			if($this->fpos == -1) $this->toBegin();

			$len = 0;
			$data = '';
			fseek($this->link_id, $this->fpos);
			while(!feof($this->link_id)){
				$c = fgetc($this->link_id);
				if($c == $delim && $len != 0) break;
				if($c != "\r" && $c != "\n"){
					$data.= $c;
					$len++;
				}
				$this->fpos++;
			}

			return $data;
		}

		return '';
	}#end function




	#--------------------------------------------------
	# Чтение строки c конца файла до начала файла
	#--------------------------------------------------
	public function getLineBack($delim = "\n"){

		if($this->link_id){

			#Устанавливаем позицию курсора на конец файла
			if($this->fpos == -1) $this->toEnd();

			$len = 0;
			$data = '';
			for(; $this->fpos >= 0; $this->fpos--){
				fseek($this->link_id, $this->fpos);
				$c = fgetc($this->link_id); 
				if($c == $delim && $len != 0) break;
				if($c != "\r" && $c != "\n"){
					$data = $c.$data;
					$len++;
				}
			}

			return $data;
		}

		return '';
	}#end function




















	/*==============================================================================================
	Запись в открытый LOG файл
	==============================================================================================*/


	#--------------------------------------------------
	# Запись произвольной строки
	#--------------------------------------------------
	public function writeCustomLine($data=null, $delimiter="\t", $write_hash=true){

		if(!$this->link_id) return false;
		if(empty($data)) return false;
		if(($data = (is_array($data) ? $this->toString($data, $delimiter) : str_replace(array("\r","\n","\t"),' ',$data))) === false) return false;

		try{

			@flock ($this->link_id, LOCK_EX); #блокировка на запись
			$this->toEnd(); #Установка курсора в конец файла
			$is_begin = ($this->fpos == 0 ? true : false);
			$last_hash = (!$write_hash || $is_begin ? '' : $this->getLineHash());
			$this->toEnd(); #Установка курсора в конец файла

			#Вычисление контрольной суммы строки
			if($write_hash){
				$hash = Hash::getInstance()->getHash($last_hash.$data);
			}
			$line = ($is_begin ? '' : RN).  date('Y-m-d H:i:s', time()). "\t" . $data . ($write_hash == true ? "\t" . $hash : '');

			$result = @fwrite($this->link_id, $line); #запись в файл
			@fflush($this->link_id); # очищаем вывод перед отменой блокировки
			@flock ($this->link_id, LOCK_UN); #снятие блокировки

		}catch(Exception $e){
			#Ошибка записи в файл
			return false;
		}

		return $result;
	}#end function


















	/*==============================================================================================
	Вычисление Hash сумм
	==============================================================================================*/


	#--------------------------------------------------
	# Запись произвольной строки
	#--------------------------------------------------
	#
	# Функция возвращает Hash сумму последней строки,
	# если файл пустой, будет возвращена пустая строка
	#
	public function getLineHash($offset = -1){

		if(!$this->link_id) return false;
		if($offset == -1) $this->toEnd(); else $this->setPos($offset);
		$is_begin = ($this->fpos == 0 ? true : false);

		$len = 0;
		$data = '';
		for(; $this->fpos >= 0; $this->fpos--){
			fseek($this->link_id, $this->fpos);
			$c = fgetc($this->link_id); 
			if($c == "\t" && $len != 0) break;
			if($c != "\r" && $c != "\n" && $c != "\t"){
				$data = $c.$data;
				$len++;
				//if($len >= HASH_SIZE) break; //Контрольная сумма - 40 символов
			}
		}

			return $data;
	}#end function



















	/*==============================================================================================
	Вспомогательные функции
	==============================================================================================*/


	/*
	#--------------------------------------------------
	# Преобразует массив в строку
	#--------------------------------------------------
	#
	# Входные параметры:
	# $arr(*) - массив, который нужно преобразовать
	# $delimiter - разделитель элементов массива
	# $end_delimeter - признак, указывающий на установку разделителя в конце строки
	# 
	# Функция возвращает строку, вычисленную из массива $arr с разделителем $delimiter
	*/
	public function toString($arr = null, $delimiter="\t", $end_delimeter=false){

		if(empty($arr)||!is_array($arr)) return false;

		$count = 0;
		$result = '';
		foreach($arr as $value){
			if($count > 0) $result.=$delimiter;
			$result.= (is_array($value)? $this->toString($value,';', true) : str_replace(array("\r","\n","\t"),' ',$value));
			$count++;
		}

		if($count>0 && $end_delimeter!=false) $result.=$delimiter;

		return $result;
	}#end function












}#end class


?>