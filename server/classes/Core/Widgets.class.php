<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Контроллер виджетов
Версия	: 1.0.0/ALPHA
Дата	: 2012-06-08
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/



class Widgets{

	use Core_Trait_SingletonUnique;

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
	);

	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/Widgets'
	);



	#переменные класса
	private $module		= null;
	private $page		= null;
	private $action		= null;
	private $method		= null;
	private $ajax		= null;
	private $dir		= null;
	private $pathlist	= null;
	private $path		= null;
	private $chunk		= null;
	private $event_name	= null;




	/*==============================================================================================
	Инициализация
	==============================================================================================*/

	#--------------------------------------------------
	# Старт, алиас для getInstance
	#--------------------------------------------------
	public static function start($options=null){ 
		return Widgets::getInstance($options);
	}#end function


	/*
	 * Конструктор класса
	 */
	protected function init($options=null){

		Event::_addListener(
			EVENT_PAGE_GET_WIDGETS,
			array(
				$this,
				'getWidgets'
			)
		);

	}#end function







	/*==============================================================================================
	Функции контроллера - обработка страниц
	==============================================================================================*/



	/*
	 * Обработка запрашиваемой страницы: для не аутентифицированных клиентов
	 */
	public function getWidgets($event_name=null, $data=null){

		if(empty($data)||!isset($data['wgt_white'])||!isset($data['wgt_black'])){
			$white = null;
			$black = null;
		}else{
			$white = $data['wgt_white'];
			$black = $data['wgt_black'];
		}
		$use_white = (!empty($white)&&is_array($white));
		$use_black = (!empty($black)&&is_array($black));

		#Определение, какому модулю адресован запрос
		$this->module = Request::_getRequestInfo('imodule');

		$this->event_name	= $event_name;
		$this->method		= Request::_getRequestInfo('method');
		$this->ajax			= Request::_getRequestInfo('ajax');
		$this->pathlist		= Request::_getRequestInfo('ipathlist');
		$dir = Request::_getRequestInfo('idir');
		$this->dir			= (empty($dir)?'':'/'.implode('/',$dir));
		$this->page			= Request::_getRequestInfo('ipage');
		$this->action		= Request::_getRequestInfo('action');
		$this->path			= '/'.implode('/',$this->pathlist);

		#Создаем объект чанка,
		$this->chunk = new Chunk();

		#Поскольку запрос явно к модулю Core, то устанавливаем чанку максимальный приоритет
		$this->chunk->setPriority(CHUNK_PRIORITY_WIDGET);

		$widgets = Config::getOptions('widgets'); 

		#Подключение виджетов
		foreach($widgets as $widget=>$winf){

			if($winf['active'] == false) continue;
			$use_widget_black = (!empty($winf['black'])&&is_array($winf['black'])) ? true : false;
			$use_widget_white = (!empty($winf['white'])&&is_array($winf['white'])) ? true : false;
			if ($use_widget_black && in_array($this->path, $winf['black'])) continue;
			if ($use_widget_white && !in_array($this->path, $winf['white'])) continue;
			if ($use_black && in_array($widget, $black)) continue;
			if ($use_white && !in_array($widget, $white)) continue;
			if(!empty($winf['acl_object'])){
				if(!Acl::_userAccess($winf['acl_object'])) continue;
			}

			$WIDGET_FILE = DIR_MODULES.'/'.$winf['file'];
			if(file_exists($WIDGET_FILE)&&is_readable($WIDGET_FILE)) include($WIDGET_FILE);

		}#Подключение виджетов

		return $this->chunk->build();
	}#end function






}#end class



?>