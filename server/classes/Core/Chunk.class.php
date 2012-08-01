<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс подготовки массива chunk (части ответа) для обработки в ядре (класс Page) и передаче клиенту
Версия	: 1.0.0/ALPHA
Дата	: 2012-06-06
Авторы	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/


class Chunk{



	/*==============================================================================================
	Переменные класса
	==============================================================================================*/


	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_PHP_ERROR, 'Вызов недопустимого метода или функции класса')
	);


	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/Chunk'
	);



	#Массив Chunk default
	public static $default = array(

		#Опции страницы, направляемые клиенту
		'headers'	=> array(),			#Массив HTTP заголовков ответа
		'httpcode'	=> 200,				#Статус ответа
		'template'	=> null,			#HTML темплейт, задается текстом
		'tmplfile'	=> null,			#HTML файл темплейта, должен быть указан путь и имя файла темплейта, относительно дирректории DIR_MODULES
										#Если задан 'template' или 'tmplfile', то корневой темплейт не будет исползован
										#'template' имеет приоритет перед 'tmplfile', если задан 'template' то 'tmplfile' не обрабатывается
										#'template' и 'tmplfile' работают только при raw запросе и стандартном запросе, при AJAX - не работают
		'html'		=> array(),			#Массив HTML контента
		'require'	=> array(),			#Массив подключаемого медиа-контента (CSS файлы, JS скрипты)
		'actions'	=> array(),			#Массив действий на стороне клиента
		'noCache'	=> false,			#Признак запрета кеширования ответа
		'location'	=> null,			#URL для редиректа на стороне клиента
		'download'	=> array(),			#Массив загружаемых клиенту файлов
		'title'		=> null,			#Заголовок страницы
		'status'	=> 'ok',			#Статус ответа обработки, 'ok' - успешно, все остальное - не успешно (ошибка)
		'messages'	=> array(),			#Массив сообщений, которые необходимо отобразить клиенту
		'data'		=> null,			#Произвольные данные
		'tmpl'		=> 0,				#Статус запроса темплейта при AJAX запросе для перехода с текущей страницы на прочие страницы сайта: 0-не запрашивать темплейт, 1 - запрашивать только с чанков, 2 - запрашивать с чанков и если нет темплейта - вернуть корневой темплейт
		'layout'	=> null,			#JS файл интерфейса текущей страницы

		#Специальные опции для ядра платформы
		'_opt_'		=> array(
			'priority'	=> 0,			#Приоритет текущего модуля над остальными, чем выше значение, тем больше приоритет
										#Chunk модулей с более высоким приоритетом будут обработаны раньше, чем Chunk модулей с более низким приоритетом
										#Рекомендуемые значения: 
										#	90 (CHUNK_PRIORITY_OWNER) - модуль, генерирующий основной контент страницы (родитель страницы)
										#	60 (CHUNK_PRIORITY_HIGH) - модуль, имеющий высокий приоритет (например, генерирует неотъемлемый компонент страницы)
										#	30 (CHUNK_PRIORITY_MEDIUM) - модуль, имеющий средний приоритет (например, генерирует виджеты для компонентов страницы)
										#	15 (CHUNK_PRIORITY_LOW) - модуль, имеющий низкий приоритет (например, генерирует блоки в виджетах)
										#	0  (CHUNK_PRIORITY_NONE) - модуль без приоритета (стандарт)

			#Нижеуказанные опции 'exit', 'die', 'return' сработают в момент обработки ядром текущего чанка.
			'exit'		=> null,		#Если не EMPTY, то после обработки чанков ядро должно завершить работу
			'die'		=> null,		#Если не EMPTY, то после обработки чанков ядро должно завершить работу с отображением на экране заданного сообщения

			'unique'	=> false,		#Если TRUE, то ядро должно прекратить обработку чанков от других модулей и взять за результат чанк этого модуля, игнорируя все остальные чанки
										#Может быть полезно, например, когда при AJAX запросе данных какому-нибудь модулю нужно условно гарантировать, что другие чанки не вставят свои данные
										#Опция сработает в момент обработки текущего чанка, если несколько чанков имеют опцию unique=true, 
										#то сработает на чанке с наивысшим приоритетом или первым в очереди

			'event'		=> null,		#Если не EMPTY, то говорит ядру о необходимости сгенерировать событие с указанным в 'event' именем, дождаться результатов и добавить их к выдаче клиенту
										#При этом текущие результаты от чанков останутся в выдаче.
										#Если используется совместно с 'unique'=true, то в конечной выдаче будут только результаты текущего чанка + результаты от вызванного события.
										#Если чанк от вызванного события имеет опцию 'unique'=true, то в результирующем массиве останется только он.
										#Максимальная вложенность событий ограничена 10 итерациями.

			'no_widgets'=> false,		#Признак, указывающий что для данной страницы не еужно подключать какие-либо виджеты

			'wgt_white'	=> array(),		#Массив белого списка используемых на странице виджетов
			'wgt_black'	=> array()		#Массив черного списка виджетов, запрещенных к использованию на странице
		)

	);


	private $chunk 			= null;		#Рабочий массив Chunk
	private $hu_require		= false;	#Признак, когда TRUE, указывает что в массиве require уже задан уникальный элемент
	private $hu_download	= false;	#Признак, когда TRUE, указывает что в массиве download уже задан уникальный элемент
	private $hu_html		= array();	#Массив признаков, когда TRUE, указывает что в массиве html для ключа container уже задан уникальный элемент
	private $hu_messages	= false;	#Признак, когда TRUE, указывает что в массиве messages уже задан уникальный элемент
	private $am_ident		= null;		#Внутренний указатель на элемент массива Chunk->actions[0]->mod




	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	public function __construct($chunk=null){
		$this->clear($chunk);
	}#end function



	#--------------------------------------------------
	# Деструктор класса
	#--------------------------------------------------
	public function __destruct(){
		//
	}#end function














	/*==============================================================================================
	Функции: работа с массивом в целом и опциями для ядра
	==============================================================================================*/



	/*
	 * Восстановление пустого чанка
	 */
	public function clear($chunk=null){
		if(!empty($chunk)&&is_array($chunk))
			$this->chunk = array_merge(self::$default, $chunk);
		else
			$this->chunk = self::$default;
	}#end function



	/*
	 * Возврат чанка
	 */
	public function build(){
		return $this->chunk;
	}#end function



	/*
	 * Установка приоритета чанка
	 */
	public function setPriority($level=0){
			$this->chunk['_opt_']['priority'] = $level;
	}#end function



	/*
	 * Установка признака завершения работы
	 */
	public function setExit($exit=true){
			$this->chunk['_opt_']['exit'] = $exit;
	}#end function



	/*
	 * Установка признака завершения работы
	 */
	public function setDie($die=null){
			$this->chunk['_opt_']['die'] = $die;
	}#end function



	/*
	 * Установка признака уникальности чанка
	 */
	public function setUnique($unique=true){
			$this->chunk['_opt_']['unique'] = $unique;
	}#end function



	/*
	 * Установка вызываемого события
	 */
	public function setEvent($event=null){
			$this->chunk['_opt_']['event'] = $event;
	}#end function



	/*
	 * Запрет вывода всех виджетов на страницу
	 */
	public function disableWidgets($status=true){
			$this->chunk['_opt_']['no_widgets'] = $status;
	}#end function



	/*
	 * Добавление виджета в белый список
	 */
	public function whiteWidget($widget=null){
		if(!empty($widget)) $this->chunk['_opt_']['wgt_white'][] = $widget;
	}#end function



	/*
	 * Добавление виджета в черный список
	 */
	public function blackWidget($widget=null){
		if(!empty($widget)) $this->chunk['_opt_']['wgt_black'][] = $widget;
	}#end function








	/*==============================================================================================
	Функции: работа с опциями страницы, направляемыми клиенту
	==============================================================================================*/



	/*
	 * Добавляет Header
	 * 
	 * $key - Название заголовка ('Content-Type','Location' и т.п.)
	 * $value - Значение заголовка
	 * 
	 * Пример:
	 * $chunk->addHeader('Content-Type','text/html');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		#HTTP заголовки, отправляемые клиенту
	 * 		'headers' => array(
	 * 			'Content-type' => 'text/html'
	 * 		)
	 * )
	 * 
	 * Если не задан $key или $value, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addHeader($key=null, $value=null){

		if(empty($key)||empty($value)) return false;
		$this->chunk['headers'][$key] = $value;

		return true;
	}#end function



	/*
	 * Убирает все добавленные Headers
	 */
	public function clearHeaders(){
		$this->chunk['headers'] = array();
	}#end function



	/*
	 * Задает новый HTTP код ответа
	 */
	public function setHttpCode($httpcode=200){
		$this->chunk['httpcode'] = $httpcode;
	}#end function



	/*
	 * Задает имя JS файла интерфейса текущей страницы
	 */
	public function setLayout($layout=null){
		$this->chunk['layout'] = $layout;
	}#end function



	/*
	 * Задает статус запроса темплейта ссылкам текущей страницы для 
	 * последующего запроса через AJAX других страниц.
	 * 
	 * Значения $tmpl:
	 * 0 - при переходе на другую страниц с текущей страницы (через AJAX запрос), темплейт страницы не запрашивается
	 * 1 - при переходе на другую страниц с текущей страницы (через AJAX запрос), темплейт страницы запрашивается только с чанков, если темплейт не задан чанками - ничего не возвращается
	 * 2 - при переходе на другую страниц с текущей страницы (через AJAX запрос), темплейт страницы запрашивается только с чанков, если темплейт не задан чанками, возвращается корневой темплейт по-умолчанию
	 * 
	 */
	public function setTmplStatus($tmpl=0){
		$this->chunk['tmpl'] = $tmpl;
	}#end function



	/*
	 * Задает HTML темплейт
	 */
	public function setTemplate($template=null){
		$this->chunk['template'] = $template;
	}#end function



	/*
	 * Задает файл HTML темплейта
	 * 
	 * В отличии от 'template', в котором задается уже конечный HTML контент
	 * в 'tmplfile' указывается путь к файлу темплейта, который будет обработан ядром
	 * вместо предопределенного корневого темплейта.
	 * Должен быть указан путь и имя файла темплейта, относительно дирректории DIR_MODULES
	 * Если задан 'template' или 'tmplfile', то корневой темплейт не будет исползован
	 * 'template' имеет приоритет перед 'tmplfile'
	 * 
	 */
	public function setTemplateFile($template=null){
		$this->chunk['tmplfile'] = $template;
	}#end function



	/*
	 * Загружает HTML темплейт из указанного файла
	 */
	public function loadTemplateContent($file=null){
		$template = $this->readFromFile($file);
		$this->chunk['template'] = ($template !== false ? $template : null);
	}#end function



	/*
	 * Загрузка контента из указанного файла
	 * 
	 * $file - имя файла, должен быть указан путь и имя файла темплейта, относительно дирректории DIR_MODULES
	 * Указывая "Core/html/login.tpl" будет подключен файл [DIR_MODULES]/Core/html/login.tpl
	 * Файлы темплейтов не обрабатываются PHP, загружается и возвращается исключительно контент
	 * Для предотвращения хаков (например, [DIR_MODULES]/Core/../../../../../passwd), 
	 * при нахождении конструкции "../" функция фозвращает FALSE
	 * В случае отсутствия файла или невозможности его чтения, также вернется FALSE,
	 * Если файл успешно прочтен, будет возвращено его содержимое
	 */
	public function readFromFile($file=null){

		if(empty($file)) return false;
		if(strpos($file,'../')!==false) return false;
		$file = DIR_MODULES.'/'.ltrim($file, " .\r\n\t\\/");
		if(!file_exists($file)||!is_readable($file))return false;

		return file_get_contents($file);
	}#end function



	/*
	 * Добавляет HTML код в чанк
	 * 
	 * $container - идентификатор контейнера, в который должен быть вставлен данный HTML
	 * $html - непосредственно HTML код
	 * $action - Действие, указывающее, как вставлять HTML в контейнер: 
	 * 			'first' - в начало(будет вставлен самым первым), 
	 * 			'last' - в конец (будет вставлен по-порядку), 
	 * 			'single' - будет вставлен как единственный элемент, но после в массив можно будет добавлять
	 * 			'unique' - будет вставлен как единственный элемент с запретом на дальнейшее добавление
	 * 
	 * Пример:
	 * $chunk->addHtml('container','<h3>H3 Tag!</h3>');
	 * $chunk->addHtml('container','<div>test text</div>');
	 * $chunk->addHtml('container','<h1>Hello world!</h1>', 'first');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		'html'=> array(
	 * 			'container'=> array(
	 * 				'<h1>Hello world!</h1>',
	 * 				'<h3>H3 Tag!</h3>',
	 * 				'<div>test text</div>'
	 * 			)
	 * 		)
	 * )
	 * 
	 * Если $chunk не массив или не задан $key, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addHtml($container=null, $html='', $action='last'){

		if(empty($container)||!empty($this->hu_html[$container])) return false;
		if(!isset($this->chunk['html'][$container])) $this->chunk['html'][$container] = array();
		switch(strtolower($action)){
			case 'unique':
				$this->chunk['html'][$container] = array($html);
				$this->hu_html[$container] = true;
			break;
			case 'single':
				$this->chunk['html'][$container] = array($html);
			break;
			case 'first':
				array_unshift($this->chunk['html'][$container], $html);
			break;
			default:
				array_push($this->chunk['html'][$container], $html);
		}

		return true;
	}#end function



	/*
	 * Очистка всего массива HTML
	 */
	public function clearHtml($key=null){
		if(empty($key)){
			$this->chunk['html'] = array();
			$this->hu_html = array();
		}else{
			if(isset($this->chunk['html'][$key])) unset($this->chunk['html'][$key]);
			if(isset($this->hu_html[$key])) unset($this->hu_html[$key]);
		}
	}#end function



	/*
	 * Устанавливает заголовок TITLE
	 * 
	 * $title - Заголовок
	 * 
	 * Пример:
	 * $chunk->setTitle('Foo Bar');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		'title' => 'Foo Bar',
	 * 		'html'=> array(
	 * 			'head/title'=> array(
	 * 					'Foo Bar'
	 * 			)
	 * 		)
	 * )
	 * 
	 * Если не задан $title, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function setTitle($title=null){

		if(is_null($title)||!is_string($title)) return false;
		$this->chunk['title'] = $title;

		return $this->addHtml('head/title', $title, 'unique');
	}#end function



	/*
	 * Устанавливает редирект на другую страницу посредством GET
	 * 
	 * $location - URL для редиректа
	 * 
	 * Пример:
	 * $chunk->setLocation('/main/index/');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		'location' => '/main/index/'
	 * )
	 * 
	 * $location может также принимать специальные значения:
	 * refresh - перезагрузка страницы GET методом 
	 * reload - перезагрузка страницы текущим методом
	 * 
	 * Если не задан $location, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function setLocation($location=null){

		if(is_null($location)||!is_string($location)) return false;
		$this->chunk['location'] = $location;

		return true;
	}#end function


	/*
	 * Устанавливает редирект на другую страницу посредством AJAX
	 */
	public function setPushLocation($location=null){

		if(is_null($location)||!is_string($location)) return false;
		$this->chunk['pushLocation'] = $location;

		return true;
	}#end function




	/*
	 * Добавляет в чанк требование подключить на страницу медиа-файл (JS, CSS  и т.п.)
	 * 
	 * $file - путь и имя файла относительно корневой директории
	 * $os - проверка на соответствие ОС. Формат: win|mac|linux|ios|android|webos, ..., ..., если не задано - для всех ОС
	 * $browser - проверка на соответствие браузера. Формат: chrome|firefox|ie|opera|safari lt|lte|gt|gte [version], ..., ..., если не задано - для всех браузеров
	 * $action - Действие, указывающее, в какой последовательности выполнять файл: 
	 * 			'first' - в начало(будет выполнен первым), 
	 * 			'last' - в конец (будет выполнен по-порядку), 
	 * 			'single' - будет вставлен как единственный элемент, но после в массив можно будет добавлять
	 * 			'unique' - будет вставлен как единственный элемент с запретом на дальнейшее добавление
	 * 
	 * Пример:
	 * $chunk->addRequire('/client/lib/js/main.js');
	 * $chunk->addRequire('/client/lib/css/main.css');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		'require'=> array(
	 * 			array('name'=>'/client/lib/js/main.js',null,null),
	 * 			array('name'=>'/client/lib/css/main.css',null,null)
	 * 		)
	 * )
	 * 
	 * Если не задан $file, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addRequire($file=null, $os=null, $browser=null, $action='last'){

		if(empty($file)||$this->hu_require) return false;

		$req = array(
			'name'		=> $file,
			'os'		=> $os,
			'browser'	=> $browser
		);

		switch(strtolower($action)){
			case 'unique':
				$this->chunk['require'] = array($req);
				$this->hu_require = true;
			break;
			case 'single':
				$this->chunk['require'] = array($req);
			break;
			case 'first':
				array_unshift($this->chunk['require'], $req);
			break;
			default:
				array_push($this->chunk['require'], $req);
		}

		return true;
	}#end function



	/*
	 * Очистка списка подключаемых файлов
	 */
	public function clearRequires(){
		$this->chunk['require'] = array();
		$this->hu_require = false;
	}#end function



	/*
	 * Добавляет в чанк требование загрузить файл клиенту
	 * 
	 * $file - путь и имя файла относительно корневой директории
	 * $action - Действие, указывающее, в какой последовательности выполнять файл: 
	 * 			'first' - в начало(будет загружен первым), 
	 * 			'last' - в конец (будет загружен по-порядку), 
	 * 			'single' - будет вставлен как единственный элемент, но после в массив можно будет добавлять
	 * 			'unique' - будет вставлен как единственный элемент с запретом на дальнейшее добавление
	 * 
	 * Пример:
	 * $chunk->addDownload('/client/lib/js/main.js');
	 * $chunk->addDownload('/client/lib/css/main.css');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		'download'=> array(
	 * 			'/client/lib/js/main.js',
	 * 			'/client/lib/css/main.css'
	 * 		)
	 * )
	 * 
	 * Если не задан $file, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addDownload($file=null, $action='last'){

		if(empty($file)||$this->hu_download) return false;
		switch(strtolower($action)){
			case 'unique':
				$this->chunk['download'] = array($file);
				$this->hu_require = true;
			break;
			case 'single':
				$this->chunk['download'] = array($file);
			break;
			case 'first':
				array_unshift($this->chunk['download'], $file);
			break;
			default:
				array_push($this->chunk['download'], $file);
		}

		return true;
	}#end function



	/*
	 * Очистка списка загружаемых файлов
	 */
	public function clearDownloads(){
		$this->chunk['download'] = array();
		$this->hu_download = false;
	}#end function



	/*
	 * Устанавливает произвольные данные
	 * 
	 * $data - Произвольные данные, отправляемые клиенту
	 * 
	 * Пример:
	 * Page::setData('Foo Boo');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		'data' => 'Foo Boo'
	 * )
	 * 
	 * во всех случаях - TRUE
	 */
	public function setData($data=null){

		$this->chunk['data'] = $data;

		return true;
	}#end function



	/*
	 * Добавляет сообщение для вывода клиенту
	 * 
	 * $title - Заголовок сообщения
	 * $message - Сообщение в формате HTML
	 * $type - тип сообщения:
	 * 			info - информационное
	 * 			warning - внимание
	 * 			error - ошибка
	 * 			success - успешно
	 * 			confirm - запрос подтверждения (ДА/НЕТ)
	 * $id - Идентификатор сообщения, если установлено, то у пользователя будет возможность игнорировать сообщение в дальнейшем (например, записью ID сообщения в cookie)
	 * $action - Действие, указывающее, как отображать сообщение: 
	 * 			'first' - в начало(будет показано самым первым), 
	 * 			'last' - в конец (будет показано по-порядку), 
	 * 			'single' - будет вставлен как единственный элемент, но после в массив можно будет добавлять
	 * 			'unique' - будет вставлен как единственный элемент с запретом на дальнейшее добавление
	 * 
	 * Пример:
	 * $chunk->addMsgCustom('title 1','message 1');
	 * $chunk->addMsgCustom('title 2','message 2', 'warning');
	 * $chunk->addMsgCustom('title 3','message 3', 'error', 'testid','first');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		'messages'=> array(
	 * 			array(
	 * 				'id' => 'testid',
	 * 				'title' => 'title 3',
	 * 				'text' => 'message 3',
	 * 				'type' => 'error'
	 * 			),
	 * 			array(
	 * 				'id' => null,
	 * 				'title' => 'title 1',
	 * 				'text' => 'message 1',
	 * 				'type' => 'info'
	 * 			),
	 * 			array(
	 * 				'id' => null,
	 * 				'title' => 'title 2',
	 * 				'text' => 'message 2',
	 * 				'type' => 'warning'
	 * 			)
	 * 		)
	 * )
	 * 
	 * Если не задан $title или не задан $message, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addMsgCustom($title='', $message='', $type='info', $id=null, $action='last'){

		if(empty($title)||empty($message)||$this->hu_messages) return false;
		if(!isset($chunk['messages'])) $chunk['messages'] = array();
		$msgdata = array(
			'id'	=> $id,
			'title'	=> $title,
			'text'	=> $message,
			'type'	=> $type
		);
		switch(strtolower($action)){
			case 'unique':
				$this->chunk['messages'] = array($msgdata);
				$this->hu_messages = true;
			break;
			case 'single':
				$this->chunk['messages'] = array($msgdata);
			break;
			case 'first':
				array_unshift($this->chunk['messages'], $msgdata);
			break;
			default:
				array_push($this->chunk['messages'], $msgdata);
		}

		return true;
	}#end function



	/*
	 * Добавляет сообщение об ошибке для вывода клиенту
	 * 
	 * $title - Заголовок сообщения
	 * $message - Сообщение в формате HTML
	 * $action - Действие, указывающее, как отображать сообщение: 
	 * 			'first' - в начало(будет показано самым первым), 
	 * 			'last' - в конец (будет показано по-порядку), 
	 * 			'single' - будет вставлен как единственный элемент, но после в массив можно будет добавлять
	 * 			'unique' - будет вставлен как единственный элемент с запретом на дальнейшее добавление
	 * 
	 * Пример:
	 * $chunk->addMsgError($a,'title 1','message 1');
	 * $chunk->addMsgError($a,'title 2','message 2', 'first');
	 * Массив Chunk будет выглядеть так:
	 * array(
	 * 		'messages'=> array(
	 * 			array(
	 * 				'id' => null,
	 * 				'title' => 'title 2',
	 * 				'text' => 'message 2',
	 * 				'type' => 'error'
	 * 			),
	 * 			array(
	 * 				'id' => null,
	 * 				'title' => 'title 1',
	 * 				'text' => 'message 1',
	 * 				'type' => 'error'
	 * 			)
	 * 		)
	 * )
	 * 
	 * Если $chunk не массив или не задан $title или не задан $message, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addMsgError($title='', $message='', $action='unique'){

		$this->chunk['status'] = 'error';
		return $this->addMsgCustom($title, $message, 'error', null, $action);

	}#end function


	/*
	 * Добавляет сообщение об успешной операции
	 */
	public function addMsgSuccess($title='', $message='', $action='unique'){

		return $this->addMsgCustom($title, $message, 'success', null, $action);

	}#end function



	/*
	 * Добавляет информационное сообщение
	 */
	public function addMsgInfo($title='', $message='', $action='last'){

		return $this->addMsgCustom($title, $message, 'info', null, $action);

	}#end function



	/*
	 * Добавляет действия для JS клиента
	 * 
	 * $delay - Задержка перед выполнением действия в миллисекундах
	 * $require - JS или CSS файлы, которые необходимо загрузить перед выполнением действия
	 * $order - Порядок выполнения. Может быть 'exec,mod' или 'mod,exec'. По умолчанию mod,exec
	 * $noMod - Если TRUE - запрет модификации страницы. В этом случае массив mod не будет обабатываться.
	 * $mod - Массив, содержащий модификации на странице
	 * $exec - Массив комманд на JS, которые будут выполнены
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function newAction($delay=null, $require=array(), $order=null, $noMod=false, $mod=array(), $exec=array()){

		$this->chunk['actions'] = array(array(
			'delay'		=> $delay,
			'require'	=> $require,
			'order'		=> $order,
			'noMod'		=> $noMod,
			'mod'		=> $mod,
			'exec'		=> $exec
		));

		return true;
	}#end function



	/*
	 * Изменяет порядок выполнения. Может быть 'exec,mod' или 'mod,exec'. По умолчанию mod,exec
	 * 
	 * $delay - Задержка перед выполнением действия в миллисекундах
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function setActionOrder($order='mod,exec'){

		if(empty($this->chunk['actions'])) $this->newAction();
		$this->chunk['actions'][0]['order'] = $order;

		return true;
	}#end function



	/*
	 * Изменяет задержку перед выполнением действия в миллисекундах
	 * 
	 * $delay - Задержка перед выполнением действия в миллисекундах
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function setActionDelay($delay=null){

		if(empty($this->chunk['actions'])) $this->newAction();
		$this->chunk['actions'][0]['delay'] = $delay;

		return true;
	}#end function



	/*
	 * Добавляет в чанк требование загрузить медиа-файл (JS, CSS  и т.п.)
	 * 
	 * $file - путь и имя файла относительно корневой директории
	 * $os - проверка на соответствие ОС. Формат: win|mac|linux|ios|android|webos, ..., ..., если не задано - для всех ОС
	 * $browser - проверка на соответствие браузера. Формат: chrome|firefox|ie|opera|safari lt|lte|gt|gte [version], ..., ..., если не задано - для всех браузеров
	 * 
	 * Если не задан $file, возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addActionRequire($file=null, $os=null, $browser=null){

		if(empty($file)) return false;

		$req = array(
			'name'		=> $file,
			'os'		=> $os,
			'browser'	=> $browser
		);

		if(empty($this->chunk['actions'])) $this->newAction();
		if(!in_array($file, $this->chunk['actions'][0]['require'])) array_push($this->chunk['actions'][0]['require'], $req);

		return true;
	}#end function



	/*
	 * Добавляет команду или массив комманд на JS, которые будут выполнены
	 * 
	 * $exec - js код, если задан массивом, то будет обработан каждый элемент массива отдельно
	 * 
	 * $chunk->addActionExec(
	 * 		array('alert("hello!");','alert("World!");')
	 * );
	 * $chunk->addActionExec('alert("hello World!");');
	 * 
	 * Если $exec не задан - возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addActionExec($exec=null){

		if(empty($key)) return false;

		#Если ключ задан массивом - обрабатываем каждый элемент массива
		if(is_array($exec)){
			foreach($exec as $k){
				$this->addActionExec($k);
			}
			return true;
		}

		if(empty($this->chunk['actions'])) $this->newAction();
		array_push($this->chunk['actions'][0]['exec'], $exec);

		return true;
	}#end function



	/*
	 * Устанавливает признак запрета модификации страницы
	 * 
	 * $noMod - Если TRUE - запрет модификации страницы. В этом случае массив mod не будет обабатываться.
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function setActionNoMod($noMod=false){

		if(empty($this->chunk['actions'])) $this->newAction();
		$this->chunk['actions'][0]['noMod'] = $noMod;

		return true;
	}#end function



	/*
	 * Добавляет запись в массив, содержащий модификации на странице
	 * 
	 * $mod - ModObject, представляет собой ассоциированный массив:
	 * modObject = array(
	 * 		'type'		=> 'exec',		//Тип выполняемого действия. Может быть:
	 * 									//createElement — для создания нового элемента
	 * 									//cloneElement — для клонирования существующего элемента
	 * 									//element — для модифицирования элемента
	 * 									//exec — для выполнения произвольного JS (Значение по умолчанию)
	 * 		'store'		=> '',			//Идентификатор, в который будет помещен возвращенный результат действия
	 *									//Затем, этот идентификатор можно будет использовать в произвольных JS командах в пределах текущего действия Action
	 * 		'selector'	=> '',			//CSS-селектор для получения элемента. Также может быть идентификатором, если начинается с '>'. Может быть использован при type = cloneElement || element
	 * 		'tag'		=> '',			//Имя тега создаваемого элемента (по умолчанию div). Может быть использован при type = createElement
	 * 		'set'		=> array(),		//MooTools Element.set - Объект аттрибутов, которые будут установлены для элемента. Может быть использован при type = createElement || cloneElement || element
	 * 		'get'		=> array(),		//MooTools Element.get - Объект аттрибутов, которые будут получены от элемента. Будет записан в store_id. Может быть использован при type = element
	 * 		'erase'		=> array(),		//MooTools Element.erase - Объект аттрибутов, которые будут удалены у элемента. Может быть использован при type = cloneElement || element
	 * 		'withElement' => array(		//Описание метода, который будет выполнен для элемента. Может быть использован при type = createElement || cloneElement || element
	 * 			array(
	 * 				'name' => '',			//Имя метода
	 * 				'arguments' => array()	//Массив аргументов. Если арг. является строкой, и в её начале стоит символ '>', то эта строка будет выполнена как JS, при этом можно использовать store_id для подстановки значений
	 * 			)
	 * 		),
	 * 		'exec'		=> array()		//Команда или массив комманд на JS, которые будут выполнены
	 * )
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function addActionMod($mod=null){

		$mod_def = array(
			'type'		=> null,
			'store'		=> null,
			'selector'	=> null,
			'tag'		=> null,
			'set'		=> array(),
			'get'		=> array(),
			'erase'		=> array(),
			'withElement'=> array(),
			'exec'		=>array()
		);

		if(empty($mod)||!is_array($mod)){
			$mod = $mod_def;
		}else{
			$mod = array_merge($mod_def, $mod);
		}
		if(empty($this->chunk['actions'])) $this->newAction();
		array_push($this->chunk['actions'][0]['mod'], $mod);
		$this->am_ident = count($this->chunk['actions'][0]['mod'])-1;

		return true;
	}#end function



	/*
	 * Очищает массив модов
	 */
	public function clearActionMods(){

		if(empty($this->chunk['actions'])) $this->newAction();
		$this->chunk['actions'][0]['mod'] = array();
		$this->am_ident = null;

		return true;
	}#end function



	/*
	 * Задает тип выполняемого действия текущему моду, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $type - Тип выполняемого действия. Может быть:
	 * 			createElement — для создания нового элемента
	 * 			cloneElement — для клонирования существующего элемента
	 * 			element — для модифицирования элемента
	 * 			exec — для выполнения произвольного JS (Значение по умолчанию)
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function setModType($type='exec'){

		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		$this->chunk['actions'][0]['mod'][$this->am_ident]['type'] = $type;

		return true;
	}#end function



	/*
	 * Задает идентификатор, в который будет помещен возвращенный результат действия для текущего мода, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $store - Идентификатор, в который будет помещен возвращенный результат действия
	 *			Затем, этот идентификатор можно будет использовать в произвольных JS командах в пределах текущего действия Action
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function setModStore($store=null){

		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		$this->chunk['actions'][0]['mod'][$this->am_ident]['store'] = $store;

		return true;
	}#end function



	/*
	 * Задает идентификатор, в который будет помещен возвращенный результат действия для текущего мода, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $selector - CSS-селектор для получения элемента. Также может быть идентификатором, если начинается с '>'. Может быть использован при type = cloneElement || element
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function setModSelector($selector=null){

		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		$this->chunk['actions'][0]['mod'][$this->am_ident]['selector'] = $selector;

		return true;
	}#end function



	/*
	 * Задает имя тега создаваемого элемента при type = createElement для текущего мода, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $tag - Имя тега создаваемого элемента (по умолчанию div). Может быть использован при type = createElement
	 * 
	 * возвращает во всех случаях - TRUE
	 */
	public function setModTag($tag=null){

		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		$this->chunk['actions'][0]['mod'][$this->am_ident]['tag'] = $tag;

		return true;
	}#end function



	/*
	 * MooTools Element.get - Объект аттрибутов, которые будут получены от элемента. Будет записан в store_id. Может быть использован при type = element
	 * для текущего мода, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $key - ключ аттрибута, если задан массивом, то будет обработан каждый элемент массива отдельно
	 * $value - значение аттрибута
	 * 
	 * Установит для аттрибутов 'visibility' и 'display' значение 'block'
	 * $chunk->addModGet(
	 * 		array('visibility','display'),
	 * 		'block'
	 * );
	 * 
	 * Установит для каждого аттрибута указанное значение
	 * $chunk->addModGet(
	 * 		array(
	 * 			'visibility' => 'hidden',
	 * 			'display' => 'none'
	 * 		)
	 * );
	 * 
	 * Если $key не задан - возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addModGet($key=null, $value=null){

		if(empty($key)) return false;

		#Если ключ задан массивом - обрабатываем каждый элемент массива
		if(is_array($key)){
			foreach($key as $k=>$v){
				if(is_numeric($k)) $this->addModGet($v, $value);
				else $this->addModGet($k, $v);
			}
			return true;
		}

		if(is_null($value)) $value = '';
		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		$this->chunk['actions'][0]['mod'][$this->am_ident]['get'][$key] = $value;

		return true;
	}#end function



	/*
	 * MooTools Element.set - Объект аттрибутов, которые будут установлены для элемента. Может быть использован при type = createElement || cloneElement || element
	 * для текущего мода, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $key - ключ аттрибута, если задан массивом, то будет обработан каждый элемент массива отдельно
	 * $value - значение аттрибута
	 * 
	 * Установит для аттрибутов 'visibility' и 'display' значение 'block'
	 * $chunk->addModSet(
	 * 		array('visibility','display'),
	 * 		'block'
	 * );
	 * 
	 * Установит для каждого аттрибута указанное значение
	 * $chunk->addModSet(
	 * 		array(
	 * 			'visibility' => 'hidden',
	 * 			'display' => 'none'
	 * 		)
	 * );
	 * 
	 * Если $key не задан - возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addModSet($key=null, $value=null){

		if(empty($key)) return false;

		#Если ключ задан массивом - обрабатываем каждый элемент массива
		if(is_array($key)){
			foreach($key as $k=>$v){
				if(is_numeric($k)) $this->addModSet($v, $value);
				else $this->addModSet($k, $v);
			}
			return true;
		}

		if(is_null($value)) $value = '';
		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		$this->chunk['actions'][0]['mod'][$this->am_ident]['set'][$key] = $value;

		return true;
	}#end function



	/*
	 * MooTools Element.erase - Объект аттрибутов, которые будут удалены у элемента. Может быть использован при type = cloneElement || element
	 * для текущего мода, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $key - ключ аттрибута, если задан массивом, то будет обработан каждый элемент массива отдельно
	 * 
	 * Удалит аттрибуты 'visibility' и 'display'
	 * $chunk->addModErase(
	 * 		array('visibility','display')
	 * );
	 * 
	 * Удалит аттрибут 'visibility'
	 * $chunk->addModErase('visibility');
	 * 
	 * Если $key не задан - возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addModErase($key=null){

		if(empty($key)) return false;

		#Если ключ задан массивом - обрабатываем каждый элемент массива
		if(is_array($key)){
			foreach($key as $k){
				$this->addModErase($k);
			}
			return true;
		}

		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		array_push($this->chunk['actions'][0]['mod'][$this->am_ident]['erase'], $key);

		return true;
	}#end function




	/*
	 * Команда или массив комманд на JS, которые будут выполнены
	 * для текущего мода, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $exec - js код, если задан массивом, то будет обработан каждый элемент массива отдельно
	 * 
	 * $chunk->addModExec(
	 * 		array('alert("hello!");','alert("World!");')
	 * );
	 * $chunk->addModExec('alert("hello World!");');
	 * 
	 * Если $exec не задан - возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addModExec($exec=null){

		if(empty($key)) return false;

		#Если ключ задан массивом - обрабатываем каждый элемент массива
		if(is_array($exec)){
			foreach($exec as $k){
				$this->addModExec($k);
			}
			return true;
		}

		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		array_push($this->chunk['actions'][0]['mod'][$this->am_ident]['exec'], $exec);

		return true;
	}#end function



	/*
	 * Описание метода, который будет выполнен для элемента. Может быть использован при type = createElement || cloneElement || element
	 * для текущего мода, если нет ни одного мода - создает новый мод и делает его текущим
	 * 
	 * $name - Имя метода
	 * $arguments - Массив аргументов. Если арг. является строкой, и в её начале стоит символ '>', то эта строка будет выполнена как JS, при этом можно использовать store_id для подстановки значений
	 * 
	 * Пример:
	 * $chunk->addModWith(
	 * 		array(
	 * 			'wraps' => '>x',
	 * 			'inject' => array(
	 * 				'>document.body',
	 * 				'top'
	 * 			)
	 * 		)
	 * );
	 * В примере "x", идентификатор, указанный в Chunk->actions[0]->store
	 * В первом действии MooTools "обертывает" элемент X
	 * Во втором действии элемент вставляется в document.body
	 * 
	 * Если $name не задан - возвращает FALSE, во всех остальных случаях - TRUE
	 */
	public function addModWith($name=null, $arguments=null){

		if(empty($name)) return false;

		#Если ключ задан массивом - обрабатываем каждый элемент массива
		if(is_array($name)){
			foreach($name as $k=>$v){
				if(is_numeric($k)) $this->addModWith($v, $arguments);
				else $this->addModWith($k, $v);
			}
			return true;
		}

		if(empty($this->chunk['actions'])) $this->newAction();
		if(is_null($this->am_ident)) $this->addActionMod();
		array_push(
			$this->chunk['actions'][0]['mod'][$this->am_ident]['withElement'],
			array(
				'name' => $name,
				'arguments' => $arguments
			)
		);

		return true;
	}#end function






}#end class


?>