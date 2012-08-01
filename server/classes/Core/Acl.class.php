<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс Класс контроля доступа к объектам
Версия	: 1.0.3/BETA
Дата	: 2012-07-17
Автор	: Станислав В. Третьяков
--------------------------------



Функции класса:
--------------------------------

Информационные функции:
getDb - Возвращает указатель на объект базы данных ACL


Работа с сессией пользователя:
checkSessionStatus - Проверка статсуа сессии
userSessionCheck - Проверка корректности сессии пользователя


Функции работы с базой данных:
dbLoadAll - Загрузка массивов объектов, групп и организаций из базы данных
dbLoadGroups - Получение массива групп из базы данных
dbLoadObjects - Получение массива объектов из базы данных
dbLoadObjectGroups - Получение данных для сопоставления объектов ACL и групп доступа ACL
dbLoadRoles - Получение массива дочерних объектов контейнеров ролей
dbLoadCompanies - Получение массива компаний (организаций)
dbSetNeedUpdate - Установка статуса необходимости обновления прав доступа пользователям в доступных источниках данных


Работа с типами объектов:
objectTypeExists - Проверяет существование типа объекта по указанному идентификатору
getAllObjectTypes - Возвращает список всех типов ACL объектов


Работа с массивом организаций:
companyExists - Проверяет существование организации с указанным идентификатором
companyActive - Проверяет статус активности организации с указанным идентификатором
getCompany - Возвращает объект организации
getAllCompanies - Возвращает массив Организаций


Работа с массивом групп доступа:
groupExists - Проверяет, существует ли группа доступа
getGroup - Возвращает группу доступа
getGroupIdFormName - Возвращает числовой идентификатор группы доступа по имени группы доступа
getGroupNameFromId - Возвращает имя руппы доступа по идентификатору
getAllGroups - Возвращает массив ACL групп


Работа с массивом объектов:
objectExists - Проверяет, существует ли объект ACL
getObject - Возвращает объект ACL
getAllObjects - Возвращает массив объектов ACL
getObjectIdFormName - Возвращает идентификатор объекта по его имени
getObjectNameFromId - Возвращает имя объекта по его идентификатору
searchObjects - Возвращает массив объектов, удовлетворяющий условиям заданного фильтра
getObjectChilds - Получение массива дочерних объектов указанного объекта


Функции проверки коллизий:
haveCollision - Проверка коллизий: дочерний объект родителя является его родителем


Функции работы с пользователями: вычисление прав доступа:
addGroupsToList - Добавляет группы доступа в основной список из заданного линейного массива
addObjectsToList - Добавляет объекты доступа в основной список из заданного линейного массива 
addObjectToList - Добавляет один ACL объект в основной список
calculateRoleTree - Добавление элемента в массив доступа на основании дерева доступа
getUserPrivs - Построение массива прав доступа пользователя
getFinalUserAccess - Фильтрация и группировка по организациям объектов доступа, получение результирующего массива доступов пользователя


Правила:
calculateRules - Обработка и применение правил доступа к пользователю


Функции работы с базой данных:
dbLoadUserGroups - Получение групп доступа, в которые включен пользователь
dbLoadUserAccess - Получение массива контейнеров и объектов, к которым имеет доступ текущий пользователь
dbUserAccessNeedUpdate - Проверка необходимости обновления прав доступа для пользователя


АУТЕНТИФИКАЦИЯ И ПРОВЕРКА ПРАВ ДОСТУПА:
userAuth - Аутентификация пользователя на основании указанных логина и пароля
userAccess - Проверяет доступ текущего пользователя к ACL объекту в указанной орагнизации
userNeedUpdate - Проверяет, нужно ли обновлять права доступа текущему пользователю
userPrivUpdate - Обновление прав доступа текущего пользователя
getSessionHash - Расчет контрольной суммы для прав доступа, записываемых в сессию


Работа с организациями для текущего пользователя:
userCheckAccessToCompany - Проверяет, имеет ли пользователь какие-либо права в определенной организации
userGetFirstCompany - Возвращает первую организацию, к которой у пользователя есть права доступа
userCompanyChange - Смена организации


Работа с текущем пользователем:
getUserObjects - Возвращает перечень доступных объектов для текущего пользователя, отфильтрованных по определенным параметрам
userAclAttribute - Возвращает ACL аттрибут пользователя из текущей сессии


Работа источниками данных:
getSources - Возвращает перечень доступных источников данных и их названий
getSourceInfo - Возвращает аттрибут источника данных либо FALSE если аттрибут или источник данных не существует
getSourceSubjects - Возвращает перечень ACL субьектов, которые могут быть аутентифицированы из указанного источника данных
getAllUsers - Получение списка пользователей из указанного источника данных


Функции логирования:
logAction - Запись текущего действия пользователя в журнал событий

==================================================================================================*/











class Acl{

	use Core_Trait_SingletonUnique, Core_Trait_BaseError, Core_Trait_Compare;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/


	#Источники данных о пользователях: значения по-умолчанию
	protected $def_source = array(
		#Основные настройки
		'name'						=> '',				#(*) Понятное название источника данных
		'active'					=> false,			#(*) Признак активности источника данных, если FALSE - аутентификация пользователей из источника данных проводиться не будет
		'acl_subject'				=> array(),			#(*) Список субьектов ACL, которые могут быть аутентифицированы из данного источника данных
		'db_link'					=> null,			#(*) Ссылка на объект класса ваимодействия с базой данных, если не задана, используется основная ссылка $this->options['db_link']
		#Поля таблицы аутентификации пользователей
		'table_auth'				=> 'acl_users',		#(*) Таблица базы данных с аутентификационной информацией пользователей
		'field_auth_id'				=> null,			#(*) Поле таблицы, в котором хранится идентификатор пользователя
		'field_auth_login'			=> null,			#(*) Поле таблицы, в котором хранится имя пользователя
		'field_auth_pass'			=> null,			#(*) Поле таблицы, в котором хранится пароль пользователя
		'field_auth_name'			=> null,			#(*) Поле таблицы, в котором хранится имя пользователя
		'field_auth_status'			=> null,			#Поле таблицы, в котором хранится статсу учетной записи пользователя, 0 - учетная запись заблокирована
		'field_auth_lastcompany'	=> null,			#Поле таблицы, в котором хранится последняя активная организация, с которой работал пользователь
		'field_auth_lastlogin'		=> null,			#Поле таблицы, в котором хранится datetime последней аутентификации 
		'field_auth_lastip'			=> null,			#Поле таблицы, в котором хранится IP адрес последней аутентификации 
		'field_auth_lastupdate'		=> null,			#Поле таблицы, в котором хранится datetime последнего обновления прав доступа
		'field_auth_needupdate'		=> null,			#Поле таблицы, в котором хранится признак необходимости перегрузить права доступа пользователя
		'table_access'				=> null,			#(*) Таблица базы данных с информацией о правах доступа пользователей
		'table_log'					=> null,			#(*) Таблица базы данных, в которую следует писать протокол действий пользователя
		'table_group'				=> null,			#(*) Таблица базы данных с информацией о группах, в которые включен пользователь
		'table_info'				=> null,			#Таблица базы данных с дополнительной информацией по пользователю, если null - дополнительная информация не грузится
		'def_roles'					=> array(),			#Роли по-умолчанию, назначаемые пользователям, если null или пустой массив - ничего не назначается
		'def_groups'				=> array(),			#Группы доступа по-умолчанию, присваемые пользователям из данного источника
		'rules'						=> array()			#Правила, при выполнении которых пользователям назначаются определенные права доступа или пользователь включается в какие-нибудь группы

	);



	#Настройки и значения по-умолчанию для работы класса
	protected $options = array(

		'sources' => array(),				#Источники данных о пользователях
		'db_link'				=>null,		#(*) Текстовое наименование соединения с базой данных: получение списка ACL объектов, 
		'access_timeout'		=>0,		#Таймаут в секундах между обновлением массива доступов пользователя к объектам в рамках одной сессии, если 0 - права доступа не обновляются
		'developer_role'		=>-1,		#Идентификатор роли разработчика: если пользователь имеет доступ к объекту с указанным именем или ID, то блокировка объектов для него не выполняется
		'admin_role'			=>-1,		#Идентификатор роли администратора: если пользователь имеет доступ к объекту с указанным именем или ID, ему назначаются все права во всех организациях
		'error_level'			=> 1,		#Уровень обработки ошибок: 
											#0 - Никак не реагировать на ошибки
											#1 - Записывать в LOG файл только системные ошибки (код от 0 до 99).
											#2 - Записывать в LOG файл все ошибки и предупреждения
											#3 - Записывать в LOG файл все + выдавать сообщения о системных ошибках
											#4 - Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях
											#5 - Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях, при системных ошибках - завершать работу
		'ignore_hash_check'		=> false,	#Признак игнорирования проверки хеш сумм при аутентификации и авторизации пользователей, а также прав доступа в сессии
		'ignore_hash_error'		=> false,	#Признак игнорирования ошибок хеш сумм при аутентификации и авторизации пользователей, а также прав доступа в сессии
		'sess_priv_rsa'			=> 'sha1',	#Сертификат для расчета контрольной суммы прав доступа для сессии пользователя (по-умолчанию, sha1)
		'xcache'				=> true		#Использовать для хранения массивов объектов групп и т.д. XCache, вместо постоянной загрузки из БД
	);



	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		#Системные ошибки, от 1 до 99
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Ошибка во время выполнения SQL запроса'),
		2	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: В таблице объектов доступа отсутствуют данные'),
		3	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: В таблице организаций отсутствуют данные'),
		4	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: В таблице групп доступа отсутствуют данные'),
		5	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Невозможно получить перечень объектов доступа'),
		6	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Соединение с базой данных не установлено'),
		7	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: В рамках сессии отсутствует массив доступов пользователя к объектам'),
		9	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Выбранная организация не существует'),
		10	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Объект не существует'),
		11	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Ошибка получения источника данных.'),
		15	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Ошибка работы с сессией.'),
		16	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Ошибка получения информации пользователя из сессии.'),
		17	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Ошибка получения источника данных из сессии.'),
		18	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Источник данных задан некорректно.'),
		20	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: Не задан массив добавляемых прав'),
		51	=> array(EVENT_ACL_ERROR, 'Внутренняя ошибка: В функцию не переданы обязательные параметры'),
		70	=> array(EVENT_ACL_ERROR, 'Ошибка протокола: Тип объекта задан некорректно'),
		71	=> array(EVENT_ACL_ERROR, 'Ошибка протокола: Объект с заданным идентификатором не существует'),
		72	=> array(EVENT_ACL_ERROR, 'Ошибка протокола: Объект действия (функции) с заданным идентификатором не существует'),
		73	=> array(EVENT_ACL_ERROR, 'Ошибка протокола: Ошибка вставки записи в файл протокола'),
		73	=> array(EVENT_ACL_ERROR, 'Ошибка протокола: Ошибка вставки записи в базу данных'),
		#Ошибки аутентификации и авторизации
		110	=> array(EVENT_ACL_ERROR, 'Не задано Имя пользователя и/или пароль'),
		120	=> array(EVENT_ACL_ERROR, 'Имя пользователя и/или пароль указаны неверно'),
		130	=> array(EVENT_ACL_ERROR, 'Учетная запись заблокирована'),
		135	=> array(EVENT_ACL_ERROR, 'Ваша учетная запись не входит ни в одну группу доступа. Обратитесь к администратору.'),
		140	=> array(EVENT_ACL_ERROR, 'Отсутствуют какие-либо права для работы в выбранной организации'),
		145	=> array(EVENT_ACL_ERROR, 'Для Вашей учетной записи не были присвоены какие-либо права доступа. Обратитесь к администратору.'),
		150	=> array(EVENT_ACL_ERROR, 'Недостаточно прав'),
		170	=> array(EVENT_ACL_ERROR, 'Доступ к данному объекту был временно ограничен администратором'),
		180	=> array(EVENT_ACL_ERROR, 'Доступ в выбранную организацию заблокирован'),

