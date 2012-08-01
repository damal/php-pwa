<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс работы с картой сайта
Версия	: 1.0.0/ALPHA
Дата	: 2012-06-13
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/











class Map{

	use Core_Trait_SingletonUnique, Core_Trait_BaseError, Core_Trait_Compare;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/





	#Настройки и значения по-умолчанию для работы класса
	protected $options = array(
		'db_link'	=> 'main'		#(*) Текстовое наименование соединения с базой данных
	);



	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		#Системные ошибки, от 1 до 99
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_MAP_ERROR, 'Внутренняя ошибка: Ошибка во время выполнения SQL запроса')
	);



	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/Map'
	);

	#Пустой элемент карты по-умолчанию
	private $def_item = array(
		'id' 			=> null,# Идентификатор элемента, здась допускается использование идентификаторов от 1 до 999
								# Идентификаторы в базе данных начинаются с 1000
		'parent_id'		=> 0,	# Идентификатор родительского элемента, если 0 - то этот элемент верхнего уровня
		'path'			=> '',	# Внешний путь к документу (для строки браузера)
		'object_id'		=> 0,	# Идентификатор объекта ACL типа страница, привязанный к данному элементу, если 0 - страница никогда не  отобразится.
		'php_code'		=> '',	# Путь к PHP файлу страницы относительно папки DIR_MODULES, если не задан - не используется
		'html_template'	=> '',	# Путь к HTML файлу темплейта страницы относительно папки DIR_MODULES, если не задан - используется корневой темплейт.
		'title'			=> '',	# Заголовок страницы
		'desc'			=> '',	# Описание страницы для dashboard или подсказки в toolbar
		'icon_32'		=> '',	# Ссылка на файл иконки страницы относительно DIR_CLIENT (размер 32х32)
		'icon_16'		=> '',	# Ссылка на файл иконки страницы относительно DIR_CLIENT (размер 16х16)
		'in_menu'		=> 0,	# 1 - отображать страницу в меню, 0 - не отображать
		'in_dashboard'	=> 0,	# 1 - отображать страницу в dashboard, 0 - не отображать
		'in_toolbar'	=> 0	# 1 - отображать страницу в toolbar, 0 - не отображать
	);


	private $db 				= null;		#Идентификатор соединения с базой данных
	private $map_loaded			= false;	#Признак, указывающий на то, загружена ли карта из БД в массив
	private $map_db				= array();	#Часть массива карты, полученная из базы данных
	private $map				= array();	#Собранный массив карты, состоит из map_db и дополнительные опции, добавленные классом








	/*==============================================================================================
	Инициализация
	==============================================================================================*/



	/*
	 * Конструктор класса
	 */
	protected function init($options=null){

		#Установка опций
		if(is_array($options)) $this->options = array_merge($this->options, $options);

		#Объект доступа к базе данных
		$this->db = Database::getInstance($this->options['db_link']);

	}#end function












	/*==============================================================================================
	Информационные функции
	==============================================================================================*/


	/* Функция, вызываемая в классе при возникновении ошибки (вызове функции doErrorEvent)
	 * 
	 * $event_name - передается идентификатор события
	 * $data - информация об ошибке
	 */
	protected function doErrorAction($event_name, $data){
		
	}#end function














	/*==============================================================================================
	Функции работы с базой данных: Загрузка карты приложения в массив
	==============================================================================================*/



	/*
	 * Загрузка массива карты из базы данных
	 * 
	 * $reload - признак, указывающий на необходимость перезагрузить данные из базы данных, даже если они уже были загружены
	 */
	public function dbLoadMap($reload=false){

		if(!$this->db->correct_init) return $this->doErrorEvent(6, __FUNCTION__, __LINE__);

		#Чтение в массив карты
		if(!$this->map_loaded || $reload==true){
			if(!$this->dbReloadMap()) return false;
		}

		return true;
	}#end function



	/*
	 * Получение массива из базы данных
	 */
	private function dbReloadMap(){

		$this->db->connect();

		$this->db->prepare('SELECT * FROM ?');
		$this->db->bind('map', null, BIND_FIELD);

		if( ($this->map_db = $this->db->select()) === false){
			return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
		}



		$this->map = array();
		$acl = Acl::getInstance();

		#Добавляем в массив карты данные из БД
		foreach($this->map_db as $item){
			$id = $item['id'];
			$this->map['m'.$id] = $item;
			$this->map['m'.$id]['childs'] = array(); #Массив дочерних элементов
			$this->map['m'.$id]['al'] = false; #Auto-Lock
			$this->map['m'.$id]['user'] = true; #Признак, что данный элемент доступен текущему пользователю, FALSE определяется дальше по листингу
		}

		#Строим дерево, а также проверяем имеет ли пользователь доступ к странице меню
		foreach($this->map as $key=>$item){
			$id = 'm'.$item['parent_id'];
			#Если родительский элемент существует
			if(isset($this->map[$id])){

				#Добавляем в родителя ID дочернего объекта
				$this->map[$id]['childs'][] = $item['id'];

				$id = 'm'.$item['id'];

				#Признак, что данный элемент доступен текущему пользователю
				if($this->map[$id]['object_id'] != 0) $this->map[$id]['user'] = $acl->userAccess($this->map[$id]['object_id']);

			}#Родительский элемент существует

		}

		#Рекурсивно вычисляем доступные юзеру элементы: 
		#если родитель заблокирован, 
		#дочерний элемент также должен быть заблокирован
		foreach($this->map as $key=>$item){
			if(!$item['user'] && !$item['al'] && !empty($item['childs'])) $this->itemLock($key);
		}

		#Данные по группам доступа загружены
		$this->map_loaded = true;

		return true;
	}#end function












	/*==============================================================================================
	Работа с массивом карты
	==============================================================================================*/



	/*
	 * Блокировка элемента карты с указанным идентификатором и всех его дочерних элементов,
	 * Функция работает рекурсивно
	 * Функция пишет линейный массив элементов заблокированной ветки в $result
	 */
	private function itemLock($item_id=0, &$result=null){

		$id = (is_numeric($item_id)) ? 'm'.$item_id : $item_id;

		if(isset($this->map[$id])){
			$this->map[$id]['user'] = false;
			$this->map[$id]['al'] = true;
			if($result) $result[] = $item_id;
			if(!empty($this->map[$id]['childs'])){
				foreach($this->map[$id]['childs'] as $item){
					$this->itemLock($item, $result);
				}
			}
		}

	}#end function



	/*
	 * Возвращает массив элементов карты, удовлетворяющий условиям заданного фильтра $filter
	 * Допустимые поля фильтра:
	 * array(
	 * 		'parent_id'		=> # Идентификатор родительского элемента: число или массив чисел, точное совпадение
	 * 		'path'			=> # Внешний путь к документу (для строки браузера): строка или массив строк, содержит
	 * 		'object_id'		=> # Идентификатор объекта ACL типа страница: число или массив чисел, точное совпадение
	 * 		'php_code'		=> # Путь к PHP файлу страницы относительно папки DIR_MODULES: строка или массив строк, содержит
	 * 		'html_template'	=> # Путь к HTML файлу темплейта страницы относительно папки DIR_MODULES: строка или массив строк, содержит
	 * 		'title'			=> # Заголовок страницы для тега TITLE: строка или массив строк, содержит
	 * 		'desc'			=> # Описание страницы для dashboard или подсказки в toolbar: строка или массив строк, содержит
	 * 		'icon_32'		=> # Ссылка на файл иконки страницы относительно DIR_CLIENT (размер 32х32): строка или массив строк, содержит
	 * 		'icon_16'		=> # Ссылка на файл иконки страницы относительно DIR_CLIENT (размер 16х16): строка или массив строк, содержит
	 * 		'in_menu'		=> # 1 - отображать страницу в меню, 0 - не отображать: булево значение TRUE/FALSE или 1/0
	 * 		'in_dashboard'	=> # 1 - отображать страницу в dashboard, 0 - не отображать: булево значение TRUE/FALSE или 1/0
	 * 		'in_toolbar'	=> # 1 - отображать страницу в toolbar, 0 - не отображать: булево значение TRUE/FALSE или 1/0
	 * 		'extra'			=> # Дополнительные данные: строка или массив строк, содержит
	 * 		'user'			=> # Выборка с учетом прав доступа текущего пользователя: булево значение TRUE/FALSE или 1/0
	 * 		'childs'		=> # Выборка с учетом наличия дочерних элементов: булево значение TRUE/FALSE или 1/0
	 * );
	 * 
	 *  $fields - массив возвращаемых полей, может быть задан ассоциированным массивом, тогда ключ ($key) - это имя поля в Map, а значение ($value) - это возвращаемое поле
	 */
	public function getMap($filter=array(), $fields=array()){

		#Чтение в массив доступных объектов
		if(!$this->map_loaded){
			if(!$this->dbLoadMap()) return false;
		}

		if(empty($filter)) return $this->map;

		#Массив результатов
		$result=array();

		#Просмотр объектов
		foreach($this->map as $item){

			$success = true;
			#Проверка объекта на соответствие заданным критериям фильтра
			foreach($filter as $key=>$value){

				if(isset($item[$key])){

					switch($key){
						case 'path':
						case 'php_code':
						case 'html_template':
						case 'title':
						case 'desc':
						case 'icon_32':
						case 'icon_16':
						case 'extra':
							$operator = '%LIKE%';
						break;
						case 'in_menu':
						case 'in_dashboard':
						case 'in_toolbar':
						case 'user':
						case 'childs':
							$operator = 'BOOL';
						break;
						default:
							$operator = '=';
					}

					if(!self::compareRuleElement($item[$key], $value, $operator)){
						echo $item[$key].' '.$operator.' '.$value;
						$success = false;
						break;
					}

				}else{
					$success = false;
					break;
				}

			}#Проверка объекта на соответствие заданным критериям фильтра


			#Добавление в результаты
			if( $success ){
				if(empty($fields)){
					$result[] = $item;
				}else{
					$tmp = array();
					foreach($fields as $k=>$v){
						$fk = is_numeric($k) ? $v : $k;
						$tmp[$v] = isset($item[$fk]) ? $item[$fk] : '';
					}
					$result[] = $tmp;
				}
			}

		}#Просмотр объектов

		#Возврат результатов
		return $result;

	}#end function


















}#end class


