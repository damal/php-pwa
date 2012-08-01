<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс обработки запроса 
Версия	: 1.0.0/ALPHA
Дата	: 2012-05-14
Авторы	: Станислав В. Третьяков, Илья Гилёв
--------------------------------
==================================================================================================*/


if(!defined('REQUEST_GET')) define('REQUEST_GET', '_GET');
if(!defined('REQUEST_POST')) define('REQUEST_POST', '_POST');
if(!defined('REQUEST_COOKIE')) define('REQUEST_COOKIE', '_COOKIE');
if(!defined('REQUEST_HEADER')) define('REQUEST_HEADER', '_HEADER');
if(!defined('REQUEST_INFO')) define('REQUEST_INFO', '_INFO');
if(!defined('REQUEST_FILE')) define('REQUEST_FILE', '_FILE');


class Request{

	use Core_Trait_SingletonUnique, Core_Trait_Array, Core_Trait_Main;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Кеш $_GET, $_POST, $_COOKIE, $_FILES и заголовки
	public $cache = array();

	#Метод HTTP запроса
	public $method = 'GET';

	#Признак AJAX запроса
	public $is_ajax = false;

	#Признак RAW запроса
	public $is_raw = false;

	#SSL соединение
	public $is_ssl = false;

	#Action - действие
	public $action = null;

