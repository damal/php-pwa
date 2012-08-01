<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс работы с LOG файлами
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/





class LogReader{


	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	private $link_id		= null;					# Идентификатор файла
	private $fname			= '';					# Имя файла
	private $fpos			= -1;					# Позиция курсора в файле
	private $fstat			= array();				# Информация  файле
	private $fsize			= 0;					# Размер файла

	private $sess			= null;					# Сессия
	private $per_page		= 5;					# Количество записей на страницу



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
		'log_file'	=> 'Core/Database'
	);





	/*==============================================================================================
	Инициализация
	==============================================================================================*/




	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	public function __construct($file = null){

		$this->sess = Session::getInstance();
		$this->openFile($file);

	}#end function











	/*==============================================================================================
	Функции: работа с файлом
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



	#--------------------------------------------------
	# Возврат текущей позиции курсора
	#--------------------------------------------------
	public function getPos(){

		if(!$this->link_id) $this->fpos = 0;

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
		}

		return $this->fpos;
	}#end function



	#--------------------------------------------------
	# Перевод позиции курсора в конец файла
	#--------------------------------------------------
	public function toEnd(){

		if($this->link_id){
			fseek($this->link_id, 0, SEEK_END);
			$this->fpos = ftell($this->link_id);
			unset($stat);
		}

		return $this->fpos;
	}#end function



	#--------------------------------------------------
	# Перевод позиции курсора в начало файла
	#--------------------------------------------------
	public function toBegin(){

		if($this->link_id){
			$this->fpos = 0;
			fseek($this->link_id, 0, SEEK_SET);
		}

		return $this->fpos;
	}#end function



	#--------------------------------------------------
	# Открытие файла
	#--------------------------------------------------
	public function openFile($file = null){

		if(file_exists($file)){
			$this->fname = $file;
			$this->link_id = fopen($file, 'r'); 
			$this->fpos = -1;
			$this->getStat();
			return true;
		}

		return false;
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



	#--------------------------------------------------
	# Чтение символа
	#--------------------------------------------------
	public function readChar(){

		if($this->link_id){
			$data = fgetc($this->link_id);
			$this->fpos = ftell($this->link_id);
			return $data; 
		}

		return '';
	}#end function



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



	#--------------------------------------------------
	# Определение нахождения курсора на начале файла
	#--------------------------------------------------
	public function feofBack(){

			return $this->fpos != -1;

	}#end function




	#--------------------------------------------------
	# Чтение нескольких строк
	#--------------------------------------------------
	public function getList($count = 0, $from_pos = 0){

		if(!$this->link_id) return false;
		if($from_pos <=0) $this->toBegin(); else{
			$this->setPos($from_pos);
		}
		$result = array();
		$i = 0;
		while(!feof($this->link_id) && $i<$count){
			array_push($result, $this->getLine());
			$i++;
		}

		return $result;
	}#end function



	#--------------------------------------------------
	# Чтение нескольких строк в обратном порядке (с конца в начало) и возвращает их в виде массива
	#--------------------------------------------------
	public function getListBack($count = 0, $from_pos = 0){

		if(!$this->link_id) return false;
		if($from_pos==0) $this->toEnd(); else $this->setPos($from_pos);
		$result = array();
		$i = 0;
		while($this->fpos > 0 && $i<$count){
			array_push($result, $this->getLineBack());
			$i++;
		} 

		return $result;
	}#end function




	#--------------------------------------------------
	# Чтение нескольких строк в обратном порядке (с конца в начало) и возвращает их в виде массива
	#--------------------------------------------------
	public function getFirstRecords($count = 0){

		$result = $this->getListBack($count,0);
		if(is_array($result)) $this->setPosToSession();

		return $result;
	}#end function



	#--------------------------------------------------
	# Чтение нескольких строк в обратном порядке (с конца в начало) и возвращает их в виде массива
	#--------------------------------------------------
	public function getNextRecords($count = 0){

		$result = $this->getListBack($count,$this->getPosFromSession());
		$this->setPosToSession();

		return $result;
	}#end function



	#--------------------------------------------------
	# Чтение нескольких строк в обратном порядке (с конца в начало) и возвращает их в виде массива
	#--------------------------------------------------
	public function getPrevRecords($count = 0){

		$pos = $this->getPosFromSession();
		$result = $this->getList($count,($pos == 0 ? 0 : $pos + 2));
		$this->setPosToSession();

		return $result;
	}#end function










	/*==============================================================================================
	Функции: работа с сессией
	==============================================================================================*/



	#--------------------------------------------------
	# Запись в сессию
	#--------------------------------------------------
	public function setPosToSession(){

		Session::getInstance()->setMd(array('Logreader',$this->fname,'pos'), $this->fpos);

		return true;
	}#end function



	#--------------------------------------------------
	# Чтение из сессии
	#--------------------------------------------------
	public function getPosFromSession(){

		$pos = Session::getInstance()->getMd(array('Logreader',$this->fname,'pos'));
		$pos = ($pos === false) ? -1 : $pos;
		return $pos;
	}#end function




































}#end class




?>