/*

-- Table: map

-- DROP TABLE map;

CREATE TABLE map
(
  "id" bigserial NOT NULL, -- Идентификатор элемента
  "parent_id" bigint NOT NULL DEFAULT 0, -- Идентификатор родительского элемента, если 0 - то этот элемент верхнего уровня
  "path" character varying(255), -- Внешний путь к документу (для строки браузера)
  "object_id" bigint NOT NULL DEFAULT 0, -- Идентификатор объекта ACL типа страница, привязанный к данному элементу, если 0 - страница никогда не  отобразится.
  "php_code" character varying(255), -- Путь к PHP файлу страницы относительно папки DIR_MODULES, если не задан - не используется
  "html_template" character varying(255), -- Путь к HTML файлу темплейта страницы относительно папки DIR_MODULES, если не задан - используется корневой темплейт.
  "title" character varying(255), -- Заголовок страницы для тега TITLE
  "desc" character varying(255), -- Описание страницы для dashboard или подсказки в toolbar
  "icon_32" character varying(255), -- Ссылка на файл иконки страницы относительно DIR_CLIENT (размер 32х32)
  "icon_16" character varying(255), -- Ссылка на файл иконки страницы относительно DIR_CLIENT (размер 16х16)
  "in_menu" integer, -- 1 - отображать страницу в меню, 0 - не отображать
  "in_dashboard" integer, -- 1 - отображать страницу в dashboard, 0 - не отображать
  "in_toolbar" integer, -- 1 - отображать страницу в toolbar, 0 - не отображать
  "extra" character varying(255),  -- Дополнительные данные, которые передаются в клиентскую часть для обработки в JS
  CONSTRAINT id PRIMARY KEY ("id"),
  CONSTRAINT path UNIQUE ("path")
)
WITH (
  OIDS=FALSE
);
ALTER TABLE map
  OWNER TO postgres;
COMMENT ON TABLE map
  IS 'Таблица карты приложения';
COMMENT ON COLUMN map.id IS 'Идентификатор элемента';
COMMENT ON COLUMN map.parent_id IS 'Идентификатор родительского элемента, если 0 - то этот элемент верхнего уровня';
COMMENT ON COLUMN map.path IS 'Внешний путь к документу (для строки браузера)';
COMMENT ON COLUMN map.object_id IS 'Идентификатор объекта ACL типа страница, привязанный к данному элементу, если 0 - страница никогда не  отобразится.';
COMMENT ON COLUMN map.php_code IS 'Путь к PHP файлу страницы относительно папки DIR_MODULES, если не задан - не используется';
COMMENT ON COLUMN map.html_template IS 'Путь к HTML файлу темплейта страницы относительно папки DIR_MODULES, если не задан - используется корневой темплейт.';
COMMENT ON COLUMN map.title IS 'Заголовок страницы';
COMMENT ON COLUMN map."desc" IS 'Описание страницы для dashboard или подсказки в toolbar';
COMMENT ON COLUMN map.icon_32 IS 'Ссылка на файл иконки страницы относительно DIR_CLIENT (размер 32х32)';
COMMENT ON COLUMN map.icon_16 IS 'Ссылка на файл иконки страницы относительно DIR_CLIENT (размер 16х16)';
COMMENT ON COLUMN map.in_menu IS '1 - отображать страницу в меню, 0 - не отображать';
COMMENT ON COLUMN map.in_dashboard IS '1 - отображать страницу в dashboard, 0 - не отображать';
COMMENT ON COLUMN map.in_toolbar IS '1 - отображать страницу в toolbar, 0 - не отображать';
COMMENT ON COLUMN map.extra IS 'Дополнительные данные, которые передаются в клиентскую часть для обработки в JS';

ALTER SEQUENCE map_id_seq MINVALUE 1 START WITH 1000;
SELECT setval('public.map_id_seq', 1000, true);
 
*/


?>