		#Ошибки сессии
		200	=> array(EVENT_ACL_ERROR, 'Сессия истекла, требуется повторная аутентификация'),
		201	=> array(EVENT_ACL_ERROR, 'Сессия истекла, требуется повторная аутентификация'),
		202	=> array(EVENT_ACL_ERROR, 'Сессия истекла, требуется повторная аутентификация'),
		220	=> array(EVENT_ACL_ERROR, 'Ваш IP адрес был изменен, требуется повторная аутентификация'),
		#Ошибки источников данных
		301	=> array(EVENT_ACL_ERROR, 'Попытка назначить права пользователю из группы, не поддерживающий данную привелегию'),
		302	=> array(EVENT_ACL_ERROR, 'Попытка назначить права администратора пользователю из группы, не поддерживающий данную привелегию'),
		303	=> array(EVENT_ACL_ERROR, 'Попытка назначить права разработчика пользователю из группы, не поддерживающий данную привелегию'),
		#Ошибки контрольный сумм
		401 => array(EVENT_ACL_ERROR, 'Контрольная сумма учетной записи пользователя не верна. Доступ запрещен.'),
		404 => array(EVENT_ACL_ERROR, 'Контрольная сумма для групп доступа пользователя не верна. Доступ запрещен.'),
		405 => array(EVENT_ACL_ERROR, 'Контрольная сумма для прав доступа пользователя к объектам не верна. Доступ запрещен.'),
		410 => array(EVENT_ACL_ERROR, 'Контрольная сумма прав доступа пользователя в сессии не верна. Доступ запрещен.')
	);



	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/Acl',
		'backtrace'	=> true
	);



	private $db 				= null;		#Идентификатор соединения с базой данных
	private $developer_role_id	= -1;		#Идентификатор роли, наличие которой у пользователя указывает что он разработчик, и имеет доступ к заблокированным объектам
	private $admin_role_id		= -1;		#Идентификатор роли, наличие которой у пользователя указывает что он имеет доступ ко всем объектам во всех организациях, кроме заблокированных
	private $childs_loaded		= false;	#Признак, указывающий на то, загружены ли уже данные по дочерним объектам из таблицы acl_containers
	private $companies_loaded	= false;	#Признак, указывающий на то, загружены ли уже данные по организациям
	private $objects_loaded		= false;	#Признак, указывающий на то, загружены ли уже данные по объектам
	private $groups_loaded		= false;	#Признак, указывающий на то, загружены ли уже данные по гркппам доступа
	private $ignore_hash_check	= false;	#Признак игнорирования проверки хеш сумм при аутентификации и авторизации пользователей
	private $ignore_hash_error	= true;		#Признак игнорирования ошибок хеш сумм при аутентификации и авторизации пользователей

	private $user_update_ckeck	= false;	#Признак проверки статуса необходимости обновления прав пользователя в базе данных
	private $user_need_update	= false;	#Признак необходимости обновления прав пользователя

	private $sess_priv_check	= false;	#Признак проверки корректности прав доступа в сессии пользователя, по-умолчанию - НЕ ПРОВЕНО (false)
	private $sess_priv_error	= false;	#Признак корректности прав доступа в сессии пользователя, по-умолчанию - КОРРЕКТНО (false)
	private $sess_priv_rsa		= 'sha1';	#Сертификат для расчета контрольной суммы прав доступа для сессии пользователя (по-умолчанию, sha1)

	private $xcache				= true;		#Использовать для хранения массивов объектов групп и т.д. XCache, вместо постоянной загрузки из БД

	public $groups				= array();		#Массив доступных групп доступа
	public $group_names			= array();		#Массив сопоставления имен групп доступа с ID
	public $companies			= array();		#Массив доступных организаций
	public $objects				= array();		#Массив доступных объектов по ID объекта
	public $object_names		= array();		#Массив сопоставления имен объектов с ID объекта


	#Ссылка на экземпляр класса Log
	protected $log	=	null;		#Экземпляр класса Log







	/*==============================================================================================
	Инициализация
	==============================================================================================*/



	/*
	 * Конструктор класса
	 */
	protected function init($options=null){

		#Установка опций
		$this->options = array_merge($this->options, (is_array($options) ? $options : Config::getOptions('Core/acl')));

		#Обработка источников данных
		foreach($this->options['sources'] as $key => $value){
			foreach($this->def_source as $dkey => $dvalue){
				if(!isset($value[$dkey])||empty($value[$dkey])) $this->options['sources'][$key][$dkey]=$dvalue;
			}
		}

		$this->error_level			= $this->options['error_level'];
		$this->developer_role_id	= $this->options['developer_role'];
		$this->admin_role_id		= $this->options['admin_role'];
		$this->ignore_hash_check	= $this->options['ignore_hash_check'];
		$this->ignore_hash_error	= $this->options['ignore_hash_error'];
		$this->sess_priv_rsa		= $this->options['sess_priv_rsa'];
		$this->xcache				= XCache::_isEnabled();


		#Объект доступа к базе данных
		$this->db = Database::getInstance($this->options['db_link']);

		#Установка префикса для сессии
		Session::_addPrefix(array('user_'));

		$this->log = LogFile::getInstance(
			'Core/Security/acl_actions',
			array(
				#Путь к файлу журнала, пример: /var/www/test/server/logs/Core/Security/acl_actions.log
				'file' => DIR_LOGS.'/Core/Security/acl_actions.log'
			)
		);


	}#end function



	/*
	 * Чтение данных из недоступных свойств
	 */
	public function __get($name){
		return Session::_get($name);
	}#end function



	/*
	 * Запись данных в недоступные свойства
	 */
	public function __set($name, $value){
		return Session::_set($name, $value);
	}#end function



	/*
	 * будет выполнен при использовании isset() или empty() на недоступных свойствах.
	 */
	public function __isset($name){
		return Session::_paramIsset($name);
	}#end function




	/*
	 * будет выполнен при вызове unset() на недоступном свойстве
	 */
	public function __unset($name){
		Session::_paramUnset($name);
		return true;
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




	/* 
	 * Возвращает указатель на объект базы данных ACL
	 */
	public function getDb(){
		return $this->db;
	}#end function






	/*==============================================================================================
	Работа с сессией пользователя
	==============================================================================================*/



	/*
	 * Проверка статсуа сессии
	 * 
	 * Принимает аргументы:
	 * $autostart - признак автоматического старта сессии, если сессия отсутствует
	 * 
	 * Возвращает:
	 * TRUE, если сессия запущена, FALSE - если сессия не запущена
	 */
	public function checkSessionStatus($autostart=true){

		#Проверка статуса сессии
		return Session::_getStatus($autostart);

	}#end function



	/*
	 * Проверка корректности сессии пользователя
	 */
	public function userSessionCheck(){

		$session = Session::getInstance();

		$result = $session->badSession(array(
			'user'		=> null,
			'acl'		=> null,
			'access'	=> null
		));

		#При проверке сессии возникли ошибки
		if($result !== false){
			switch($result){
				case 'session': 	return $this->doErrorEvent(15, __FUNCTION__, __LINE__); #Внутренняя ошибка: Ошибка работы с сессией.
				case 'session_id': 	return $this->doErrorEvent(201, __FUNCTION__, __LINE__); #Сессия истекла
				case 'session_ip': 	return $this->doErrorEvent(220, __FUNCTION__, __LINE__); #IP адрес был изменен, требуется повторная аутентификация
				case 'user':
				case 'acl':
				case 'access':
					return $this->doErrorEvent(200, __FUNCTION__, __LINE__); #Сессия истекла
				default:
					return $this->doErrorEvent(200, __FUNCTION__, __LINE__); #Сессия истекла
			}
		}

		$acl = $session->get('acl');
		$access = $session->get('access');

		if(	empty($acl) || 
			!is_array($acl) ||
			empty($acl['user_id']) || 
			empty($acl['user_login']) || 
			empty($acl['source_name']) || 
			empty($acl['hash_access']) || 
			!is_numeric($acl['user_id']) || 
			empty($access) || 
			!is_array($access) ||
			empty($this->options['sources'][$acl['source_name']])
		){
			return $this->doErrorEvent(15, __FUNCTION__, __LINE__); #Внутренняя ошибка: Ошибка работы с сессией.
		}

		#Проверка контрольной суммы
		if(!$this->sess_priv_check && !$this->ignore_hash_check){

			$this->sess_priv_check = true;

			#Расчет контрольной суммы выполняется путем "суммирования" идентификатора сессии session_id и 
			#массива прав доступа, присвоенных пользователю в рамках данной сессии по алгоритму sha1
			#Поскольку это исключительно внутренний механизм проверки целостности, по-умолчанию подпись ЭЦП не используется,
			#но может быть включена при необходимости, указанием имени соответствующего сертификата
			$hash_calc	= $session->get('session_id') . Hash::_toString($access, null, null);
			$hash		= Hash::_getHash($hash_calc, null, null, $this->sess_priv_rsa);

			if(!Hash::_compareHash($acl['hash_access'], $hash)){

				if(!$this->ignore_hash_error){
					$this->sess_priv_error = true;
					return $this->doErrorEvent(410, __FUNCTION__, __LINE__); 
				}

			}

		}else{
			if($this->sess_priv_check == true && $this->sess_priv_error == true && !$this->ignore_hash_error) return false; 
		}

		return true;

	}#end function












	/*==============================================================================================
	Функции работы с базой данных:
	Загрузка общих сведений об объектах ACL, ролях, группах доступа,
	кроме получения информации по пользователю
	==============================================================================================*/



	/*
	 * Загрузка массивов объектов и организаций из базы данных
	 */
	public function dbLoadAll(){

		if(!$this->db->correct_init) return $this->doErrorEvent(6, __FUNCTION__, __LINE__);

		#Чтение в массив доступных групп доступа
		if(!$this->groups_loaded){
			if(!$this->dbLoadGroups()) return false;
		}

		#Чтение в массив доступных объектов
		if(!$this->objects_loaded){
			if(!$this->dbLoadObjects()) return false;
		}

		#Чтение в массив доступных организаций
		if(!$this->companies_loaded){
			if(!$this->dbLoadCompanies()) return false;
		}

		if($this->xcache) XCache::_set('acl/actual', true);

		return true;
	}#end function



	/*
	 * Получение массива групп из базы данных
	 */
	public function dbLoadGroups(){


		if(!$this->xcache||!XCache::_exists('acl/groups')||!XCache::_exists('acl/group_names')||XCache::_get('acl/actual')==false){

			$this->db->prepare('SELECT * FROM ?');
			$this->db->bind('acl_groups', null, BIND_FIELD);

			if( ($this->groups = $this->db->selectByKey('group_id')) === false){
				return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
			}

			if(!is_array($this->groups)){
				return $this->doErrorEvent(4, __FUNCTION__, __LINE__); #Внутренняя ошибка: В таблице групп доступа отсутствуют данные
			}

			if(count($this->groups)==0){
				return $this->doErrorEvent(4, __FUNCTION__, __LINE__); #Внутренняя ошибка: В таблице групп доступа отсутствуют данные
			}

			#Массив сопоставления имен групп доступа с ID
			foreach($this->groups as $key=>$value){
				$this->group_names[$value['name']] = $key;
			}

			XCache::_set('acl/groups', $this->groups);
			XCache::_set('acl/group_names', $this->group_names);

		}else{
			$this->groups = XCache::_get('acl/groups');
			$this->group_names = XCache::_get('acl/group_names');
		}


		#Данные по группам доступа загружены
		$this->groups_loaded = true;

		return true;
	}#end function



	/*
	 * Получение массива объектов из базы данных
	 */
	public function dbLoadObjects(){


		if(!$this->xcache||!XCache::_exists('acl/objects')||!XCache::_exists('acl/object_names')||XCache::_get('acl/actual')==false){

			$this->db->prepare('SELECT * FROM ?');
			$this->db->bind('acl_objects', null, BIND_FIELD);

			if( ($this->objects = $this->db->selectByKey('object_id')) === false){
				return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
			}

			if(!is_array($this->objects)){
				return $this->doErrorEvent(2, __FUNCTION__, __LINE__); #Внутренняя ошибка: В таблице объектов доступа отсутствуют данные
			}

			if(count($this->objects)==0){
				return $this->doErrorEvent(2, __FUNCTION__, __LINE__); #Внутренняя ошибка: В таблице объектов доступа отсутствуют данные
			}

			#Массив сопоставления имен объектов доступа с ID
			foreach($this->objects as $key=>$value){
				$this->object_names[$value['name']] = $key;
				$this->objects[$key]['childs'] = array();	#Массив дочерних элементов объекта
				$this->objects[$key]['groups'] = array();	#Массив групп доступа, в которые включен объект
			}

			#Чтение в массив контейнеров ролей
			if($this->dbLoadRoles() === false) return false;

			#Чтение в массив данных для сопоставления объектов ACL и групп доступа ACL
			if($this->dbLoadObjectGroups() === false) return false;

			XCache::_set('acl/objects', $this->objects);
			XCache::_set('acl/object_names', $this->object_names);

		}else{
			$this->objects = XCache::_get('acl/objects');
			$this->object_names = XCache::_get('acl/object_names');
		}

		#Данные по объектам загружены
		$this->objects_loaded = true;

		return true;
	}#end function



	/*
	 * Получение данных для сопоставления объектов ACL и групп доступа ACL
	 */
	private function dbLoadObjectGroups(){

		$this->db->prepare('SELECT * FROM ?');
		$this->db->bind('acl_object_groups', null, BIND_FIELD);

		#Получение данных по дочерним элементам контейнеров (ролей)
		if( ($list = $this->db->select())===false){
			return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса;
		}

		foreach($list as $item){
			#Если объект существует и группа доступа существует - 
			#Устанавливаем, что объект доступен для данной группы доступа
			if(isset($this->objects[$item['object_id']]) && isset($this->groups[$item['group_id']])){
				if(!in_array($item['group_id'], $this->objects[$item['object_id']]['groups'])){
					array_push($this->objects[$item['object_id']]['groups'], $item['group_id']);
				}
			}
		}

		return true;
	}#end function



	/*
	 * Получение массива дочерних объектов контейнеров ролей
	 */
	private function dbLoadRoles(){

		$this->db->prepare('SELECT * FROM ?');
		$this->db->bind('acl_roles', null, BIND_FIELD);

		#Получение данных по дочерним элементам контейнеров (ролей)
		if( ($list = $this->db->select())===false){
			return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса;
		}

		foreach($list as $item){
			#Если объект существует
			if(isset($this->objects[$item['object_id']]) && isset($this->objects[$item['child_id']])){
				#Если тип объекта - контейнер (роль)
				if($this->objects[$item['object_id']]['type']==ACL_OBJECT_ROLE){
					array_push($this->objects[$item['object_id']]['childs'], $item['child_id']);
				}
			}
		}

		#Данные по дочерним объектам загружены
		$this->childs_loaded = true;

		return true;
	}#end function



	/*
	 * Получение массива компаний
	 */
	public function dbLoadCompanies(){

		if(!$this->xcache||!XCache::_exists('acl/companies')||XCache::_get('acl/actual')==false){

			$this->db->prepare('SELECT * FROM ?');
			$this->db->bind('acl_companies', null, BIND_FIELD);

			if( ($this->companies = $this->db->selectByKey('company_id', null)) === false){
				return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
			}

			if(!is_array($this->companies)){
				return $this->doErrorEvent(3, __FUNCTION__, __LINE__, $this->companies); #Внутренняя ошибка: В таблице организаций отсутствуют данные
			}

			if(count($this->companies) == 0){
				return $this->doErrorEvent(3, __FUNCTION__, __LINE__); #Внутренняя ошибка: В таблице организаций отсутствуют данные
			}

			XCache::_set('acl/companies', $this->companies);

		}else{
			$this->companies = XCache::_get('acl/companies');
		}

		#Данные по организациям загружены
		$this->companies_loaded = true;

		return $this->companies;
	}#end function




	/*
	 * Установка статуса необходимости обновления прав доступа пользователям в доступных источниках данных
	 * 
	 * $user_id - идентификатор пользователя, которому требуется обновить права, если не указан - все пользователи
	 * $source_name - имя источника данных, если не указано - все источники данных
	 */
	public function dbSetNeedUpdate($user_id=0, $source_name=''){

		if(empty($source_name)){
			$sources = $this->options['sources'];
		}else{
			if(empty($this->options['sources'][$source_name])) return $this->doErrorEvent(18, __FUNCTION__, __LINE__); #Источник данных задан некорректно
			$sources = array($source_name => $this->options['sources'][$source_name]);
		}

		#Просмотр источников данных
		foreach($sources as $name=>$source){

			$table_auth			= $source['table_auth']; #Таблица базы данных с аутентификационной информацией пользователей
			$field_id			= $source['field_auth_id']; #Поле таблицы, в котором хранится идентификатор пользователя
			$field_need_update	= $source['field_auth_needupdate']; #Поле таблицы, в котором хранится признак необходимости перегрузить права доступа пользователя
			if(empty($table_auth)||empty($field_id)||empty($field_need_update)) continue;

			$db	= (empty($source['db_link']) ? $this->db : Database::getInstance($source['db_link']));
			$sql = $db->buildUpdate(
				$table_auth,
				array($field_need_update => 1),
				(empty($user_id) ? array() : array($field_id => $user_id))
			);

			if($db->update($sql) === false){
				return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
			}

		}#Просмотр источников данных

		return true;
	}#end function












	/*==============================================================================================
	Работа с типами объектов
	==============================================================================================*/

	/*
	 * Проверяет существование типа объекта по указанному идентификатору
	 */
	public function objectTypeExists($object_type=null){

		#Получение массива типов объектов
		$types = Config::getOption('Core/acl','objects');
		if(empty($types)||!is_array($types)) return false;

		if(is_numeric($object_type)){
			foreach($types as $t){
				if(empty($t)||!is_array($t)) continue;
				if($t[0] == $object_type) return $t[0];
			}
		}else{
			foreach($types as $n=>$t){
				if($n == $object_type){
					return $t[0];
				}
			}
		}

		return false;
	}#end function



	/*
	 * Возвращает список всех ACL объектов
	 * $aclmanager - признак, при TRUE указывающий на необходимость вернуть все типы ACL с которыми можно создавать ACL объекты.
	 * при FALSE - возвращаются все типы ACL объектов, используемые в приложении.
	 */
	public function getAllObjectTypes($aclmanager=false){

		#Получение массива типов объектов
		$types = Config::getOption('Core/acl','objects');
		if(empty($types)||!is_array($types)) return false;

		if($aclmanager){
			return array_filter($types, function($item){return($item>1);});
		}else{
			return array_filter($types, function($item){return($item>0);});
		}

	}#end function









	/*==============================================================================================
	Работа с массивом организаций
	==============================================================================================*/



	/*
	 * Проверяет существование организации с указанным идентификатором
	 */
	public function companyExists($company_id=0){

		#Получение массива организаций
		if($this->companies_loaded==false){
			if(!$this->dbLoadCompanies()) return false;
		}

		if(!isset($this->companies[$company_id])||!is_array($this->companies[$company_id])) return false;

		return $company_id;
	}#end function



	/*
	 * Проверяет статус активности организации с указанным идентификатором
	 * 
	 * Если организация существует и активна, возвращает TRUE, иначе - FALSE
	 */
	public function companyActive($company_id=0){

		#Проверка существования организации с указанным идентификатором
		if(!$this->companyExists($company_id)) return false;

		return ($this->companies[$company_id]['lock'] == 0 ? true : false);
	}#end function



	/*
	 * Возвращает объект организации
	 */
	public function getCompany($company_id=0){

		#Проверка существования организации с указанным идентификатором
		if(!$this->companyExists($company_id)){
			return $this->doErrorEvent(9, __FUNCTION__, __LINE__); #Внутренняя ошибка: Выбранная организация не существует
		}

		return  $this->companies[$company_id];
	}#end function



	/*
	 * Возвращает массив Организаций
	 */
	public function getAllCompanies(){

		#Чтение в массив доступных организаций
		if(!$this->companies_loaded){
			if(!$this->dbLoadCompanies()) return false;
		}

		return array_values($this->companies);
	}#end function










	/*==============================================================================================
	Работа с массивом групп доступа
	==============================================================================================*/



	/*
	 * Проверяет, существует ли группа доступа
	 * Функция берет идентификатор группы доступа $group_id, 
	 * представляющий собой именованный или числовой идентификатор.
	 * Проверяет существование группы доступа и если группа найдена, возвращает TRUE
	 * иначе возвращает FALSE
	 */
	public function groupExists($group_id=0){

		#Чтение в массив доступных объектов
		if(!$this->groups_loaded){
			if(!$this->dbLoadGroups()) return false;
		}

		if(!is_numeric($group_id)){
			$group_id = $this->getGroupIdFormName($group_id);
		}
		if(!$group_id) return false;

		if(!isset($this->groups[$group_id])||!is_array($this->groups[$group_id])) return false;

		return $group_id;
	}#end function



	/*
	 * Возвращает группу доступа
	 * Функция берет идентификатор группы доступа $group_id, 
	 * представляющий собой именованный или числовой идентификатор.
	 * Проверяет существование группы доступа и возвращает если группа найдена, 
	 * возвращает объект группы доступа
	 */
	public function getGroup($group_id=0){

		#Чтение в массив доступных объектов
		if(!$this->groups_loaded){
			if(!$this->dbLoadGroups()) return false;
		}

		if(!is_numeric($group_id)){
			$group_id = $this->getGroupIdFormName($group_id);
		}
		if(!$group_id) return false;

		if(!isset($this->groups[$group_id])||!is_array($this->groups[$group_id])) return false;

		return $this->groups[$group_id];
	}#end function



	/*
	 * Возвращает числовой идентификатор группы доступа по имени группы доступа
	 */
	public function getGroupIdFormName($name=''){

		#Чтение в массив доступных объектов
		if(!$this->groups_loaded){
			if(!$this->dbLoadGroups()) return false;
		}

		if(!isset($this->group_names[$name])) return false;
		$group_id = $this->group_names[$name];
		if(!isset($this->groups[$group_id])||!is_array($this->groups[$group_id])) return false;

		return $group_id;
	}#end function



	/*
	 * Возвращает имя руппы доступа по идентификатору
	 */
	public function getGroupNameFromId($group_id=0){

		#Чтение в массив доступных групп доступа
		if(!$this->groups_loaded){
			if(!$this->dbLoadGroups()) return false;
		}

		if(!isset($this->groups[$group_id])||!is_array($this->groups[$group_id])) return false;

		return $this->groups[$group_id]['name'];
	}#end function



	/*
	 * Возвращает массив ACL групп
	 */
	public function getAllGroups(){

		#Чтение в массив доступных объектов
		if(!$this->groups_loaded){
			if(!$this->dbLoadGroups()) return false;
		}

		return array_values($this->groups);
	}#end function














	/*==============================================================================================
	Работа с массивом объектов
	==============================================================================================*/



	/*
	 * Проверяет, существует ли объект ACL
	 * Функция берет идентификатор объекта ACL $object_id,
	 * представляющий собой именованный или числовой идентификатор.
	 * Проверяет существование объекта ACL и если объект найден, возвращает TRUE,
	 * в противном случае возвращает FALSE
	 */
	public function objectExists($object_id=0){

		#Чтение в массив доступных объектов
		if(!$this->objects_loaded){
			if(!$this->dbLoadObjects()) return false;
		}

		if(!is_numeric($object_id)){
			$object_id = $this->getObjectIdFormName($object_id);
		}
		if(!$object_id) return false;

		if(!isset($this->objects[$object_id])||!is_array($this->objects[$object_id])) return false;

		return $object_id;
	}#end function



	/*
	 * Возвращает объект ACL
	 * Функция берет идентификатор объекта ACL $object_id,
	 * представляющий собой именованный или числовой идентификатор.
	 * Проверяет существование объекта ACL и возвращает объект ACL
	 */
	public function getObject($object_id=0){

		#Чтение в массив доступных объектов
		if(!$this->objects_loaded){
			if(!$this->dbLoadObjects()) return false;
		}

		if(!is_numeric($object_id)){
			$object_id = $this->getObjectIdFormName($object_id);
		}
		if(!$object_id) return false;

		if(!isset($this->objects[$object_id])||!is_array($this->objects[$object_id])) return false;

		return $this->objects[$object_id];
	}#end function



	/*
	 * Возвращает массив объектов ACL
	 */
	public function getAllObjects(){

		#Чтение в массив доступных объектов
		if(!$this->objects_loaded){
			if(!$this->dbLoadObjects()) return false;
		}

		return array_values($this->objects);
	}#end function



	/*
	 * Возвращает идентификатор объекта по его имени
	 */
	public function getObjectIdFormName($name=''){

		#Чтение в массив доступных объектов
		if(!$this->objects_loaded){
			if(!$this->dbLoadObjects()) return false;
		}

		if(!isset($this->object_names[$name])) return false;
		$object_id = $this->object_names[$name];
		if(!isset($this->objects[$object_id])||!is_array($this->objects[$object_id])) return false;

		return $object_id;
	}#end function



	/*
	 * Возвращает имя объекта по его идентификатору
	 */
	public function getObjectNameFromId($object_id=0){

		#Чтение в массив доступных объектов
		if(!$this->objects_loaded){
			if(!$this->dbLoadObjects()) return false;
		}

		if(!isset($this->objects[$object_id])||!is_array($this->objects[$object_id])) return false;

		return $this->objects[$object_id]['name'];
	}#end function



	/*
	 * Возвращает массив объектов, удовлетворяющий условиям заданного фильтра
	 */
	public function searchObjects($filter=array()){

		#Чтение в массив доступных объектов
		if(!$this->objects_loaded){
			if(!$this->dbLoadObjects()) return false;
		}

		#Массив результатов
		$result=array();

		#Если пользователь не имеет роль разработчика, 
		#и явно не указано, что требуется игнорирование блокированных объектов - 
		#добавляем в фильтр возврат только активных объектов
		if(!$this->user_is_developer && !isset($filter['lock'])) $filter['lock'] = 0;

		#Просмотр объектов
		foreach($this->objects as $object){

			$success = true;
			#Проверка объекта на соответствие заданным критериям фильтра
			foreach($filter as $key=>$value){
				if($value != $object[$key]) $success = false;
			}

			#Добавление в результаты
			if( $success ) array_push($result, $object);

		}#Просмотр объектов

		#Возврат результатов
		return $result;
	}#end function



	/*
	 * Получение массива дочерних объектов указанного объекта
	 */
	public function getObjectChilds($object_id=0){

		#Чтение в массив доступных объектов
		if(!$this->objects_loaded){
			if(!$this->dbLoadObjects()) return false;
		}

		if(!is_numeric($object_id)){
			$object_id = $this->getObjectIdFormName($object_id);
			if(!$object_id) return false;
		}else{
			if(!isset($this->objects[$object_id])||!is_array($this->objects[$object_id])) return false;
		}

		if($this->objects[$object_id]['type']!=ACL_OBJECT_ROLE) return false;

		return $this->objects[$object_id]['childs'];
	}#end function












	/*=====================================================================================================================================
	Функции проверки коллизий
	======================================================================================================================================*/



	/*
	 * Проверка коллизий: дочерний объект родителя является его родителем
	 * 
	 * Принимает аргументы:
	 * $owner_id (*) - Идентификатор родительского элемента объекта
	 * $object_id (*) - Идентификатор объекта
	 * 
	 * Результат:
	 * Возвращает TRUE, если в дочерних объектах контейнера $object_id присутствует $owner_id
	 */
	public function haveCollision($owner_id, $object_id){

		if($owner_id == $object_id) return true;

		#Чтение в массив доступных объектов
		if(!$this->dbLoadAll()) return false;

		if(!is_numeric($object_id)){
			if( ($object_id = $this->getObjectIdFormName($object_id)) === false) return false; #Объект не существуе, коллизии быть не может
		}

		if(!is_numeric($owner_id)){
			if( ($owner_id = $this->getObjectIdFormName($owner_id)) === false) return false; #Объект не существуе, коллизии быть не может
		}

		$tree = array($owner_id);

		return $this->haveCollisionCalculate($owner_id, $object_id, $tree);
	}#end function



	/*
	 * Проверка коллизий: дочерний объект родителя является его родителем
	 * 
	 * Функция проверяет, является ли объект $object_id родителем объекта $owner_id
	 * Возвращает TRUE, если коллизия найдена, функция рекурсивна
	 */
	private function haveCollisionCalculate($owner_id, $object_id, &$tree){

		if(!isset($this->objects[$object_id]['childs'])) return false;

		#Просмотр дочерних объектов роли
		foreach($this->objects[$object_id]['childs'] as $child_id){

			#Если текущий дочерний объект $object_id, является родителем для $owner_id, коллизия найдена
			if($child_id == $owner_id) return true;

			#Если текущий дочерний объект в свою очередь также является ролью - делаем рекурсивный запрос
			#Перед рекурсивным запросом проверяем, в истории запросов отсутствие коллизий для исключения 
			#замкнутого цикла: родитель имеет в дочерних объектах своего родителя
			if($this->objects[$child_id]['type'] == ACL_OBJECT_ROLE){
				if(array_search($child_id, $tree)===false){
					array_push($tree, $child_id);
					if( $this->haveCollisionCalculate($owner_id, $child_id, $tree) == true )return true;
				}
			}

		}#foreach

		#Коллизии не найдены
		return false;
	}#end function
















	/*=====================================================================================================================================
	Функции работы с пользователями: вычисление прав доступа
	======================================================================================================================================*/



	/* 
	 * Добавляет группы доступа в основной список из заданного линейного массива 
	 * 
	 * Функция берет исходный линейный массив $groups,
	 * представляющий собой смешанный набор идентификаторов и имен групп доступа,
	 * проверяет существование группы доступа, 
	 * конвертирует именованный идентификатор группы доступа в числовой идентификатор
	 * и добавляет запись в массив $list, если таковая там отсутствует
	 * 
	 * $list - основной список групп доступа
	 * $groups - список идентификаторов групп, которые требуется включить в основной список
	 * $explain - ссылка на массив объяснения доступа
	 * $comment - комментарий для EXPLAIN ACCESS
	 * 
	 * Структура записи основного списка групп доступа
	 * 'uID'=>array(
	 * 	1,	#id объекта
	 * 	1	#признак запрета
	 * )
	 */
	private function addGroupsToList(&$list, $groups=array(), &$explain, $comment=''){

		#Загрузка массива объектов из базы данных
		if(!$this->dbLoadAll()) return false;

		foreach($groups as $item){

			#Если группа доступа задана не массивом, преобразуем в массив, считая что restrict = false
			if(!is_array($item)) $item = array($item, 0);

			if(!isset($item[0])){
				array_push($explain,'? Группа доступа задана пустым массивом'.$comment);
				continue;
			}
			$ident		= $item[0];
			$restrict	= (isset($item[1]) ? $item[1] : 0);


			#Если задан текстовый идентификатор группы - пытаемся определить его числовой идентификатор
			if(!is_numeric($ident)){
				#группа доступа по идентификатору не найдена
				if( ($id = $this->getGroupIdFormName($ident))===false){
					array_push($explain,'? Не найдена группа доступа по текстовому идентификатору ['.$ident.']'.$comment);
					continue;
				}
				$ident = $id;
			}else{
				#группа доступа по идентификатору не найдена
				if( $this->groupExists($ident) === false){
					array_push($explain,'? Не найдена группа доступа по числовому идентификатору ['.$ident.']'.$comment);
					continue;
				}
			}

			#Группа не задана в основном массиве
			if(!isset($list['u'.$ident])){
				$list['u'.$ident] = array(
					$ident,		#id объекта
					$restrict	#признак запрета
				);
				if(!$restrict)
					array_push($explain, '+ группа доступа ID:'.$ident.' ['.$this->getGroupNameFromId($ident).']'.$comment);
				else
					array_push($explain, '- запрет на группу доступа ID:'.$ident.' ['.$this->getGroupNameFromId($ident).']'.$comment);
			}
			#Группа уже задана в основном массиве
			else{
				#Группа доступа еще не запрещена, но задан запрет
				if(!$list['u'.$ident][1] && $restrict){
					$list['u'.$ident][1] = $restrict;
					array_push($explain, '- запрет на группу доступа ID:'.$ident.' ['.$this->getGroupNameFromId($ident).']'.$comment);
				}else{
					array_push($explain, '! Повторное назначение группы доступа ID:'.$ident.' ['.$this->getGroupNameFromId($ident).']'.$comment);
				}
			}


		}

		return $list;
	}#end function




	/* 
	 * Добавляет объекты доступа в основной список из заданного линейного массива 
	 * 
	 * Функция берет исходный линейный массив $objects,
	 * представляющий собой смешанный набор идентификаторов и имен объектов доступа,
	 * проверяет существование объекта доступа, 
	 * конвертирует именованный идентификатор объекта доступа в числовой идентификатор
	 * и добавляет запись в массив $list, если таковая там отсутствует
	 * 
	 * $list - основной список объектов доступа
	 * $objects - список идентификаторов объектов, которые требуется включить в основной список
	 * $explain - ссылка на массив объяснения доступа
	 * $comment - комментарий для EXPLAIN ACCESS
	 * 
	 * Структура записи основного списка групп доступа
	 * 'uID'=>array(
	 * 	1,		//id объекта
	 * 	0,		//id организации
	 * 	1		//признак запрета
	 * )
	 */
	private function addObjectsToList(&$list, $objects=array(), &$explain, $comment=''){

		#Загрузка массива объектов из базы данных
		if(!$this->dbLoadAll()) return false;

		#Просмотр добавляемых объектов
		foreach($objects as $item){

			#Если объект доступа задан не массивом, преобразуем в массив, считая что restrict = false и объект доступен для любой организации
			if(!is_array($item)) $item = array($item, 0, 0);

			if(!isset($item[0])){
				array_push($explain,'? Объект доступа задан пустым массивом'.$comment);
				continue;
			}
			$ident		= $item[0];
			$company_id	= (isset($item[1]) ? $item[1] : 0);
			$restrict	= (isset($item[2]) ? $item[2] : 0);
			$object = $this->getObject($ident); #Получение объекта

			#Объект не найден
			if($object === false || !is_array($object)){
				array_push($explain,'? Не найден объект доступа по идентификатору ['.$ident.']'.$comment);
				continue;
			}

			$ident = $object['object_id'];

			#Если текущий объект является контейнером (ролью)
			if($object['type'] == ACL_OBJECT_ROLE){

				array_push($explain,'i Добавляется объект типа роль ID:'.$ident.' ['.$object['name'].'] для организации ID:'.$company_id.$comment);

				#Если текущая роль доступна во всех организациях
				if($company_id == 0){

					#Просмотриваем все организации
					foreach($this->companies as $company){

						$cid = $company['company_id'];

						#Проверяем статус активности организации, если заблокирована - пропускаем
						if($company['lock']!=0){
							array_push($explain, '- Роль ID:'.$ident.' ['.$object['name'].'] не назначена в организации ID:'.$cid.' ['.$company['name'].'], т.к. организация заблокирована'.$comment);
							continue;
						}

						#Добавляем объект - роль в список доступа
						if($this->addObjectToList($list, $ident, $cid, $restrict, $explain, $comment) != true){
							$tree = array($ident);
							$this->calculateRoleTree($list, $tree, $ident, $cid, $restrict, $explain, $comment.' -> ');
						}

					}#Просмотриваем все организации
				}
				#Роль доступна только в определенной организации
				else{

					#Проверяем статус активности организации, если заблокирована - пропускаем
					if($company['lock']!=0){
						array_push($explain, '- Роль ID:'.$ident.' ['.$object['name'].'] не назначена в организации ID:'.$cid.' ['.$company['name'].'], т.к. организация заблокирована'.$comment);
						continue;
					}

					#Добавляем объект - роль в список доступа
					if($this->addObjectToList($list, $ident, $cid, $restrict, $explain, $comment) != true){
						$tree = array($ident);
						$this->calculateRoleTree($list, $tree, $ident, $cid, $restrict, $explain, $comment.' -> ');
					}
				}

			}
			#Если объект не является контейнером роли
			else{

				#Если текущая роль доступна во всех организациях
				if($company_id == 0){

					#Просмотриваем все организации
					foreach($this->companies as $company){

						$cid = $company['company_id'];

						#Проверяем статус активности организации, если заблокирована - пропускаем
						if($company['lock']!=0){
							array_push($explain, '- объект ID:'.$ident.' ['.$object['name'].'] не назначена в организации ID:'.$cid.' ['.$company['name'].'], т.к. организация заблокирована'.$comment);
							continue;
						}

						#Добавляем объект в список доступа
						$this->addObjectToList($list, $ident, $cid, $restrict, $explain, $comment);

					}#Просмотриваем все организации
				}
				#Роль доступна только в определенной организации
				else{

					#Проверяем статус активности организации, если заблокирована - пропускаем
					if($company['lock']!=0){
						array_push($explain, '- объект ID:'.$ident.' ['.$object['name'].'] не назначен в организации ID:'.$cid.' ['.$company['name'].'], т.к. организация заблокирована'.$comment);
						continue;
					}

					#Добавляем объект в список доступа
					$this->addObjectToList($list, $ident, $cid, $restrict, $explain, $comment);

				}

			}#Если объект не является контейнером роли

		}#Просмотр добавляемых объектов

		return $list;
	}#end function




	/* 
	 * Добавляет один ACL объект в основной список
	 * 
	 * $list - основной список объектов доступа
	 * $ident - идентификатор объекта, который требуется включить в основной список
	 * $company_id - организация, для которой назначается доступ
	 * $restrict - запрет доступа
	 * $explain - ссылка на массив объяснения доступа
	 * $comment - комментарий для EXPLAIN ACCESS
	 * 
	 * Структура записи основного списка объектов доступа
	 * 'uID'=>array(
	 * 	1,		//id объекта
	 * 	0,		//id организации
	 * 	1		//признак запрета
	 * )
	 * 
	 * Функция возвращает TRUE, если производится повторное добавление объекта, 
	 * во всех остальных случаях функция возвращает FALSE
	 */
	private function addObjectToList(&$list, $ident, $company_id, $restrict, &$explain, $comment=''){

		$object = $this->getObject($ident); #Получение объекта
		if(!is_array($object)){
			array_push($explain, '? Объект ID:'.$ident.' не найден'.$comment);
			return false;
		}

		if(!$this->companyExists($company_id)){
			array_push($explain, '- к объекту ID:'.$ident.' ['.$object['name'].'] не назначен в организации ID:'.$company_id.', т.к. организация не найдена'.$comment);
			return false;
		}

		$company = $this->companies[$company_id];

		#Проверяем статус активности организации, если заблокирована - пропускаем
		if($company['lock']!=0){
			array_push($explain, '- к объекту ID:'.$ident.' ['.$object['name'].'] не назначен в организации ID:'.$company_id.' ['.$company['name'].'], т.к. организация заблокирована'.$comment);
			return false;
		}

		$indx = 'u'.$ident.'c'.$company_id;

		#Объект не задана в основном массиве
		if(!isset($list[$indx])){
			$list[$indx] = array(
				$ident,			#ID Объекта
				$company_id,	#ID Организации
				$restrict		#Признак запрета
			);
			if(!$restrict)
				array_push($explain, '+ к объекту ID:'.$ident.' ['.$this->getObjectNameFromId($ident).'] в организации ID:'.$company_id.' ['.$company['name'].']'.$comment);
			else
				array_push($explain, '- запрет к объекту ID:'.$ident.' ['.$this->getObjectNameFromId($ident).'] в организации ID:'.$company_id.' ['.$company['name'].']'.$comment);
		}
		#Объект уже задан в основном массиве
		else{
			#Доступ к объекту еще не запрещен, но задан запрет
			if(!$list[$indx][2] && $restrict){
				$list[$indx][2] = $restrict;
				array_push($explain, '- запрет к объекту ID:'.$ident.' ['.$this->getObjectNameFromId($ident).'] в организации ID:'.$company_id.' ['.$company['name'].']'.$comment);
			}else{
				array_push($explain, '! повторно к объекту ID:'.$ident.' ['.$this->getObjectNameFromId($ident).'] в организации ID:'.$company_id.' ['.$company['name'].']'.$comment);
				return true;
			}
		}

		return false;
	}#end function




	/*
	 * Добавление элемента в массив доступа на основании дерева доступа
	 * 
	 * $list - основной список объектов доступа
	 * $tree - ссылка на массив дерева
	 * $object_id - родительский объект
	 * $company_id - организация, для которой назначается доступ
	 * $restrict - запрет доступа
	 * $explain - ссылка на массив объяснения доступа
	 * $comment - комментарий для EXPLAIN ACCESS
	 * 
	 * Функция работает рекурсивно, возврат значений не предусмотрен
	 * результаты работы функции записываются непосредственно в массивы $access и $tree
	 */
	private function calculateRoleTree(&$list, &$tree, $ident, $company_id, $restrict, &$explain, $comment){

		$object = $this->getObject($ident);
		if(!is_array($object)){
			array_push($explain, '? Объект ID:'.$ident.' не найден'.$comment);
			return;
		}

		#Просмотр дочерних объектов роли
		foreach($object['childs'] as $child_id){

			$child_object = $this->getObject($child_id);
			if(!is_array($object)){
				array_push($explain, '? Объект ID:'.$child_id.' не найден'.$comment.'/'.$object['name']);
				return;
			}

			#Если текущий дочерний объект в свою очередь также является ролью - делаем рекурсивный запрос
			#Перед рекурсивным запросом проверяем, в истории запросов отсутствие коллизий для исключения 
			#замкнутого цикла: родитель имеет в дочерних объектах своего родителя
			if($child_object['type'] == ACL_OBJECT_ROLE){
				if(in_array($child_id, $tree) === false){
					#Если в массиве доступов к объектам еще нет текущего объекта - добавляем его
					$this->addObjectToList($list, $child_id, $company_id, $restrict, $explain, $comment.'/'.$object['name']);
					array_push($tree, $child_id);
					$this->calculateRoleTree($list, $tree, $child_id,  $company_id, $restrict, $explain, $comment.'/'.$ident);
				}
			}else{
				$this->addObjectToList($list, $child_id, $company_id, $restrict, $explain, $comment.'/'.$object['name']);
			}

		}#Просмотр дочерних объектов роли

		return;
	}#end function





	/*
	 * Построение массива прав доступа пользователя
	 * 
	 * $user_id - идентификатор пользователя
	 * $source_name - имя источника данных из ACL sources
	 * $user_info - ассоциированный массив аттрибутов пользователя
	 * 
	 */
	public function getUserPrivs($user_id=0, $source_name='main', $user_info=null){

		#Источник данных
		if(!isset($this->options['sources'][$source_name]) || !is_array($this->options['sources'][$source_name])){
			return $this->doErrorEvent(11, __FUNCTION__, __LINE__); #Ошибка получения источника данных.
		}

		#Упрощающие жизнь переменные (сокращенный вид)
		$explain 		= array(); #Массив, объясняющий права доступа пользователя
		$user_groups	= array(); #Массив групп, в которые включен пользователь
		$user_objects	= array(); #Масисв объектов ACL, к которым пользователь имеет доступ
		$access			= array(); #Результирующий массив объектов ACL к которым пользователь имеет доступ, сгрупированный по организациям
		$source 		= $this->options['sources'][$source_name];
		$db				= (empty($source['db_link']) ? $this->db : Database::getInstance($source['db_link']));
		$table_access	= (isset($source['table_access'])? $source['table_access'] : null);
		$table_group	= (isset($source['table_group'])? $source['table_group'] : null);
		$table_auth		= $source['table_auth']; #Таблица базы данных с аутентификационной информацией пользователей
		$table_info		= $source['table_info']; #Таблица базы данных с дополнительной информацией по пользователю, если null - дополнительная информация не грузится

		#Получение аттрибутов пользователя
		if(empty($user_info)||!is_array($user_info)){

			#Получение информации о пользователе из таблицы аутентификации
			$db->prepare('SELECT * FROM ? WHERE ?=? LIMIT 1');
			$db->bind($table_auth, null, BIND_FIELD);
			$db->bind($source['field_auth_id'], null, BIND_FIELD);
			$db->bind($user_id, null, BIND_NUM);

			if( ($user_auth = $db->selectRecord()) === false ){
				return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
			}

			#Получение дополнительной информации о пользователе
			if(!empty($table_info)){
				$db->prepare('SELECT * FROM ? WHERE ?=? LIMIT 1');
				$db->bind($table_info, null, BIND_FIELD);
				$db->bind($source['field_auth_id'], null, BIND_FIELD);
				$db->bind($user_id, null, BIND_NUM);

				if( ($user_attr = $db->selectRecord()) === false ){
					return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
				}
			}else{
				$user_attr = array();
			}

			$user_info = array_merge($user_auth, $user_attr);

		}#Получение аттрибутов пользователя


		#BEGIN
		array_push($explain, 'i ACL BEGIN user_id=['.$user_id.'] FROM source=['.$source_name.']');

		#STEP 1:
		#Добавление групп доступа def_groups из источника данных в основной список
		array_push($explain, 'i STEP 1: Добавление групп доступа [def_groups] из источника данных ['.$source_name.']');
		if(isset($source['def_groups']) && is_array($source['def_groups'])){
			$this->addGroupsToList($user_groups, $source['def_groups'], $explain, ', из Source['.$source_name.'] -> [def_groups]');
		}

		#STEP 2:
		#Добавление объектов доступа def_roles из источника данных в основной список
		array_push($explain, 'i STEP 2: Добавление объектов доступа [def_roles] из источника данных Source['.$source_name.']');
		if(isset($source['def_roles']) && is_array($source['def_roles'])){
			$this->addObjectsToList($user_objects, $source['def_roles'], $explain, ', из Source['.$source_name.'] -> [def_roles]');
		}

		#STEP 3:
		#Обработка и применение правил доступа к пользователю
		array_push($explain, 'i STEP 3: Обработка и применение правил доступа [rules] из источника данных ['.$source_name.']');
		$this->calculateRules($source['rules'], $user_info, $user_groups, $user_objects, $explain, ', из Source['.$source_name.'] -> [rules]');

		#STEP 4:
		#Назначение пользователю групп доступа из базы данных
		array_push($explain, 'i STEP 4: Назначение пользователю групп доступа из базы данных');
		if( ($dbgroups = $this->dbLoadUserGroups($user_id, $db, $table_group)) !== false){

			#Проверка контрольной суммы
			if(!$this->ignore_hash_check && isset($user_info['hash_group'])){
				$hash_db	= $user_info['hash_group'];
				if(Hash::_checkHash($hash_db, $dbgroups)){
					array_push($explain, 'i Корректный Hash групп доступа DB.table_auth.hash_group = ['.$hash_db.']');
				}else{
					$emsg = 'Неправильный Hash групп доступа DB.table_auth.hash_group = ['.$hash_db.']';
					if(!$this->ignore_hash_error){
						#Контрольная сумма для групп доступа пользователя не верна. Доступ запрещен.
						return $this->doErrorEvent(404, __FUNCTION__, __LINE__, $emsg); 
					}else{
						array_push($explain, '! '.$emsg);
					}
				}
			}

			$this->addGroupsToList($user_groups, $dbgroups, $explain, ', из DB['.$db->connection.'] -> table_group['.$table_group.']');
		}



		#STEP 5:
		#Назначение пользователю прав доступа к объектам из базы данных
		array_push($explain, 'i STEP 5: Назначение пользователю прав доступа к объектам из базы данных');
		if( ($dbobjects = $this->dbLoadUserAccess($user_id, $db, $table_access)) !== false){

			#Проверка контрольной суммы
			if(!$this->ignore_hash_check && isset($user_info['hash_priv'])){
				$hash_db	= $user_info['hash_priv'];
				if(Hash::_checkHash($hash_db, $dbobjects)){
					array_push($explain, 'i Корректный Hash доступа к объектам DB.table_auth.hash_priv = ['.$hash_db.']');
				}else{
					$emsg = 'Неправильный Hash доступа к объектам DB.table_auth.hash_priv = ['.$hash_db.']';
					if(!$this->ignore_hash_error){
						#Контрольная сумма для прав доступа пользователя к объектам не верна. Доступ запрещен.
						return $this->doErrorEvent(405, __FUNCTION__, __LINE__, $emsg); 
					}else{
						array_push($explain, '! '.$emsg);
					}
				}
			}

			$this->addObjectsToList($user_objects, $dbobjects, $explain, ', из DB['.$db->connection.'] -> table_group['.$table_access.']');
		}


		#STEP 6:
		#Фильтрация и группировка по организациям объектов доступа, получение результирующего массива
		array_push($explain, 'i STEP 6: Фильтрация и группировка по организациям объектов доступа, получение результирующего массива');
		$access = $this->getFinalUserAccess($source['acl_subject'], $user_groups, $user_objects, $explain, ', из Source['.$source_name.']');

		#END
		array_push($explain, 'i ACL END');

		#Возвращаем массив прав доступа пользователя
		return array(
			'explain'	=> $explain,
			'user'		=> $user_info,
			'access'	=> $access['access'],
			'acl'		=> $access['acl'],
			'groups'	=> $access['groups']
		);
	}#end function



	/*
	 * Фильтрация и группировка по организациям объектов доступа, получение результирующего массива доступов пользователя
	 * 
	 * $acl_subject - Линейный массив перечня субьектов ACL, которые могут быть авторизованы
	 * $user_groups - Массив групп, в которые включен пользователь или исключен из них
	 * $user_objects - Масисв объектов ACL, к которым пользователь имеет доступ или установлен запрет на доступ
	 * $explain - ссылка на массив объяснения доступа
	 * $comment - комментарий для EXPLAIN ACCESS
	 */
	private function getFinalUserAccess($acl_subject=array(), $user_groups=array(), $user_objects=array(), &$explain, $comment=''){

		if(!is_array($acl_subject)) $acl_subject = array();

		$is_client = in_array(ACL_SUBJECT_CLIENT, $acl_subject);

		$result = array(
			'acl' => array(
				'is_admin'		=> null,
				'is_developer'	=> null,
				'is_client' 	=> $is_client,
				'is_user'		=> !$is_client
			),
			'groups' => array(),
			'access' => array()
		);

		#Просмотриваем все организации
		foreach($this->companies as $company){

			if($company['lock']!=0) continue;
			$result['access']['c'.$company['company_id']] = array();

		}#Просмотриваем все организации

		#Если не задан массив групп доступа или не задан массив объектов доступа 
		#или не задан массив ACL субъектов, которые могут быть авторизованы
		#Считаем, что пользователь не наделен никакими правами
		if(empty($user_groups)||empty($user_objects)||empty($acl_subject)) return $result;


		#Подготовка линейного результирующего массива групп, в которые входит пользователь
		#Исключая запрещенные группы
		foreach($user_groups as $item){
			if($item[1] == 0) $result['groups'][] = $item[0];
		}


		#Просмотр всех объектов, указанных в массиве
		foreach($user_objects as $item){

			#Фильтр запретов
			if($item[2] != 0) continue;

			#Фильтр организации
			if(!isset($result['access']['c'.$item[1]])) continue;

			#Получение объекта ACL
			$object = $this->getObject($item[0]);
			if(!is_array($object)) continue;
			$object_id = $object['object_id'];

			#Проверка вхождения ACL объекта и пользователя в одну группу доступа
			#Пример:
			#Пользователь входит в группы А, Б и В
			#Объект доступен в группах А и Д
			#Пользователь получит доступ к объекту, т.к. и пользователь и объект входят в группу А
			$intersect = array_intersect($result['groups'], $object['groups']);
			if(!count($intersect)){
				array_push($explain, '- не в группе с объектом ID:'.$object_id.' ['.$object['name'].']');
				continue;
			}

			#Специализированные роли (разработчик и/или администратор)
			#При этом субьект с признаком клиента не может иметь роль администратора иди разработчика
			if(($object_id == $this->developer_role_id || $object_id == $this->admin_role_id) && $is_client === false){

				if($object_id == $this->developer_role_id && $result['acl']['is_developer'] === null){
					$result['acl']['is_developer'] = in_array(ACL_SUBJECT_DEVELOPER, $acl_subject);
					if($result['acl']['is_developer']===false) array_push($explain, '- специальная роль ACL_SUBJECT_DEVELOPER не доступна'.$comment);
				}
				if($object_id == $this->admin_role_id && $result['acl']['is_admin'] === null){
					$result['acl']['is_admin'] = in_array(ACL_SUBJECT_ADMIN, $acl_subject);
					if($result['acl']['is_admin']===false) array_push($explain, '- специальная роль ACL_SUBJECT_ADMIN не доступна'.$comment);
				}

			}

			#Добавляем объект в список доступов пользователя
			$result['access']['c'.$item[1]][] = $object_id;

		}#Просмотр всех объектов, указанных в массиве


		if($result['acl']['is_admin'] == null) $result['acl']['is_admin'] = false;
		if($result['acl']['is_developer'] == null) $result['acl']['is_developer'] = false;

		return $result;
	}#end function





















	/*==============================================================================================
	Правила
	==============================================================================================*/


	/*
	 * --------------------------------------------------
	 * Обработка и применение правил доступа к пользователю
	 * --------------------------------------------------
	 * Правила, при выполнении которых пользователям назначаются определенные права доступа или пользователь включается в какие-нибудь группы
	 * Идея "правил" состоит в том, чтобы наделить пользователя некоторыми правами, если значения атрибутов пользователя соответствуют заданным условиям
	 * аттрибутами считаются обеъединенные в единый массив поля таблиц table_auth и table_info, если она задана.
	 * Например, можно задать правило, при котором пользователь с логином 'admin' автоматически получает некую роль 'role.admin.test' и 'role.admin.test2',
	 * а также будет включен в группы: 'test1' и 'test2'
	 * Правило будет выглядеть следующим образом:
	 * 'rule_name' => array(
	 * 		'params' => array(	//Массив параметров, при успешном выполнении которых сработает правило
	 * 			array(
	 * 				'key' 		=> 'login',	# Имя поля в таблице table_auth или table_info для получения значения атрибута
	 * 				'value'		=> array('aaa','sss')		-	Значение, сравниваемое со значением атрибута, 
	 * 															если значение задано массивом, тогда правило сработает если один из элементов массива 
	 * 			 												соответствует значению аттрибута (по принципу OR)
	 * 															если значение задано массивом и задан оператор "!=","<>", то правило сработает если все элементы массива 
	 * 															не соответствуют значению аттрибута (по принципу AND)
	 * 				'operator'	=> 'LIKE%'	# Оператор, применяемый для выполнения сравнения, возможные операторы:
	 * 										# "="			- РАВНО, правило сработает если текстовые строки или числовые значения равны, используется по-умолчанию
	 * 										# "!=","<>"		- НЕ РАВНО, правило сработает если текстовые строки или числовые значения НЕ равны
	 * 										# ">"			- БОЛЬШЕ, правило сработает если аттрибут пользователя больше value
	 * 										# "<"			- МЕНЬШЕ, правило сработает если аттрибут пользователя меньше value
	 * 										# ">="			- БОЛЬШЕ ИЛИ РАВНО, правило сработает если аттрибут пользователя больше или равен value
	 * 										# "<="			- МЕНЬШЕ ИЛИ РАВНО, правило сработает если аттрибут пользователя меньше или равен value
	 * 										# "LIKE"		- СООТВЕТСТВУЕТ без учета регистра, сравнение текстовых значений через strcasecmp(), правило сработает если строки идентичны
	 * 										# "LIKE%"		- НАЧИНАЕТСЯ С без учета регистра, правило сработает если аттрибут пользователя начинается со значения value
	 * 										# "%LIKE"		- ЗАКАНЧИВАЕТСЯ без учета регистра, правило сработает если аттрибут пользователя заканчивается значением value
	 * 										# "%LIKE%"		- СОДЕРЖИТ без учета регистра, правило сработает если аттрибут пользователя содержит значение value
	 * 										# "CASELIKE"	- СООТВЕТСТВУЕТ с учетом регистра, сравнение текстовых значений через strcmp(), правило сработает если строки идентичны
	 * 										# "CASELIKE%"	- НАЧИНАЕТСЯ С с учетом регистра, правило сработает если аттрибут пользователя начинается со значения value
	 * 										# "%CASELIKE"	- ЗАКАНЧИВАЕТСЯ с учетом регистра, правило сработает если аттрибут пользователя заканчивается значением value
	 * 										# "%CASELIKE%"	- СОДЕРЖИТ с учетом регистра, правило сработает если аттрибут пользователя содержит значение value
	 * 			)
	 * 		),
	 * 		'roles' => array(	//Массив прав доступа, назначаемый пользователю при сработке правила
	 * 			array('role.admin.test',0, 0),		//Объект доступа (в примере - роль) и организация (0 - все организации)
	 * 			array('role.admin.test2',0, 0),	//Объект доступа (в примере - роль) и организация (0 - все организации)
	 * 		),
	 * 		'groups' => array('test1','test2')	//Линейный массив названий групп, в которые будет включен пользователь
	 * )
	 * 
	 * Еще пример: для клиентов, чей пол - женский (поле sex из таблицы table_info)
	 * и клиент - физическое лицо (поле type из таблицы table_info),
	 * предоставлять доступ на страницу поздравлений в организации ID 1:
	 * 'test_rule' => array(
	 * 		'param'=>array(
	 * 			array('attr'=>'sex', 'value'=>'female', 'operator'=>'='),
	 * 			array('attr'=>'type', 'value'=>'person')
	 * 		),
	 * 		'roles'=>array(array('page.female.holiday',1))
	 * )
	 */
	public function calculateRules($rules=array(), $user_info=array(), &$user_groups, &$user_objects, &$explain, $comment=''){

		#Правила в источнике данных не заданы
		if(!is_array($rules)||empty($rules)) return false;

		#Просмотр правил
		foreach($rules as $rulename=>$rule){

			#Если параметры правила не заданы - пропускаем это правило и переходим к другому
			if(!isset($rule['params'])||empty($rule['params'])||!is_array($rule['params'])) continue;

			#Обработка правил
			if(self::compareRulesArray($rule['params'], $user_info) === false) continue;

			array_push($explain, 'i сработало правило ['.$rulename.']'.$comment);

			#Правило обработано успешно - добавляем группы доступа, если заданы
			if(isset($rule['groups'])&&is_array($rule['groups'])) 
				$this->addGroupsToList($user_groups, $rule['groups'], $explain, $comment.' -> ['.$rulename.']');

			#Правило обработано успешно - добавляем доступ к объектам, если объекты заданы
			if(isset($rule['roles'])&&is_array($rule['roles']))
				$this->addObjectsToList($user_objects, $rule['roles'], $explain, $comment.' -> ['.$rulename.']');

		}#Просмотр правил

		return true;
	}#end function











	/*==============================================================================================
	Функции работы с базой данных:
	Загрузка информации по пользователю сведений о доступах, группах доступа,
	==============================================================================================*/



	/*
	 * Получение групп доступа, в которые включен пользователь
	 */
	private function dbLoadUserGroups($user_id=0, $db=null, $table_group=''){

		#Если не задана таблица групп для пользователя, возвращаем пустой массив
		if(empty($db)||empty($table_group)) return array();

		#Получение данных по дочерним элементам контейнеров (ролей)
		$db->prepare('SELECT ?,? FROM ? WHERE ?=?');
		$db->bind('group_id', null, BIND_FIELD);
		$db->bind('restrict', null, BIND_FIELD);
		$db->bind($table_group, null, BIND_FIELD);
		$db->bind('user_id', null, BIND_FIELD);
		$db->bind($user_id,null, BIND_NUM);

		if( ($list = $db->select(null, null, DB_NUM))===false){
			return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса;
		}

		return $list;
	}#end function





	/*
	 * Получение массива контейнеров и объектов, к которым имеет доступ текущий пользователь
	 */
	private function dbLoadUserAccess($user_id=0, $db=null, $table_access=''){

		#Если не задана таблица групп для пользователя, возвращаем пустой массив
		if(empty($db)||empty($table_access)) return array();

		#Получение данных по дочерним элементам контейнеров (ролей)
		$db->prepare('SELECT ?,?,? FROM ? WHERE ?=?');
		$db->bind('object_id', null, BIND_FIELD);
		$db->bind('company_id', null, BIND_FIELD);
		$db->bind('restrict', null, BIND_FIELD);
		$db->bind($table_access, null, BIND_FIELD);
		$db->bind('user_id', null, BIND_FIELD);
		$db->bind($user_id,null, BIND_NUM);

		if( ($list = $db->select(null, null, DB_NUM))===false){
			return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса;
		}

		return $list;
	}#end function



	/*
	 * Проверка необходимости обновления прав доступа для пользователя
	 * 
	 * $user_id - идентификатор пользователя
	 * $source_name - имя источника данных
	 * $update_check - признак, указывающий на необходимость обновления в сессии времени последней проверки необходимости обновления прав доступа
	 */
	private function dbUserAccessNeedUpdate($user_id=0, $source_name='', $update_check=true){

		if($this->user_update_ckeck) return $this->user_need_update;

		#Источник данных
		if(empty($user_id) || empty($this->options['sources'][$source_name])){
			return $this->doErrorEvent(16, __FUNCTION__, __LINE__); #Ошибка получения информации пользователя из сессии
		}

		$source = $this->options['sources'][$source_name];

		if(!is_array($source)){
			return $this->doErrorEvent(17, __FUNCTION__, __LINE__, $source); #Ошибка получения источника данных из сессии
		}

		$db					= (empty($source['db_link']) ? $this->db : Database::getInstance($source['db_link']));
		$table_auth			= $source['table_auth']; #Таблица базы данных с аутентификационной информацией пользователей
		$field_id			= $source['field_auth_id']; #Поле таблицы, в котором хранится идентификатор пользователя
		$field_need_update	= $source['field_auth_needupdate']; #Поле таблицы, в котором хранится признак необходимости перегрузить права доступа пользователя

		if(empty($table_auth)||empty($field_id)||empty($field_need_update)) return false;

		$db->prepare('SELECT ? FROM ? WHERE ?=? LIMIT 1');
		$db->bind($field_need_update, null, BIND_FIELD);
		$db->bind($table_auth, null, BIND_FIELD);
		$db->bind($field_id, null, BIND_FIELD);
		$db->bind($user_id,null, BIND_NUM);

		if(($need_update = $db->result()) === false){
			return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
		}

		if($update_check == true){
			Session::_setMd(array('acl','last_check'),time());
		}

		return ($need_update != 0);
	}#end function















	/*==============================================================================================
	АУТЕНТИФИКАЦИЯ И ПРОВЕРКА ПРАВ ДОСТУПА
	==============================================================================================*/



	/*
	 * Аутентификация пользователя на основании указанных логина и пароля
	 */
	public function userAuth($login='', $password=''){

		#Загрузка массива объектов из базы данных
		if(!$this->dbLoadAll()) return false;

		$company_id=0;
		$login=trim($login);
		$password=trim($password);

		#Проверка логина и пароля
		if(empty($login) || empty($password)){
			return $this->doErrorEvent(110, __FUNCTION__, __LINE__); #Не задано Имя пользователя и/или пароль
		}

		#Просмотр источников аутентификации пользователей
		foreach($this->options['sources'] as $sourcename=>$source){

			#Если источник данных не активен - аутентификацию из него проводить не будем
			if($source['active'] == false) continue;

			#Сведения об источнике данных
			$db					= (empty($source['db_link']) ? $this->db : Database::getInstance($source['db_link'])); #Ссылка на объект класса ваимодействия с базой данных, если не задана, используется основная ссылка $this->options['db_link']
			$table_auth			= $source['table_auth']; #Таблица базы данных с аутентификационной информацией пользователей
			$table_info			= $source['table_info']; #Таблица базы данных с дополнительной информацией по пользователю, если null - дополнительная информация не грузится
			$field_id			= $source['field_auth_id']; #Поле таблицы, в котором хранится идентификатор пользователя
			$field_login		= $source['field_auth_login']; #Поле таблицы, в котором хранится имя пользователя
			$field_pass			= $source['field_auth_pass']; #Поле таблицы, в котором хранится пароль пользователя
			$field_name			= $source['field_auth_name']; #(*) Поле таблицы, в котором хранится имя пользователя
			$field_status		= $source['field_auth_status']; #Поле таблицы, в котором хранится статсу учетной записи пользователя, 0 - учетная запись заблокирована
			$field_last_company	= $source['field_auth_lastcompany']; #Поле таблицы, в котором хранится последняя активная организация, с которой работал пользователь
			$field_last_login	= $source['field_auth_lastlogin']; #Поле таблицы, в котором хранится datetime последней аутентификации 
			$field_last_ip		= $source['field_auth_lastip']; #Поле таблицы, в котором хранится IP адрес последней аутентификации 
			$field_last_update	= $source['field_auth_lastupdate']; #Поле таблицы, в котором хранится datetime последнего обновления прав доступа
			$field_need_update	= $source['field_auth_needupdate']; #Поле таблицы, в котором хранится признак необходимости перегрузить права доступа пользователя

			#Аутентификация пользователя по логину и паролю
			#Пароль проверяется как текст, как хеш MD5 и как хеш SHA1
			$db->prepare('SELECT * FROM ? WHERE ?=? AND ? IN(?,?,?) LIMIT 1');
			$db->bind($table_auth, null, BIND_FIELD);
			$db->bind($field_login, null, BIND_FIELD);
			$db->bind($login);
			$db->bind($field_pass, null, BIND_FIELD);
			$db->bind($password);
			$db->bind(md5($password));
			$db->bind(sha1($password));

			#Получение информации о пользователе из таблицы аутентификации
			if(($user_auth = $db->selectRecord()) === false ){
				#Если при выполнении запроса произошла ошибка - выход
				return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
			}

			#Не найдена запись с указанным именем пользователя и паролем
			if(empty($user_auth) || !is_array($user_auth)) continue;

			#Получение дополнительной информации о пользователе
			if(!empty($table_info)){
				$db->prepare('SELECT * FROM ? WHERE ?=? LIMIT 1');
				$db->bind($table_info, null, BIND_FIELD);
				$db->bind($field_id, null, BIND_FIELD);
				$db->bind($user_auth[$field_id],null,BIND_NUM);
				if(($user_attr = $db->selectRecord()) === false ){
					return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
				}
			}else{
				$user_attr = array();
			}

			#Информация о пользователе
			$user_info = array_merge($user_auth, $user_attr);

			if(!empty($field_status)){
				#Если учетная запись пользователя заблокирована
				if($user_auth[$field_status] == 0){
					return $this->doErrorEvent(130, __FUNCTION__, __LINE__); #Учетная запись заблокирована
				}
			}

			#Проверка контрольной суммы
			if(!$this->ignore_hash_check && isset($user_auth['hash_user'])){
				$hash_db = $user_auth['hash_user'];
				$hash_data	= $user_auth[$field_id].$user_auth[$field_login].$user_auth[$field_pass].$user_auth[$field_name];
				if(!Hash::_checkHash($hash_db, $hash_data)){
					$emsg = 'Неправильный Hash пользователя user_id=['.$user_auth[$field_id].'] DB.table_auth.hash_user = ['.$hash_db.']';
					if(!$this->ignore_hash_error){
						return $this->doErrorEvent(401, __FUNCTION__, __LINE__, $emsg); 
					}
				}
			}

			#Старт сессии
			if($this->checkSessionStatus(true)===false){
				return $this->doErrorEvent(15, __FUNCTION__, __LINE__); #Внутренняя ошибка: Ошибка работы с сессией.
			}

			#Дополнительные аттрибуты
			$user_info['user_ip']		= Session::_getIP(true);	#получение IP адреса: REMOTE_ADDR
			$user_info['user_proxy_ip']	= Session::_getIP(false);	#получение IP адреса прокси (если есть)

			#Аутентификация прошла успешно,
			#Авторизация пользователя и наделение правами доступа
			if(($access = $this->getUserPrivs($user_auth[$field_id], $sourcename, $user_info)) === false) return false;

			#Текущая организация
			$company_id = $user_auth[$field_last_company];
			$reselect_company = false;

			$access['acl']['user_id']			= $user_auth[$field_id];
			$access['acl']['user_login']		= $user_auth[$field_login];
			$access['acl']['user_name']			= $user_auth[$field_name];
			$access['acl']['auth_time']			= time();	#Время аутентификации пользователя
			$access['acl']['last_update']		= time();	#Время последнего обновления прав доступа
			$access['acl']['last_check']		= time();	#Время последней проверки на необходимость обновления прав доступа
			$access['acl']['source_name']		= $sourcename;	#Имя источника данных
			$access['acl']['need_update']		= false;		#Признак необходимости обновления прав доступа пользователя
			$access['acl']['active_company']	= $company_id;	#Активная организация
			$access['acl']['explain']			= $access['explain'];


			#Проверка доступа пользователя к организации,
			#Если организация не найдена или доступ отсутствует - выбор первой организации, к которой у пользователя имеется доступ
			if(!$this->companyExists($company_id)) $reselect_company = true;

			if($access['acl']['is_admin'] != true){
				if(!$reselect_company && empty($access['access']['c'.$company_id])) $reselect_company = true;
			}

			#Выбор организации
			if($reselect_company){
				$company_id = $this->userGetFirstCompany($access['acl'], $access['access']);
			}

			#Если у пользователя нет доступа ни к одной из организаций - ошибка
			if($company_id === false){
				return $this->doErrorEvent(145, __FUNCTION__, __LINE__); #Для Вашей учетной записи не были присвоены какие-либо права доступа. Обратитесь к администратору.
			}

			$access['acl']['active_company']	= $company_id;	#Активная организация
			$access['acl']['company_name']		= $this->companies[$company_id]['name'];	#Активная организация, название
			$access['acl']['hash_access']		= $this->getSessionHash($access['access']); #Контрольная сумма массива прав доступа в сессии

			#Запись в сессию результатов авторизации
			Session::_set('user', $user_info);
			Session::_set('acl', $access['acl']);
			Session::_set('access', $access['access']);

			#Аутентификация прошла успешно,
			#Вызов события об успешной аутентификации пользователя
			Event::getInstance()->fireEvent(EVENT_ACL_USER_AUTH, array(
				'auth_time'			=> $access['acl']['auth_time'],
				'user_id'			=> $user_auth[$field_id],
				'user_login'		=> $user_auth[$field_login],
				'user_ip'			=> $user_info['user_ip'],
				'user_proxy_ip'		=> $user_info['user_proxy_ip'],
				'active_company'	=> $user_auth[$field_last_company],
				'is_client'			=> $access['acl']['is_client'],
				'is_admin'			=> $access['acl']['is_admin'],
				'is_developer'		=> $access['acl']['is_developer'],
				'user_info'			=> $user_info,
				'user_source_name'	=> $sourcename
			));

			$arr_set = array();
			if(!empty($field_last_login)) $arr_set[$field_last_login] = date('Y-m-d H:i:s',time());
			if(!empty($field_last_ip)) $arr_set[$field_last_ip] = $user_info['user_ip'];
			if(!empty($field_last_update)) $arr_set[$field_last_update] = date('Y-m-d H:i:s',time());
			if(!empty($field_last_company)) $arr_set[] = array($field_last_company, $company_id, BIND_NUM);
			if(!empty($field_need_update)) $arr_set[] = array($field_need_update, 0, BIND_NUM);

			#Запись сведений о последней удачной авторизации
			if(count($arr_set) > 0){
				$sql = $db->buildUpdate($table_auth, $arr_set, array(array($field_id,$user_auth[$field_id],BIND_NUM)));
				if( $db->update($sql) === false){
					return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
				}
			}

			#Успешная аутентификация
			return true;

		}#foreach $this->options[sources]

		return $this->doErrorEvent(120, __FUNCTION__, __LINE__); #Имя пользователя и/или пароль указано неверно

	}#end function




	/*
	 * Проверяет доступ текущего пользователя к ACL объекту в указанной орагнизации
	 * 
	 * $object_id - идентификатор объекта ACL
	 * $company_id - идентификатор организации, если не задан, используется текущая активная организация
	 * $ignore_lock - признак игнорирования признака блокировки объекта
	 * 
	 * Возвращает TRUE, если пользователю разрешен доступ к указанному объекту
	*/
	public function userAccess($object_id=0, $company_id=0, $ignore_lock=false){

		#Загрузка массива объектов из базы данных
		if(!$this->dbLoadAll()) return false;

		$object = $this->getObject($object_id);
		if(!is_array($object)){
			return $this->doErrorEvent(10, __FUNCTION__, __LINE__); #Внутренняя ошибка: Объект не существует
		}

		#Если тип объекта - ACL_OBJECT_INTERNAL (Внутренний абстрактный объект, через котороый доступ предоставляется любому пользователю независимо от его прав)
		#Возвращаем TRUE - доступ разрешен
		if($object['type'] == ACL_OBJECT_INTERNAL) return true;

		#Проверка сессии, факта аутентификации пользователя
		if(!$this->userSessionCheck()) return false;

		$acl = Session::_get('acl');
		$access = Session::_get('access');

		#Идентификатор текущей организации
		if(empty($company_id)) $company_id = $acl['active_company'];


		#Проверка признака необходимости обновления прав доступа к объектам для пользователей
		if($this->userNeedUpdate()){
			if(!$this->userPrivUpdate())return false;
		}

		if(empty($access['c'.$company_id]) || !is_array($access['c'.$company_id])){
			return $this->doErrorEvent(150, __FUNCTION__, __LINE__); #Недостаточно прав
		}


		#Проверка наличия доступа пользователя к объекту в рамках выбранной организации
		if($acl['is_admin'] != true){
			if(in_array($object['object_id'], $access['c'.$company_id])===false){
				return $this->doErrorEvent(150, __FUNCTION__, __LINE__); #Недостаточно прав
			}
		}

		#Проверка статуса объекта на блокировку
		if(!$acl['is_developer'] && !$ignore_lock && $object['lock']!=0){
			return $this->doErrorEvent(170, __FUNCTION__, __LINE__); #Доступ к данному объекту был временно ограничен администратором
		}

		return true;
	}#end function



	/*
	 * Проверяет, нужно ли обновлять права доступа текущему пользователю
	 */
	public function userNeedUpdate(){

		if($this->user_update_ckeck) return $this->user_need_update;

		#Проверка сессии, факта аутентификации пользователя
		if(!$this->userSessionCheck()) return false;
		$acl = Session::_get('acl');

		#Проверка признака необходимости обновления прав доступа к объектам для пользователей
		#Выполняем проверку не чаще чем раз в пять секунд
		#Это сделано для предотвращения большого количества запросов к базе,
		#потому что userAccess может несколько раз вызываться в рамках одного скрипта
		$need_update = false;
		if(time()-$acl['last_check'] > 4){

			if($this->xcache&&XCache::_exists('acl/last_update')){
				$last_update = XCache::_get('acl/last_update');
				$need_update = ($acl['last_update'] > $last_update ? false : true);
			}

			if(!$need_update) $need_update = $this->dbUserAccessNeedUpdate($acl['user_id'], $acl['source_name']);

		}

		$this->user_update_ckeck = true;

		#Если в опциях класса установлено автоматическое обновление массива доступов пользователя и 
		#время таймаута истекло или из базы данных получена информация о необходимости обновления,
		#происходит запуск механизма обновления массива доступа
		if(($this->options['access_timeout'] != 0 && (time()-$acl['last_update'] > $this->options['access_timeout'])) || $need_update == true || $acl['need_update'] == true){
			$this->user_need_update = true;
			return true;
		}

		$this->user_need_update = false;
		return false;
	}#end function



	/*
	 * Обновление прав доступа текущего пользователя
	 */
	public function userPrivUpdate(){

		#Проверка сессии, факта аутентификации пользователя
		if(!$this->userSessionCheck()) return false;

		$acl = Session::_get('acl');

		#Источник данных
		if(empty($this->options['sources'][$acl['source_name']])){
			return $this->doErrorEvent(16, __FUNCTION__, __LINE__); #Ошибка получения информации пользователя из сессии
		}

		$source = $this->options['sources'][$acl['source_name']];

		if(!is_array($source)){
			return $this->doErrorEvent(17, __FUNCTION__, __LINE__); #Ошибка получения источника данных из сессии
		}

		$db					= (empty($source['db_link']) ? $this->db : Database::getInstance($source['db_link']));
		$table_auth			= $source['table_auth']; #Таблица базы данных с аутентификационной информацией пользователей
		$field_id			= $source['field_auth_id']; #Поле таблицы, в котором хранится идентификатор пользователя
		$field_last_update	= $source['field_auth_lastupdate']; #Поле таблицы, в котором хранится datetime последнего обновления прав доступа
		$field_need_update	= $source['field_auth_needupdate']; #Поле таблицы, в котором хранится признак необходимости перегрузить права доступа пользователя


		#Получение прав доступа пользователя
		if(($access = $this->getUserPrivs($acl['user_id'], $acl['source_name'], null)) === false) return false;


		$arr_set = array();
		if(!empty($field_last_update)) $arr_set[$field_last_update] = date('Y-m-d H:i:s',time());
		if(!empty($field_need_update)) $arr_set[] = array($field_need_update, 0, BIND_NUM);

		#Запись сведений о последней удачной авторизации
		if(count($arr_set) > 0){
			$sql = $db->buildUpdate($table_auth, $arr_set, array(array($field_id, $acl['user_id'],BIND_NUM)));
			if( $db->update($sql) === false){
				return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
			}
		}

		$acl['last_update']	= time();	#Время последнего обновления прав доступа
		$acl['need_update']	= false;	#Признак необходимости обновления прав доступа пользователя
		$acl['explain']		= $access['explain'];
		$acl['hash_access'] = $this->getSessionHash($access['access']);
		$acl = array_merge($acl, $access['acl']);

		#Запись в сессию результатов авторизации
		Session::_set('user', $access['user']);
		Session::_set('acl', $acl);
		Session::_set('access', $access['access']);

		#Текущая организация
		$company_id = $acl['active_company'];
		$reselect_company = false;

		#Проверка доступа пользователя к организации,
		#Если организация не найдена или доступ отсутствует - выбор первой организации, к которой у пользователя имеется доступ
		if(!$this->companyExists($company_id)) $reselect_company = true;

		if($acl['is_admin'] != true){
			if(!$reselect_company && empty($access['access']['c'.$company_id])) $reselect_company = true;
		}

		#Выбор организации
		if($reselect_company){
			$company_id = $this->userGetFirstCompany($acl, $access['access']);
		}

		#Если у пользователя нет доступа ни к одной из организаций - ошибка
		if($company_id === false){
			return $this->doErrorEvent(145, __FUNCTION__, __LINE__); #Для Вашей учетной записи не были присвоены какие-либо права доступа. Обратитесь к администратору.
		}

		#Проверка актуальности доступа к текущей организации
		if($acl['active_company']!=$company_id && !$this->userCompanyChange($company_id)) return false;

		return true;
	}#end function





	/*
	 * Расчет контрольной суммы для прав доступа, записываемых в сессию
	 * 
	 * $access - массив прав доступа
	 */
	private function getSessionHash($access=array()){

		#Расчет контрольной суммы выполняется путем "суммирования" идентификатора сессии session_id и 
		#массива прав доступа, присвоенных пользователю в рамках данной сессии по алгоритму sha1
		#Поскольку это исключительно внутренний механизм проверки целостности, по-умолчанию подпись ЭЦП не используется,
		#но может быть включена при необходимости, указанием имени соответствующего сертификата
		$hash_calc	= Session::_get('session_id') . Hash::_toString($access, null, null);

		return Hash::_getHash($hash_calc, null, null, $this->sess_priv_rsa);
	}#end function




	/*==============================================================================================
	Работа с организациями для текущего пользователя
	==============================================================================================*/


	/*
	 * Проверяет, имеет ли пользователь какие-либо права в определенной организации
	 */
	public function userCheckAccessToCompany($company_id=0){

		#Проверка сессии, факта аутентификации пользователя
		if(!$this->userSessionCheck()) return false;
		$acl = Session::_get('acl');
		$access = Session::_get('access');

		if($acl['is_admin']) return true;
		if(!empty($access['c'.$company_id]) && is_array($access['c'.$company_id])) return true;

		return false;
	}#end function



	/*
	 * Возвращает первую организацию, к которой у пользователя есть права доступа
	 */
	public function userGetFirstCompany($acl=null, $access=null){

		#Если массив доступов пользователя не задан
		if(empty($access)||empty($acl)){
			#Проверка сессии, факта аутентификации пользователя
			if(!$this->userSessionCheck()) return false;
			$acl = Session::_get('acl');
			$access = Session::_get('access');
		}

		foreach($this->companies as $company){
			$company_id = $company['company_id'];
			if($acl['is_admin']) return $company_id;
			if(!empty($access['c'.$company_id]) && is_array($access['c'.$company_id])) return $company_id;
		}

		return false;
	}#end function



	/*
	 * Смена организации
	 */
	public function userCompanyChange($company_id=0){

		#Проверка существования организации с указанным идентификатором
		if(!$this->companyExists($company_id)){
			return $this->doErrorEvent(9, __FUNCTION__, __LINE__); #Внутренняя ошибка: Выбранная организация не существует
		}

		#Если нет прав в выбранной организации - вернуть ошибку
		if(!$this->userCheckAccessToCompany($company_id)){
			return $this->doErrorEvent(140, __FUNCTION__, __LINE__); #Отсутствуют какие-либо права для работы в выбранной организации
		}

		$acl = Session::_get('acl');
		$access = Session::_get('access');

		#Источник данных
		if(empty($this->options['sources'][$acl['source_name']])){
			return $this->doErrorEvent(16, __FUNCTION__, __LINE__); #Ошибка получения информации пользователя из сессии
		}

		$source = $this->options['sources'][$acl['source_name']];

		if(!is_array($source)){
			return $this->doErrorEvent(17, __FUNCTION__, __LINE__); #Ошибка получения источника данных из сессии
		}

		$db					= (empty($source['db_link']) ? $this->db : Database::getInstance($source['db_link']));
		$table_auth			= $source['table_auth']; #Таблица базы данных с аутентификационной информацией пользователей
		$field_id			= $source['field_auth_id']; #Поле таблицы, в котором хранится идентификатор пользователя
		$field_last_company	= $source['field_auth_lastcompany']; #Поле таблицы, в котором хранится последняя активная организация, с которой работал пользователь

		$arr_set = array();
		if(!empty($field_last_company)) $arr_set[] = array($field_last_company, $company_id, BIND_NUM);

		#Запись сведений о последней удачной авторизации
		if(count($arr_set) > 0){
			$sql = $db->buildUpdate($table_auth, $arr_set, array(array($field_id, $acl['user_id'],BIND_NUM)));
			if( $db->update($sql) === false){
				return $this->doErrorEvent(1, __FUNCTION__, __LINE__); #Ошибка во время выполнения SQL запроса
			}
		}


		#Запись в сессию информации о выбранной организации
		Session::_setMd(array('acl','company_name'),$this->companies[$company_id]['name']);

		#Установление выбранной организации в качестве активной
		Session::_setMd(array('acl','active_company'),$company_id);

		return true;
	}#end function




















	/*=====================================================================================================================================
	Работа с текущем пользователем
	======================================================================================================================================*/



	/*
	 * Возвращает перечень доступных объектов для текущего пользователя по определенным параметрам
	 * 
	 * Принимает аргументы:
	 * $filter - ассоциированный массив для фильтрации массива
	 * $company_id - организация для которой надо вернуть перечень объектов, если не задана, то используется текущая активная организация из сессии
	 * $ignore_admin_priv - признак, указывающий что необходимо игнорировать права администратора у пользователя 
	 * и проводить поиск не по все объектам, а только по реально назначенному списку (как если бы пользователь не был администратором)
	 * 
	 * Возвращает массив найденных объектов
	 */
	public function getUserObjects($filter=array(), $company_id=0, $ignore_admin_priv=false){

		#Массив результатов
		$objects=array();

		#Проверка сессии, факта аутентификации пользователя
		if(!$this->userSessionCheck()) return false;
		$acl = Session::_get('acl');
		$access = Session::_get('access');

		if(!$this->dbLoadAll()) return $objects;

		#Если пользователь не имеет роль разработчика, 
		#и явно не указано, что требуется игнорирование блокированных объектов - 
		#добавляем в фильтр возврат только активных объектов
		if(!$acl['is_developer'] && !isset($filter['lock'])) $filter['lock'] = 0;

		#Если текущий пользователь администратор, возвращаем все объекты данного типа
		if($acl['is_admin'] && !$ignore_admin_priv) return $this->searchObjects($filter);

		#Текущая организация
		if(empty($company_id)) $company_id = $acl['active_company'];

		#Проверка массива пользовательских объектов, заданных в сессии
		if(empty($access['c'.$company_id]) || !is_array($access['c'.$company_id])) return $objects;

		#Просмотр объектов доступа пользователя в текущей организации
		foreach($access['c'.$company_id] as $object_id){

			#Текущий объект
			$object = $this->getObject($object_id);

			#Если объект не существует в массиве объектов - пропускаем его
			if(!is_array($object)) continue;

			$success = true;
			#Проверка объекта на соответствие заданным критериям фильтра
			foreach($filter as $key=>$value){
				if(isset($object[$key])){
					
					if(!self::compareRuleElement($object[$key],$value)) $success = false;
				}else $success = false;
			}

			#Добавление в результаты
			if( $success ) array_push($objects, $object);

		}#Просмотр объектов доступа пользователя в текущей организации

		#Возврат результатов
		return $objects;
	}#end function




	/*
	 * Возвращает ACL аттрибут пользователя из текущей сессии
	 * 
	 * Принимает аргументы:
	 * $attr_name - Название ACL аттрибута, значение которого требуется вернуть
	 * 
	 * Возвращает значение запрошенного аттрибута ибо FALSE если аттрибут не существует или ошибка
	 */
	public function userAclAttribute($attr_name=''){

		if(empty($attr_name)) return false;

		#Проверка сессии, факта аутентификации пользователя
		if(!$this->userSessionCheck()) return false;
		$acl = Session::_get('acl');
		$access = Session::_get('access');

		switch($attr_name){

			case 'access': return Session::_get('access');
			case 'id': 
			case 'user_id': 
				return $acl['user_id'];
			case 'info': 
			case 'user': 
				return Session::_get('user');
			case 'is_admin': return $acl['is_admin'];
			case 'is_developer': return $acl['is_admin'];
			case 'is_client': return $acl['is_client'];
			case 'is_user': return $acl['is_user'];
			case 'subject':
				if($acl['is_admin']) return ACL_SUBJECT_ADMIN;
				if($acl['is_user']) return ACL_SUBJECT_USER;
				if($acl['is_client']) return ACL_SUBJECT_CLIENT;
				return ACL_SUBJECT_UNDEFINED;
			break;
			case 'auth_time': return $acl['auth_time'];
			case 'source': 
			case 'source_name':
				return $acl['source_name'];
			case 'company': 
			case 'company_id': 
			case 'active_company': 
				return $acl['active_company'];
			case 'company_name': return $acl['company_name'];
			case 'hash_access': return $acl['hash_access'];
			case 'explain': return $acl['explain'];

			default: return false;
		}

	}#end function





	/*=====================================================================================================================================
	Работа источниками данных
	======================================================================================================================================*/



	/*
	 * Возвращает перечень доступных источников данных и их названий
	 * 
	 * $only_active - признак, указывающий вернуть только активные источники данных
	 */
	public function getSources($only_active=true){

		#Массив результатов
		$sources=array();

		#Просмотр источников данных
		foreach($this->options['sources'] as $sourcename=>$source){
			if($only_active && !$source['active']) continue;
			$sources[$sourcename] = $source['name'];
		}

		#Возврат результатов
		return $sources;
	}#end function


	/*
	 * Возвращает перечень ACL субьектов, которые могут быть аутентифицированы из указанного источника данных
	 * 
	 * $source_name - имя источника данных
	 * $source_info - запрашиваемая информация
	 * 
	 * Возвращает аттрибут источника данных либо FALSE если аттрибут или источник данных не существует
	 */
	public function getSourceInfo($source_name=null, $source_info=null){

		if(!isset($this->options['sources'][$source_name][$source_info])) return false;

		#Возврат результатов
		return $this->options['sources'][$source_name][$source_info];
	}#end function



	/*
	 * Возвращает перечень ACL субьектов, которые могут быть аутентифицированы из указанного источника данных
	 * 
	 * $source_name - имя источника данных
	 * 
	 * Возвращает массив ACL субьектов, которые могут быть аутентифицированы из указанного источника данных или FALSE, если источник данных не найден
	 */
	public function getSourceSubjects($source_name=null){
		return $this->getSourceInfo($source_name,'acl_subject');
	}#end function
















	/*=====================================================================================================================================
	Работа правами доступа пользователей
	======================================================================================================================================*/

	/*
	 * Получение списка пользователей из указанного источника данных
	 * 
	 * $source_name - имя источника данных
	 * 
	 * Возвращает массив пользователей или FALSE, если произошла ошибка или источник данных не найден 
	 */
	public function getAllUsers($source_name=null){

		if(!isset($this->options['sources'][$source_name])) return false;

		#Сведения об источнике данных
		$db = (empty($source['db_link']) ? $this->db : Database::getInstance($source['db_link'])); #Ссылка на объект класса ваимодействия с базой данных, если не задана, используется основная ссылка $this->options['db_link']

		#Получение информации о пользователях из таблицы аутентификации
		$db->prepare('SELECT * FROM ?');
		$db->bind($source['table_auth'], null, BIND_FIELD);
		if(($users = $db->select()) === false ){
			#Если при выполнении запроса произошла ошибка - выход
			return $this->doErrorEvent(1, __FUNCTION__, __LINE__, 'source_name = '.$source_name); #Ошибка во время выполнения SQL запроса
		}

		return $users;
	}#end function










	/*=====================================================================================================================================
	Функции логирования
	======================================================================================================================================*/



	/*
	 * Запись текущего действия пользователя в журнал событий
	 */
	public function logAction($data=null){

		if(empty($data)) return;
		if( ($object_type = $this->objectTypeExists($data['object_type'])) === false) return $this->doErrorEvent(70, __FUNCTION__, __LINE__, $data);
		//if( ($object_id = $this->objectExists($data['object_id'])) === false) return $this->doErrorEvent(71, __FUNCTION__, __LINE__, $data);
		$object_id = $data['object_id'];
		if( ($action_id = $this->objectExists($data['action'])) === false) return $this->doErrorEvent(72, __FUNCTION__, __LINE__, $data);

		#Проверка сессии, факта аутентификации пользователя
		if(!$this->userSessionCheck()) return false;
		$acl = Session::_get('acl');

		$logdata = array(
			'action_time'	=> date('Y-m-d H:i:s',time()),				#Время совершения действия
			'user_id'		=> $acl['user_id'],							#Идентификатор пользователя
			'user_login'	=> $acl['user_login'],						#Логин пользователя
			'company_id'	=> $acl['active_company'],					#Организация, в рамках которой выполняется действие
			'user_ip'		=> Session::_get('session_ip'),				#IP адрес
			'user_ip_real'	=> Session::_get('session_ip_real'),		#IP адрес по HTTP_X_FORWARDED_FOR
			'action_id'		=> $action_id,								#Идентификатор действия (функции)
			'action_name'	=> $this->getObjectNameFromId($action_id),	#Название действия (функции)
			'object_id'		=> $object_id,								#Идентификатор Объекта, над которым производится действие
			'object_type'	=> $object_type,							#Тип Объекта, над которым производится действие
			'description'	=> $data['desc'],							#Описание происходимого действия
			'value'			=> json_encode($data['value'])				#Изменяемые значения в рамках выполняемого действия
		);

		#Запись в события в LOG файл
		if(!$this->log->writeCustomLine($logdata)) return $this->doErrorEvent(73, __FUNCTION__, __LINE__);

		#Запись события в базу данных
		if($this->db->insert($this->db->buildInsert('acl_log', $logdata)) === false) return $this->doErrorEvent(74, __FUNCTION__, __LINE__);


		return true;
	}#end function




























}#end class