	#Предопределенный путь
	public $path = null;

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
		'log_file'	=> 'Core/Request'
	);








	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	protected function init(){

		#Метод запроса:  'GET', 'HEAD', 'POST', 'PUT'
		$this->method = $_SERVER['REQUEST_METHOD'];

		#SSL соединение
		$this->is_ssl = $this->isSSL();

		#AJAX запрос
		$is_ajax = $this->getGPCValue('ajax');
		$this->is_ajax = ($is_ajax !== null ? ($is_ajax == 1 ? true : false) : $this->isAjax());

		#RAW запрос
		$is_raw = $this->getGPCValue('raw');
		$this->is_raw = ($is_raw == '1' || $is_raw == 'On' ? true : false);

		#Action - действие
		$this->action = $this->getGPCValue('action');

		#Предопределенный путь
		$this->path = $this->getGPCValue('path');

	}#end function



	#--------------------------------------------------
	# Вызов недоступных методов
	#--------------------------------------------------
	public function __call($name, $args){

		switch($name){

			case 'get':
			case 'post':
			case 'cookie':
				return $this->keyGet((isset($args[0]) ? $args[0] : null), '_'.strtoupper($name), (isset($args[1]) ? $args[1] : null));
			break;

			case 'file':
				$this->getRequestFiles();
				return $this->keyGet((isset($args[0]) ? $args[0] : null), '_FILE', (isset($args[1]) ? $args[1] : null));
			break;

			case 'headers':
				return $this->getHeaders();
			break;

			case 'header':
				return $this->getHeader((isset($args[0]) ? $args[0] : null), (isset($args[1]) ? $args[1] : null));
			break;

			case 'full':
				return $this->getFullRequestInfo();
			break;

			case 'info':
				return $this->getRequestInfo((isset($args[0]) ? $args[0] : null), (isset($args[1]) ? $args[1] : null));
			break;


			case 'files':
				return $this->getRequestFiles();
			break;

			case 'gpc':
			case 'pick':
				return $this->getGPCValue((isset($args[0]) ? $args[0] : null), (isset($args[1]) ? $args[1] : 'pg'), (isset($args[2]) ? $args[2] : null));
			break;


			default: 
				return null;

		}

	}#end function






	/*==============================================================================================
	Получение параметров запроса
	==============================================================================================*/


	#--------------------------------------------------
	# Функция, проверяющая произведен ли запрос по AJAX
	#--------------------------------------------------
	public function isAjax(){
		return (strcasecmp($this->getHeader('x-requested-with'), 'XMLHttpRequest') == 0);
	}#end function



	#--------------------------------------------------
	# Функция, проверяющая произведен ли запрос с SSL
	#--------------------------------------------------
	public function isSSL() {
		return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
	}#end function



	#--------------------------------------------------
	# Получение заголовков запроса
	#--------------------------------------------------
	public function getHeaders(){

		if(isset($this->cache[REQUEST_HEADER])) return $this->cache[REQUEST_HEADER];

		if(!function_exists('getallheaders')){
			$this->cache[REQUEST_HEADER] = array();

			foreach ($_SERVER as $k=>$v){

				if (strncmp($k, 'HTTP_', 5) == 0){ 
					$k = strtr(ucwords(strtolower(strtr(substr($k, 5), '_', ' '))),' ','-'); 
					$this->cache[REQUEST_HEADER][$k] = $v; 
				} else if ($k == 'CONTENT_TYPE') { 
					$this->cache[REQUEST_HEADER]['Content-Type'] = $v;
				} else if ($k == 'CONTENT_LENGTH') { 
					$this->cache[REQUEST_HEADER]['Content-Length'] = $v;
				}

			}#foreach

		}
		else
			$this->cache[REQUEST_HEADER] = getallheaders();

		return $this->cache[REQUEST_HEADER];
	}#end function

 
	#--------------------------------------------------
	# Получение определенного заголовка
	#--------------------------------------------------
	public function getHeader($key, $default=null){

		if(empty($key)) return $default;
		$this->getHeaders();
		$key = self::ucwordsHyphen($key);

		return isset($this->cache[REQUEST_HEADER][$key]) ? $this->cache[REQUEST_HEADER][$key] : $default;
	}#end function



	#--------------------------------------------------
	# Получение информации о запросе клиента
	#--------------------------------------------------
	# <pre>
	# scheme  user  password  host  port  basePath   relativeUrl
	#   |      |      |        |      |    |             |
	# /--\   /--\ /------\ /-------\ /--\/--\/----------------------------\
	# http://stas:x0y17575@sintez.ru:8042/ru/manual.php?name=param#fragment  <-- absoluteUrl
	#        \__________________________/\____________/^\________/^\______/
	#                     |                     |           |         |
	#                 authority               path        query    fragment
	# </pre>
	#
	# - authority:   [user[:password]@]host[:port]
	# - hostUrl:     http://user:password@nette.org:8042
	# - basePath:    /en/ (everything before relative URI not including the script name)
	# - baseUrl:     http://user:password@nette.org:8042/en/
	# - relativeUrl: manual.php
	public function getFullRequestInfo(){

		if(isset($this->cache[REQUEST_INFO])) return $this->cache[REQUEST_INFO];
		$host = (strncasecmp('www.', $_SERVER['HTTP_HOST'], 4)==0) ? substr($_SERVER['HTTP_HOST'], 4) : $_SERVER['HTTP_HOST'];
		$protocol = ($this->is_ssl ? 'https' : 'http');
		$document_uri = preg_replace('/\/\/+/', '/', strtok($_SERVER['REQUEST_URI'],'?#'));
		$path = (!empty($this->path) ? trim($this->path) : $document_uri);
		if(empty($path)) 
			$path = 'core/main';
		else
		#Фильтр Alias:
		#Замена текущего document_uri предопределенными значениями из файла конфигурации [DIR_CONFIG]/aliases.config.php
		if( ($alias = Config::getOption('aliases', $path)) !== false) $path = $alias;

		$path = trim($path,'/');
		$pathlist = explode('/',$path);

		if(count($pathlist)==1){
			if(strcasecmp('core',$pathlist[0])!=0) 
				array_unshift($pathlist,'core');
			else 
				$pathlist = array('core','main');
		}
		$pathcount = count($pathlist);
		$this->cache[REQUEST_INFO] = array(
			'ajax'		=> $this->is_ajax,				#Признак, указывающий что данный запрос - AJAX запрос (если TRUE)
			'raw'		=> $this->is_raw,				#Признак, указывающий что данный запрос - RAW запрос (если TRUE)
			'ssl'		=> $this->is_ssl,				#Признак, указывающий что данный запрос по SSL соединению (если TRUE)
			'tmpl'		=> $this->getGPCValue('tmpl','pg',0),	#Признак, указывающий что при наличии темплейта, полученного от чанков, темплейт должен быть передан с ответом в JSON объекте
														#Принимает значения: 0 - не отдавать темплейт в JSON, 1 - отдавать темплейт, только если он задан в чанках, 2 - отдавать всегда темплейт (если в чанках не задан, возвращается корневой темплейт)
			'tmpl_id'	=> $this->getGPCValue('tmpl_id'),	#Текстовый идентификатор темплейта страницы, с которой пришел запрос
			'method'	=> $_SERVER['REQUEST_METHOD'],	#Метод запроса: GET или POST
			'protocol'	=> $protocol,					#Протокол: http
			'host'		=> $host,						#Имя хоста: domain.com
			'idn_host'	=> (strpos($host, 'xn--') === 0 || strpos($host, '.xn--') !== false) ? $this->punyCodeToUTF($host) : $host,
			'port'		=> $_SERVER['SERVER_PORT'],		#Порт, по которому запрос: 80
			'url'		=> $protocol.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],	#Полный URL запроса: http://domain.com/app/settings/user/general?var=value&foo=boo
			'uri'		=> $_SERVER['REQUEST_URI'],		#Запрошенный URI: /app/settings/user/general?var=value&foo=boo
			'document'	=> $document_uri,				#Запрошенный документ: /app/settings/user/general
			'query'		=> $_SERVER['QUERY_STRING'],	#Строка переданных параметров методом GET: var=value&foo=boo
			'script'	=> $_SERVER['SCRIPT_NAME'],		#Реальный скрипт, обрабатывающий запрос: /index.php
			'userauth'	=> Acl::_userSessionCheck(),	#Признак, указывающий что клиент аутентифицирован (если TRUE)
			#Реальный PATH запроса: задается только один раз, доступен только для чтения
			'path'		=> $path,						#Путь до страницы: /app/settings/user/general, отличается от 'document' тем, что
														#может быть передан в запросе GET/POST методом, а также проходит через фильтр Alias
			'pathlist'	=> $pathlist,					#Путь до страницы, разбитый в массив: array('app','settings','user','general')
			'pathcount'	=> $pathcount,					#Количество элементов в массиве $pathlist: 4
			'module'	=> $pathlist[0],				#Имя модуля: 'app'
			'dir'		=> array_slice($pathlist,1,$pathcount-2),	#Директория страницы: array('settings','user')
			'page'		=> $pathlist[$pathcount-1],		#Имя страницы: 'general'
			'action'	=> $this->getGPCValue('action'),#Запрошенное действие, может не задаваться
			#Внутренний PATH запроса: доступен для записи, начальные значения равны Реальному PATH запроса
			#Модули должны работать и обрабатывать данные внутреннего PATH запроса
			'ipathlist'	=> $pathlist,					#Путь до страницы, разбитый в массив: array('app','settings','user','general')
			'ipathcount'=> $pathcount,					#Количество элементов в массиве $pathlist: 4
			'imodule'	=> $pathlist[0],				#Имя модуля: 'app'
			'idir'		=> array_slice($pathlist,1,$pathcount-2),	#Директория страницы: array('settings','user')
			'ipage'		=> $pathlist[$pathcount-1],		#Имя страницы: 'general'
		);

		return $this->cache[REQUEST_INFO];
	}#end function



	#--------------------------------------------------
	# Получение информации о запросе клиента
	#--------------------------------------------------
	public function getRequestInfo($key, $default=null){

		if(empty($key)) return $default;
		if(empty($this->cache[REQUEST_INFO])) $this->getFullRequestInfo();

		return isset($this->cache[REQUEST_INFO][$key]) ? $this->cache[REQUEST_INFO][$key] : $default;
	}#end function



	#--------------------------------------------------
	# Запись в информацию о запросе клиента
	#--------------------------------------------------
	public function setRequestInfo($key, $value=null){

		if(empty($key)) return false;
		if(empty($this->cache[REQUEST_INFO])) $this->getFullRequestInfo();
		$this->cache[REQUEST_INFO][$key] = $value;

		return true;
	}#end function



	#--------------------------------------------------
	# Получение информации о текущем пути запроса
	#--------------------------------------------------
	public function getRequestPath($real=true){
		if(empty($this->cache[REQUEST_INFO])) $this->getFullRequestInfo();
		return ($real) ? '/'.implode('/',$this->cache[REQUEST_INFO]['pathlist']) : '/'.implode('/',$this->cache[REQUEST_INFO]['ipathlist']);
	}#end function



	#--------------------------------------------------
	# Запись в информацию о запросе клиента нового пути
	#--------------------------------------------------
	public function setRequestPath($path=null){

		if(empty($this->cache[REQUEST_INFO])) $this->getFullRequestInfo();

		if(empty($path)) 
			$path = 'core/main';
		else
		#Фильтр Alias:
		#Замена текущего document_uri предопределенными значениями из файла конфигурации [DIR_CONFIG]/aliases.config.php
		if( ($alias = Config::getOption('aliases', $path)) !== false) $path = $alias;

		$path = trim($path,'/');
		$pathlist = explode('/',$path);

		if(count($pathlist)==1){
			if(strcasecmp('core',$pathlist[0])!=0) 
				array_unshift($pathlist,'core');
			else 
				$pathlist = array('core','main');
		}
		$pathcount = count($pathlist);

		$this->cache[REQUEST_INFO]['ipath']		= $path;			#Путь до страницы: /app/settings/user/general
		$this->cache[REQUEST_INFO]['ipathlist']	= $pathlist;		#Путь до страницы, разбитый в массив: array('app','settings','user','general')
		$this->cache[REQUEST_INFO]['ipathcount']= $pathcount;		#Количество элементов в массиве $pathlist: 4
		$this->cache[REQUEST_INFO]['imodule']	= $pathlist[0];		#Имя модуля: 'app'
		$this->cache[REQUEST_INFO]['idir']		= array_slice($pathlist,1,$pathcount-2);	#Директория страницы: array('settings','user')
		$this->cache[REQUEST_INFO]['ipage']		= $pathlist[$pathcount-1];					#Имя страницы: 'general'

		return true;
	}#end function



	/*
	 * Функция восстанавливает измененный внутренний путь запроса и задает 
	 * путь запроса такой же как и реальный
	 */
	public function restoreRequestPath(){

		if(empty($this->cache[REQUEST_INFO])){
			$this->getFullRequestInfo();
			return;
		}
		$this->setRequestPath($this->cache[REQUEST_INFO]['path']);

	}#end function



	/*
	#--------------------------------------------------
	# Получение информации о загруженных файлах
	#--------------------------------------------------
	#
	# Тут же выполняется преобразование массива $_FILES во внутренний формат.
	#
	# Исходный формат $_FILES:
	# Array (
	#	[image] => Array(
	#		[name] => Array([0] => 400.png)
	#		[type] => Array([0] => image/png)
	#		[tmp_name] => Array([0] => /tmp/php5Wx0aJ)
	#		[error] => Array([0] => 0)
	#		[size] => Array([0] => 15726)
	#	)
	# )
	#
	# Получаемый формат:
	# Array(
	#	[image] => Array(
	#		[0] => Array(
	#			[name] => 400.png
	#			[type] => image/png
	#			[tmp_name] => /tmp/php5Wx0aJ
	#			[error] => 0
	#			[size] => 15726
	#		)
	#	)
	# )
	*/
	public function getRequestFiles(){

		if(!isset($_FILES)||empty($_FILES)) return array();
		if(isset($this->cache[REQUEST_FILE])) return $this->cache[REQUEST_FILE];

		$this->cache[REQUEST_FILE] = array();
		foreach ($_FILES as $key => $items){
			foreach ($items as $i => $value){
				$this->cache[REQUEST_FILE][$i][$key] = ($key == 'name') ? $this->toUTF($value) : $value;
			}
		}

		return $this->cache[REQUEST_FILE];
	}#end function











	/*
	 * ==============================================================================================
	 * Работа с массивами _GET _POST _COOKIE
	 * ==============================================================================================
	 */


	/**
	 * Конвертация в UTF8
	 *
	 * Функция определяет кодировку входного текста и если кодировка отличается от UTF-8, 
	 * конвертирует строку в UTF-8
	 * 
	 * @package Core
	 * @subpackage Request
	 * 
	 * @param mixed $mixed Текстовая строка или массив со строками, которые требуются конвертировать в UTF-8
	 * 
	 * @return string
	 */
	protected function toUTF($mixed){

		if(is_array($mixed)){
			$result = array();
			foreach ($mixed as $k=>$v)
				$result[$k] = $this->toUTF($v);
			return $result;
		}
		else
			return (is_string($mixed) && 'UTF-8' != ($charset = mb_detect_encoding($mixed, array('ASCII', 'UTF-8', 'Windows-1251'))))
				? mb_convert_encoding($mixed, 'UTF-8', $charset)
				: $mixed;
	}#end function



	/**
	 * Получение значения из массива GPC
	 *
	 * Функция просмотривает массивы _GET _POST _COOKIE в заданной последовательности и 
	 * пытается вернуть значение по указанному ключу, если в массивах 
	 * 
	 * @package Core
	 * @subpackage Request
	 * 
	 * @param mixed $key Ключ, по которому требуется получить значение, 
	 * может быть задан в виде массива (путь ключа) или в виде текста (сам ключ),
	 * 
	 * @param mixed $default Значение, возвращаемое если ничего не найдено по ключу в _GET _POST _COOKIE
	 * 
	 * @param string $gpc Последовательность просмотра массивов, может состоять из дрех символов в любых вариациях: 
	 * "g" - поиск в массиве _GET
	 * "p" - поиск в массиве _POST
	 * "c" - поиск в массиве _COOKIE
	 * Примеры:
	 * 'pgc' - будет осуществлен поиск значения сначала в _POST, потом в _GET, потом в _COOKIE, если не найдено - вернет $default
	 * 'pg' - будет осуществлен поиск значения сначала в _POST, потом в _GET, если не найдено - вернет $default
	 * 'gc' - будет осуществлен поиск значения сначала в _GET, потом в _COOKIE, если не найдено - вернет $default
	 * 
	 * @return string
	 */
	public function getGPCValue($key=null, $gpc='pg', $default=null){

		if(empty($gpc)||!is_string($gpc)) return $default;

		$arr = str_split($gpc, 1);

		foreach($arr as $v){

			switch($v){

				#_GET
				case 'g':
					if( ($result = $this->keyGet($key, REQUEST_GET, null)) !== null) return $result;
				break;

				#_POST
				case 'p':
					if( ($result = $this->keyGet($key, REQUEST_POST, null)) !== null) return $result;
				break;

				#_COOKIE
				case 'c':
					if( ($result = $this->keyGet($key, REQUEST_COOKIE, null)) !== null) return $result;
				break;

			}

		}

		return $default;
	}#end function



	/**
	 * Получение параметра из массива
	 *
	 * Функция просмотривает заданный массив _GET _POST _COOKIE и 
	 * пытается вернуть значение по указанному ключу, если ключ не найден,
	 * возвращается значение, указанное в $default
	 * 
	 * @package Core
	 * @subpackage Request
	 * 
	 * @param mixed $key Ключ, по которому требуется получить значение, 
	 * может быть задан в виде массива (путь ключа) или в виде текста (сам ключ),
	 * 
	 * @param string $type Текстовое наименование глобального массива
	 * 
	 * @param mixed $default Значение, возвращаемое если ничего не найдено по ключу в указанном массиве
	 * 
	 * @return mixed
	 */
	public function keyGet($key=null, $type=null, $default=null){

		if(empty($key)||empty($type)||!isset($GLOBALS[$type])) return $default;

		$result		= self::arrayGetValue($GLOBALS[$type], $key, null);
		if(is_null($result)) return $default;

		$iterator	= (isset($this->cache[$type])) ? self::arrayGetValue($this->cache[$type], $key, null) : null;
		if(!is_null($iterator)) return $iterator;

		$result = $this->toUTF($result);
		$this->keySet($key, $result, $type);

		return $result;
	}#end function



	/**
	 * Запись параметра в массив
	 *
	 * Функция просмотривает заданный массив _GET _POST _COOKIE и 
	 * пытается вернуть значение по указанному ключу, если ключ не найден,
	 * возвращается значение, указанное в $default
	 * 
	 * @package Core
	 * @subpackage Request
	 * 
	 * @param mixed $key Ключ, по которому требуется записать значение, 
	 * может быть задан в виде массива (путь ключа) или в виде текста (сам ключ),
	 * 
	 * @param mixed $value Значение, присваиваемое в массиве по указанному ключу
	 * @param string $type Текстовое наименование глобального массива, в который требуется записать значение
	 * 
	 * @return bool
	 */
	private function keySet($key=null, $value=null, $type=null){

		if(empty($key)||empty($type)) return false;
		if(!isset($this->cache[$type])) $this->cache[$type] = array();

		return self::arraySetValue($this->cache[$type], $key, $value);
	}#end function




	#--------------------------------------------------
	# Конвертация из punycode в UTF-8
	#--------------------------------------------------
	protected function punyCodeToUTF($input){

		$input = trim($input);
		$punycode_prefix = 'xn--';
		$decode_digit = function($cp) {
			$cp = ord($cp);
			return ($cp - 48 < 10) ? $cp - 22 : (($cp - 65 < 26) ? $cp - 65 : (($cp - 97 < 26) ? $cp - 97 : 36));
		};
		$adapt = function($delta, $npoints, $is_first) {
			$delta = intval($is_first ? ($delta / 700) : ($delta / 2));
			$delta += intval($delta / $npoints);
			for ($k = 0; $delta > ((36 - 1) * 26) / 2; $k += 36) {
				$delta = intval($delta / (36 - 26));
			}
			return intval($k + (36 - 1 + 1) * $delta / ($delta + 38));
		};
		$ucs4_to_utf8 = function($input) {
			$output = '';
			foreach ($input as $k => $v) {
				if ($v < 128)
					$output .= chr($v);
				elseif ($v < (1 << 11))
					$output .= chr(192+($v >> 6)).chr(128+($v & 63));
				elseif ($v < (1 << 16))
					$output .= chr(224+($v >> 12)).chr(128+(($v >> 6) & 63)).chr(128+($v & 63));
				elseif ($v < (1 << 21))
					$output .= chr(240+($v >> 18)).chr(128+(($v >> 12) & 63)).chr(128+(($v >> 6) & 63)).chr(128+($v & 63));
				else
					return false;
			}
			return $output;
		};
		$decode = function($encoded) use($punycode_prefix, $decode_digit, $adapt, $ucs4_to_utf8) {
			$decoded = array();

			$encode_test = preg_replace('!^'.preg_quote($punycode_prefix, '!').'!', '', $encoded);

			// If nothing left after removing the prefix, it is hopeless
			if (!$encode_test)
				return $encoded;

			// Find last occurence of the delimiter
			$delim_pos = strrpos($encoded, '-');

			if ($delim_pos > ($pref_len = strlen((binary)$punycode_prefix))) {
				for ($k = $pref_len; $k < $delim_pos; ++$k) {
					$decoded[] = ord($encoded{$k});
				}
			}
			$deco_len = count($decoded);
			$enco_len = strlen((binary)$encoded);
			
			// Wandering through the strings; init
			$is_first = true;
			$bias = 72;
			$idx = 0;
			$char = 0x80;
			for ($enco_idx = ($delim_pos) ? ($delim_pos + 1) : 0; $enco_idx < $enco_len; ++$deco_len) {
				for ($old_idx = $idx, $w = 1, $k = 36; 1 ; $k += 36) {
					$digit = $decode_digit($encoded{$enco_idx++});
					$idx += $digit * $w;
					$t = ($k <= $bias) ? 1 :
						(($k >= $bias + 26) ? 26 : ($k - $bias));
					if ($digit < $t) break;
					$w = (int) ($w * (36 - $t));
				}
				$bias = $adapt($idx - $old_idx, $deco_len + 1, $is_first);
				$is_first = false;
				$char += (int) ($idx / ($deco_len + 1));
				$idx %= ($deco_len + 1);
				if ($deco_len > 0) {
					// Make room for the decoded char
					for ($i = $deco_len; $i > $idx; $i--) $decoded[$i] = $decoded[($i - 1)];
				}
				$decoded[$idx++] = $char;
			}
			return $ucs4_to_utf8($decoded);
		};
		
		if (strpos($input, '.') !== false) {
			$output = [];
			foreach (explode('.', $input) as $chunk) {
				$conv = $decode($chunk);
				$output[] = $conv ? $conv : $chunk;
			}
			$output = join('.', $output);
		}
		else
			$output = $decode($input);
		
		return $output;

	}#end function


}#end class


?>