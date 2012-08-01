<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс обрабоки полей форм
Версия	: 1.0.0/ALPHA
Дата	: 2012-05-11
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/



class Validator{

	use Core_Trait_BaseError;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		#Системные ошибки, от 1 до 99
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_PHP_ERROR, 'Вызов недопустимого метода или функции класса'),
		5	=> array(EVENT_FORM_ERROR, 'Ошибка добавления поля в массив полей формы для проверки')
	);


	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/Validator'
	);


	public $validate_errors	= array();	#Массив ошибок
	public $validate_fields	= array();	#Массив полей формы для проверки
	public $templates		= array();	#Массив шаблонов предопределенных полей
	private $restruct_files	= null;		#Реструктурированный массив $_FILES








	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	public function __construct($fields=null, $templates=null){

		#Если задан массив темплейтов
		$this->setTemplates($templates);

		#Если задан массив полей для проверки
		$this->setFields($fields);

	}#end function



	#--------------------------------------------------
	# Деструктор класса
	#--------------------------------------------------
	public function __destruct(){
		//
	}#end function









	/*==============================================================================================
	Функции работы с полями
	==============================================================================================*/



	#--------------------------------------------------
	# Задаем массив темплейтов полей
	#--------------------------------------------------
	public function setTemplates($templates=null){

		#Если задан массив темплейтов
		if(!empty($templates)){
			if(is_array($templates)) 
				$this->templates = $templates;
			else
				$this->templates = Config::getOptions($templates);
		}

		return true;
	}#end function



	#--------------------------------------------------
	# Задаем массив полей для проверки
	#--------------------------------------------------
	public function setFields($fields=null){

		if(!is_array($fields)) return false;
		$this->clearFields();

		foreach($fields as $field)
			if($this->addField($field) === false) 
				return $this->doErrorEvent(5, __FUNCTION__, __LINE__, var_export($field, true));

		return true;
	}#end function



	/*
	#--------------------------------------------------
	# Добавление поля в массив полей для проверки
	#--------------------------------------------------
	# Cтруктура передаваемого массива:
	# name - Название поля (для обработки ошибок)
	# value - значение элемента формы, которое будет проверяться, или одномерный массив значений
	# type - тип данных:
	#		text - текст,
	#		int - целое число,
	#		uint - целое положительное число,
	#		float - число с запятой,
	#		ufloat - положительное число с запятой,
	#		num - целое число + символы "_","-",
	#		email - адрес электронной почты,
	#		date - дата в формате dd.mm.YYYY,
	#		intlist - числовое перечисление (цифры тире и запятая)
	#		regex - проверка по регулярному выражению
	#		base64 - текст в формате Base64
	#		file - загруженный файл
	# required - признак, указывающий что поле не должно быть пустым (true или false), для поля типа file означает, что в массиве $_FILES должен быть хотя бы один файл
	# min 	- минимальное количество символов в поле (для типа text и email) 
	#		- минимально допустимое значение (для int float), 
	#		- для file минимальное количество файлов
	#		при значении 0 - игнорируется
	# max 	- максимальное количество символов в поле (для типа text и email)
	#		- максимально допустимое значение (для int float)
	#		- для file максимальное количество файлов
	#		при значении 0 - игнорируется
	# minlen - минимальное количество символов, для file - минимально допустимый размер файла
	# maxlen - максимальное количество символов, для file - максимально допустимый размер файла
	# exclude - значение поля не должно содержать следующий текст или число, если null - игнорируется - задается в виде массива (FALSE если проверяемое значение имеет хотя бы одно совпадение)
	# include - значение поля должно содержать следующий текст или число, если null - игнорируется - задается в виде массива (TRUE если проверяемое значение содержит все совпадения)
	# regex	- регулярное выражение, которому должно удовлетворять значение поля, используется если тип поля указан как type = [regex] 
	#		  или для типа [file] по регулярному выражению проверяется имя пользовательского файла из $_FILES, если null - игнорируется
	# filetype - используется для типа [file], значение $_FILES...['type'] должно содержать один из перечисленных типов, задается в виде массива, если null - игнорируется
	#			 пример 'filetype' => array('image/png', 'image/jpeg', 'image/tif') или 'filetype' => array('image/')
	*/
	public function addField($field=null){

		if(!is_array($field)) return false;


		#Если поле name не определно, считаем что используется поле из темплейта
		#В этом случае формат массива поля таков:
		#array($template_name, $validate_value), где:
		#$template_name - имя темплейта
		#$validate_value - проверяемое значение
		if(!isset($field['name'])){
			$name = $field[0];
			$value = $field[1];
			if(!isset($this->templates[$name])||!is_array($this->templates[$name])) return false;
			$field = $this->templates[$name];
			$field['name'] = $name;
			$field['value'] = $value;
		}

		#Значения по-умолчанию
		#Присвоение значений из массива $field в $defs
		$defs= array_merge(
			array(
				'name'=>'',
				'value'=>'',
				'type'=>'text',
				'required'=>false,
				'min'=>0,
				'max'=>0,
				'minlen'=>0,
				'maxlen'=>0,
				'exclude'=>null,
				'include'=>null,
				'regex'=>null,
				'filetype'=>null
			),
			$field
		);

		#Добавление поля в массив полей для проверки
		array_push($this->validate_fields, $defs);

		return true;
	}#end function



	#--------------------------------------------------
	# Очистка массива полей проверки
	#--------------------------------------------------
	public function clearFields(){

		unset($this->validate_fields);
		$this->validate_fields=array();

		return true;
	}#end function



	#--------------------------------------------------
	# Проверка, есть ли ошибки
	#--------------------------------------------------
	public function hasErrors(){
		return (bool)(count($this->validate_errors) > 0);
	}



	#--------------------------------------------------
	# Получение массива ошибок
	#--------------------------------------------------
	public function getErrors(){
		return $this->validate_errors;
	}#end function



	#--------------------------------------------------
	# Добавление ошибки в массив ошибок
	#--------------------------------------------------
	public function addError($name='', $desc='', $value='', $type='error'){

		array_push($this->validate_errors, array(
			'name'	=> $name,
			'text'	=> $desc,
			'value'	=> $value,
			'type'	=> $type
		));

		return false;
	}#end function



	#--------------------------------------------------
	# Очистка массива полей проверки
	#--------------------------------------------------
	public function clearErrors(){

		unset($this->validate_errors);
		$this->validate_errors=array();

		return true;
	}#end function



	#--------------------------------------------------
	# Проверка заданных полей
	#--------------------------------------------------
	public function validate(){

		#По-умолчанию считаем, что все поля формы заполнены корректно
		$result = true;

		#Обнуление массива ошибок
		$this->clearErrors();

		#Проверка каждого поля
		foreach ($this->validate_fields as $field){
			if(!$this->checkField($field)) $result = false;
		}#Проверка каждого поля

		return $result;
	}#end function



	#--------------------------------------------------
	# Проверка полученных файлов
	#--------------------------------------------------
	public function checkFileField($field=null){

		if(!isset($_FILES)||!is_array($field)||!isset($field['type'])||$field['type']!='file') 
			return $this->addError($field['name'],'Файл не был загружен на сервер');

		#Проверка и реструктуризация файла $_FILES
		if(is_null($this->restruct_files))
			$this->restruct_files = Request::_getRequestFiles();

		#Файл не найден, не загружен
		if(!isset($this->restruct_files[$field['name']])){
			if($field['required']==true) return $this->addError($field['name'],'Файл не был загружен на сервер');
			return true;
		}

		$files = $this->restruct_files[$field['name']];

		#Количество файлов
		$files_count = (is_array($files) ? count($files) : 0);
		if(!$files_count) 
			return $this->addError($field['name'],'Файл не был загружен на сервер');

		if(
			($field['min']!=0 && $files_count < $field['min'])||
			($field['max']!=0 && $files_count > $field['max'])
		){
			if($field['min']==0) return $this->addError($field['name'],'Количество загруженных файлов не должно превышать '.$field['max'], $files_count);
			else if($field['max']==0 ) return $this->addError($field['name'],'Количество загруженных файлов должно быть не менее '.$field['min'], $files_count);
			else return $this->addError($field['name'],'Количество загруженных файлов должно быть в диапазоне от '.$field['min'].' до '.$field['max'], $files_count);
		}


		#Проверка, полученных файлов
		foreach($files as $file){

			#Проверка размера файла
			if(($field['minlen']!=0 && $file['size'] < $field['minlen'])||
				($field['maxlen']!=0 && $file['size'] > $field['maxlen'])){
				if($field['minlen']==0) return $this->addError($field['name'],'Размер файла "'.$file['name'].'" не должен превышать '.$field['maxlen'].' байт', $file['size']);
				else if($field['maxlen']==0 ) return $this->addError($field['name'],'Размер файла "'.$file['name'].'" должен быть не менее '.$field['minlen'].' байт', $file['size']);
				else return $this->addError($field['name'],'Размер файла "'.$file['name'].'" должен быть в диапазоне от '.$field['minlen'].' до '.$field['maxlen'], $file['size']);
			}

			#Проверка имени файла
			if(!empty($field['regex']) && !$this->regexMatch($file['name'], $field['regex'])){
				return $this->addError($field['name'],'Имя файла "'.$file['name'].'" не соответствует заданному шаблону', $file['name']);
			}

			#Проверка типа файла
			#Проверка наличия совпадений (exclude - значение поля не должно содержать ни одного элемента массива)
			if(!empty($field['filetype'])&&is_array($field['filetype'])){
				if(self::isExcluded($value, $field['filetype'])){
					return $this->addError($field['name'],'Тип загруженного файла "'.$file['name'].'" не соответствует требуемому для обработки типу', $value);
				}
			}

		}#Проверка, полученных файлов


		return true;
	}#end function



	#--------------------------------------------------
	# Проверка поля
	#--------------------------------------------------
	public function checkField($field=null){

		if(empty($field)||!is_array($field)) return false;

		#Если тип поля - файл,
		#прверку файлов выполняем в отдельной функции
		if($field['type']=='file')
			return $this->checkFileField($field);

		$value = $field['value'];

		#Проверка, задано ли значение
		if($field['required'] && self::isEmpty($value)){
			return $this->addError($field['name'],'Данное поле не может быть пустым');
		}

		#Если переданное значение является массивом
		if(is_array($value)){
			$fld = $field;
			foreach($value as $item){
				$fld['value'] = $item;
				if( $this->checkField($fld) === false ) return false;
			}
			return true;
		}


		#Проверка по типам значения
		switch($field['type']){

			#Значение должно быть целым числом
			case 'int':
				if(!$this->isEmpty($value) && !$this->isInt($value)){
					return $this->addError($field['name'],'Данное поле должно содержать целое число', $value);
				}
			break;

			#Значение должно быть целым положительным числом
			case 'uint':
				if(!$this->isEmpty($value) && !$this->isUint($value)){
					return $this->addError($field['name'],'Данное поле должно содержать целое положительное число', $value);
				}
			break;

			#Значение должно быть перечислением целых чисел
			case 'intlist':
				$value = str_replace(' ','',$value);
				if(!$this->isEmpty($value) && !$this->isIntlist($value)){
					return $this->addError($field['name'],'Данное поле должно содержать перечисление целых чисел, разделенных запятой', $value);
				}
			break;

			#Значение должно быть числом
			case 'float':
				if(!$this->isEmpty($value) && !$this->isDecimal($value)){
					return $this->addError($field['name'],'Данное поле должно содержать число', $value);
				}
			break;

			#Значение должно быть положительным числом
			case 'ufloat':
				if(!$this->isEmpty($value) && !$this->isUdecimal($value)){
					return $this->addError($field['name'],'Данное поле должно содержать положительное число', $value);
				}
			break;

			#Значение должно быть в формате BASE64
			case 'base64':
				if(!$this->isEmpty($value) && !$this->isBase64($value)){
					return $this->addError($field['name'],'Данное поле должно содержать данные в формате BASE64', $value);
				}
			break;

			#Значение должно быть адресом электронной почты
			case 'email':
				if(!$this->isEmpty($value) && !$this->isEmail($value)){
					return $this->addError($field['name'],'Данное поле должно содержать корректный адрес электронной почты', $value);
				}
			break;

			#Значение должно быть датой
			case 'date':
				if(!$this->isEmpty($value) && !$this->isDate($value)){
					return $this->addError($field['name'],'Данное поле должно содержать корректную дату dd.mm.yyyy', $value);
				}
			break;

			#Значение должно удовлетворять заданному регулярному выражению
			case 'regex':
				if(!$this->isEmpty($value) && !empty($field['regex']) && !$this->regexMatch($value, $field['regex'])){
					return $this->addError($field['name'],'Введенное значение не соответствует заданному шаблону', $value);
				}
			break;

			#Все остальное - текст
			default:
				$field['type'] = 'text';
			break;

		}#Проверка по типам значения



		#Проверка лимитов значений
		switch($field['type']){

			#Проверка числовых значений
			case 'int':
			case 'uint':
			case 'float':
			case 'ufloat':
				if ((!self::isEmpty($value) && $field['min']!=0 && !self::greaterThan($value,$field['min']))||
					(!self::isEmpty($value) && $field['max']!=0 && !self::lessThan($value,$field['max']))){
					if($field['min']==0) return $this->addError($field['name'],'Указанное значение должно быть не более '.$field['max'], $value);
					else if($field['max']==0 ) return $this->addError($field['name'],'Указанное значение должно быть не менее '.$field['min'], $value);
					else return $this->addError($field['name'],'Указанное значение должно быть в диапазоне от '.$field['min'].' до '.$field['max'], $value);
				}
			break;

			#Проверка дат
			case 'date':
				if ((!self::isEmpty($value) && $field['min']!=0 && !self::greaterDate($value,$field['min'])) ||
					(!self::isEmpty($value) && $field['max']!=0 && !self::lessDate($value,$field['max']))){
					if($field['min']==0) return $this->addError($field['name'],'Указанная дата должна быть не более '.$field['max'], $value);
					else if($field['max']==0 ) return $this->addError($field['name'],'Указанная дата должна быть не менее '.$field['min'], $value);
					else return $this->addError($field['name'],'Указанная дата должна быть в диапазоне от '.$field['min'].' до '.$field['max'], $value);
				}
			break;

			#Проверка текстовых значений
			case 'text':
			case 'email':
			case 'base64':
				if ((!self::isEmpty($value) && $field['min']!=0 && !self::minLength($value,$field['min']))||
					(!self::isEmpty($value) && $field['max']!=0 && !self::maxLength($value,$field['max']))){
					if($field['min']==0) return $this->addError($field['name'],'Длинна строки должна быть не более '.$field['max'], $value);
					else if($field['max']==0 ) return $this->addError($field['name'],'Длинна строки должна быть не не менее '.$field['min'], $value);
					else return $this->addError($field['name'],'Длинна строки должна быть в диапазоне от '.$field['min'].' до '.$field['max'], $value);
				}
			break;

		}#Проверка лимитов значений

		#Проверка значения на количество символов
		if ((!self::isEmpty($value) && $field['minlen']!=0 && !self::minLength($value,$field['minlen']))||
			(!self::isEmpty($value) && $field['maxlen']!=0 && !self::maxLength($value,$field['maxlen']))){
			if($field['minlen']==0) return $this->addError($field['name'],'Длинна строки должна быть не более '.$field['maxlen'], $value);
			else if($field['maxlen']==0 ) return $this->addError($field['name'],'Длинна строки должна быть не не менее '.$field['minlen'], $value);
			else return $this->addError($field['name'],'Длинна строки должна быть в диапазоне от '.$field['minlen'].' до '.$field['maxlen'], $value);
		}

		#Проверка наличия совпадений (exclude - значение поля не должно содержать ни одного элемента массива)
		if(!is_null($field['exclude'])&&is_array($field['exclude'])){
			if(!self::isExcluded($value, $field['exclude'])){
				return $this->addError($field['name'],'Поле содержит одно из недопустимых значений: '.implode(', ',$field['exclude']), $value);
			}
		}


		#Проверка наличия совпадений (include - значение поля должно содержать все элементы массива)
		if(!is_null($field['include'])&&is_array($field['include'])){
			if(!self::isIncluded($value,$field['include'])){
				return $this->addError($field['name'],'Поле не содержит одно из требуемых значений: '.implode(', ',$field['include']), $value);
			}
		}


		return true;
	}#end function



	#--------------------------------------------------
	# Проверка, удовлетворяет ли значение заданному регулярному выражению
	#--------------------------------------------------
	static public function regexMatch($value, $regex){
		if(!preg_match($regex, $value)) return false;
		return true;
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение целым числом
	#--------------------------------------------------
	static public function isInt($value){
		return (bool) preg_match('/^[\-+]?[0-9]+$/', $value);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение целым положительным числом
	#--------------------------------------------------
	static public function isUint($value){
		return (bool) preg_match('/^[0-9]+$/', $value);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение перечислением целых чисел
	#--------------------------------------------------
	static public function isIntlist($value){
		return (bool) preg_match('/^[0-9\-\,]+$/', $value);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение числом
	#--------------------------------------------------
	static public function isDecimal($value){
		return (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $value);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение положительным числом
	#--------------------------------------------------
	static public function isUdecimal($value){
		return (bool) preg_match('/^[0-9]+\.[0-9]+$/', $value);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение больше чем указанный минимум
	#--------------------------------------------------
	static public function greaterThan($value, $min){
		if(!is_numeric($value)) return false;
		return (bool)($value >= $min);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение меньше чем указанный максимум
	#--------------------------------------------------
	static public function lessThan($value, $max){
		if(!is_numeric($value)) return false;
		return (bool)($value <= $max);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли дата больше чем указанный минимум
	#--------------------------------------------------
	static public function greaterDate($value, $min){
		if( ($value = strtotime($value)) === false) return false;
		if( ($min = strtotime($min)) === false) return false;
		return (bool)($value >= $min);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли дата меньше чем указанный максимум
	#--------------------------------------------------
	static public function lessDate($value, $max){
		if( ($value = strtotime($value)) === false) return false;
		if( ($max = strtotime($max)) === false) return false;
		return (bool)($value <= $max);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение текстом в формате BASE64
	#--------------------------------------------------
	static public function isBase64($value){
		return (bool) ! preg_match('/[^a-zA-Z0-9\/\+=]/', $value);
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение адресом электронной почты
	#--------------------------------------------------
	static public function isEmail($value){
		return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $value)) ? false : true;
	}#end function



	#--------------------------------------------------
	# Проверка, является ли значение датой
	#--------------------------------------------------
	static public function isDate($value){
		return (!preg_match("/^[0-9]{2}\.[0-9]{2}\.[0-9]{4}$/", $value)) ? false : true;
	}#end function



	#--------------------------------------------------
	# Проверка, является ли длинна значения больше чем указанный минимум
	#--------------------------------------------------
	static public function minLength($value, $min){
		return (strlen($value) < $min) ? false : true;
	}#end function



	#--------------------------------------------------
	# Проверка, является ли длинна значения меньше чем указанный максимум
	#--------------------------------------------------
	static public function maxLength($value, $max){
		return (strlen($value) > $max) ? false : true;
	}#end function



	#--------------------------------------------------
	# Проверка, задано ли значение
	#--------------------------------------------------
	static public function isEmpty($value){
		return empty($value);
	}#end function



	#--------------------------------------------------
	# Проверка присутствия в значении всех текстовых элементов из массива
	#--------------------------------------------------
	# False - если не найдены все совпадения
	static public function isIncluded($value, $arr=null){

		if(empty($arr))return true;
		if(!is_array($arr))return true;
		$matches = 0;
		foreach($arr as $item){
			if(stripos($value,$item)!==false)$matches++;
		}

		return ($matches != count($arr)) ? false : true;
	}#end function



	#--------------------------------------------------
	# Проверка отсутствия в значении всех текстовых элементов из массива
	#--------------------------------------------------
	# False - если найдено хотя бы одно совпадение
	static public function isExcluded($value, $arr=null){

		if(empty($arr))return true;
		if(!is_array($arr))return true;
		foreach($arr as $item){
			if(stripos($value,$item)!==false)return false;
		}

		return true;
	}#end function



}#end class



?>