/*
--------------------------------------------------
КЛАСС ACL - SQL таблицы:
--------------------------------------------------


--
-- Table structure for table `acl_groups`
--
DROP TABLE IF EXISTS `acl_groups`;
CREATE TABLE IF NOT EXISTS `acl_groups` (
  `group_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор группы доступа ACL',
  `name` char(128) NOT NULL COMMENT 'Уникальное внутреннее имя группы доступа',
  `desc` char(255) NOT NULL COMMENT 'Описание группы доступа',
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='Таблица групп доступа ACL';

INSERT INTO `acl_groups` VALUES(1, 'group.main','Основная группа пользователей');
INSERT INTO `acl_groups` VALUES(2, 'group.admin','Группа администраторов');
INSERT INTO `acl_groups` VALUES(3, 'group.developer','Группа разработчиков');
INSERT INTO `acl_groups` VALUES(4, 'group.client','Группа клиентов');



--
-- Table structure for table `acl_objects`
--
DROP TABLE IF EXISTS `acl_objects`;
CREATE TABLE IF NOT EXISTS `acl_objects` (
  `object_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор объекта ACL',
  `type` int(10) unsigned NOT NULL COMMENT 'Тип объекта ACL: (1 - ACL_OBJECT_PAGE, 2 - ACL_OBJECT_FUNCTION, 3 - ACL_OBJECT_ROLE, 4 - ACL_OBJECT_INTERNAL, 5 - ACL_OBJECT_REPORT)',
  `name` char(128) NOT NULL COMMENT 'Уникальное внутреннее имя объекта ACL',
  `desc` char(255) NOT NULL COMMENT 'Описание объекта',
  `lock` int(1) NOT NULL DEFAULT '0' COMMENT 'Признак блокировки объекта: (0 - объект активен и может использоваться, 1 - объект заблокирован и доступен только пользователю с правами разработчика)',
  PRIMARY KEY (`object_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='Таблица объектов ACL';

INSERT INTO `acl_objects` VALUES(1, 3, 'role.admin', 'Роль: Администратор - все права доступа ко всем объектам', 0);
INSERT INTO `acl_objects` VALUES(2, 3, 'role.developer', 'Роль: Разработчик - доступ к заблокированным объектам', 0);
INSERT INTO `acl_objects` VALUES(3, 3, 'role.super', 'Роль: Суперадминистратор - Администратор+Разработчик', 0);



--
-- Table structure for table `acl_object_groups`
--
DROP TABLE IF EXISTS `acl_object_groups`;
CREATE TABLE IF NOT EXISTS `acl_object_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `object_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор объекта ACL',
  `group_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор группы, в которой доступен выбранный ACL объект',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='Таблица сопоставления объектов ACL и групп доступа ACL';

INSERT INTO `acl_object_groups` VALUES(1, 1, 2); -- роль role.admin доступна только для пользователей из группы group.admin
INSERT INTO `acl_object_groups` VALUES(2, 2, 3); -- роль role.developer доступна только для пользователей из группы group.developer
INSERT INTO `acl_object_groups` VALUES(3, 3, 2); -- роль role.super доступна только для пользователей из группы group.admin
INSERT INTO `acl_object_groups` VALUES(4, 3, 3); -- роль role.super доступна только для пользователей из группы group.developer
-- 
-- Пример: Если пользователь находится только в группе role.admin, и для него установлена роль role.super,
-- то роль role.developer ему не будет присвоена в любом случае, поскольку роль доступна только для пользователей из группы group.developer
-- поскольку пользователь не состоит в данной группе (group.developer), роль role.developer ему присвоена не будет
-- 


--
-- Table structure for table `acl_companies`
--
DROP TABLE IF EXISTS `acl_companies`;
CREATE TABLE IF NOT EXISTS `acl_companies` (
  `company_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор организации',
  `name` char(128) NOT NULL COMMENT 'Краткое наименование организации',
  `lock` int(1) NOT NULL DEFAULT '0' COMMENT 'Признак блокировки организации: (0 - организация активна и может использоваться, 1 - организация заблокирована и доступна только пользователю с правами разработчика)',
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='Таблица организаций ACL';

INSERT INTO `acl_companies` VALUES(1, 'ООО "ТК Сервис Юг"', 0);
INSERT INTO `acl_companies` VALUES(2, 'ООО "Леон"', 0);
INSERT INTO `acl_companies` VALUES(3, 'ООО "Эталон МК"', 0);



--
-- Table structure for table `acl_roles`
--
DROP TABLE IF EXISTS `acl_roles`;
CREATE TABLE IF NOT EXISTS `acl_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `object_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор объекта ACL - роли',
  `child_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор объекта ACL - вложенный в роль объект',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `child_id` (`child_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='Таблица контейнеров ролей';

INSERT INTO `acl_roles` VALUES(1, 3, 1); -- в роль role.super включена роль role.admin
INSERT INTO `acl_roles` VALUES(2, 3, 2); -- в роль role.super включена роль role.developer


--
-- Table structure for table `acl_access`
--
DROP TABLE IF EXISTS `acl_access`;
CREATE TABLE IF NOT EXISTS `acl_access` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `user_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор пользователя',
  `company_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор организации в которой разрешен доступ к объекту ACL (0 - доступ разрешен во всех организациях)',
  `object_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор объекта ACL, к которому разрешен доступ',
  `restrict` int(1) NOT NULL COMMENT 'Признак, указывающий что доступ к данному объекту явно запрещен',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='Таблица прав доступа пользователей к объектам ACL';

INSERT INTO `acl_access` VALUES(1, 1, 0, 3, 1); -- Пользователь ID 1 имеет доступ к объекту ID 3: пользователь admin имеет доступ к роли role.super во всех организациях


--
-- Table structure for table `acl_users`
--
DROP TABLE IF EXISTS `acl_users`;
CREATE TABLE IF NOT EXISTS `acl_users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор пользователя',
  `login` char(40) NOT NULL COMMENT 'Логин пользователя',
  `password` char(40) NOT NULL COMMENT 'Пароль пользователя',
  `status` int(10) unsigned NOT NULL COMMENT 'Статус учетной записи пользователя (0 - заблокирован)',
  `name` char(128) NOT NULL COMMENT 'Имя пользователя',
  `last_login` datetime NOT NULL COMMENT 'Дата/время последней успешной аутентификации',
  `last_ip` char(15) NOT NULL COMMENT 'IP адрес последней успешной аутентификации',
  `last_company` int(10) unsigned NOT NULL COMMENT 'Идентификатор активной организации, в которой работал пользователь',
  `last_update` datetime NOT NULL COMMENT 'Дата/время последнего обновления прав доступа',
  `need_update` int(1) NOT NULL COMMENT 'Признак, указывающий на необходимость обновления прав доступа у пользователя',
  `hash_user` char(64) NOT NULL COMMENT 'Контрольная сумма пользователя',
  `hash_group` char(64) NOT NULL COMMENT 'Контрольная сумма групп, в которые включен пользователь',
  `hash_priv` char(64) NOT NULL COMMENT 'Контрольная сумма прав доступа, назначенных пользователю',
  PRIMARY KEY (`user_id`),
  KEY `login` (`login`,`password`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='Таблица учетных данных пользователей';

INSERT INTO `acl_users` VALUES(1, 'admin', 'admin', 1, 'Администратор системы', '0000-00-00 00:00:00', 'localhost', 1, '0000-00-00 00:00:00', 0, 'sha1-ba96fb050ee1be0b330867b0ad5e2107ee041392', 'sha1-91032ad7bbcb6cf72875e8e8207dcfba80173f7c', 'sha1-e26973e6ee8ab9cd8cb3f207d1b90f00d2669eff');



--
-- Table structure for table `acl_user_groups`
--
DROP TABLE IF EXISTS `acl_user_groups`;
CREATE TABLE IF NOT EXISTS `acl_user_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `user_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор пользователя',
  `group_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор группы, в которую включен пользователь',
  `restrict` int(1) NOT NULL COMMENT 'Признак, указывающий что пользователь явно исключен из данной группы',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='Таблица сопоставления пользователей и групп доступа ACL';

INSERT INTO `acl_user_groups` VALUES(1, 1, 2, 0); -- пользователь admin включен в группу group.admin




--------------------------------------------------
-- КЛАСС ACL - дополнительные SQL таблицы:
--------------------------------------------------


--
-- Table structure for table `acl_log`
--
DROP TABLE IF EXISTS `acl_log`;
CREATE TABLE IF NOT EXISTS `acl_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор события',
  `event_time` datetime NOT NULL COMMENT 'Дата/время события',
  `user_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор пользователя, выполнившего действие',
  `user_login` char(20) NOT NULL COMMENT 'Логин пользователя',
  `user_ip` char(15) NOT NULL COMMENT '',
  `user_ip_real` char(15) NOT NULL COMMENT '',
  `company_id` int(10) unsigned NOT NULL COMMENT '',
  `object_type` int(10) unsigned NOT NULL COMMENT '',
  `object_id` int(10) unsigned NOT NULL COMMENT '',
  `action_id` int(10) unsigned NOT NULL COMMENT '',
  `action_name` char(128) NOT NULL COMMENT '',
  `description` char(255) NOT NULL COMMENT '',
  `value` varchar(32768) NOT NULL COMMENT '',
  `hash` char(40) NOT NULL COMMENT 'Контрольная сумма записи',
  PRIMARY KEY (`id`),
  KEY `event_time` (`event_time`),
  KEY `user_id` (`user_id`),
  KEY `object_id` (`object_id`),
  KEY `action_id` (`action_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=cp1251 AUTO_INCREMENT=1 COMMENT='Таблица протоколирования действий пользователей';

*/

?>