<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс обработки заголовков ответа и Cookies
Версия	: 1.0.0/ALPHA
Дата	: 2012-05-14
Авторы	: Станислав В. Третьяков, Илья Гилёв
--------------------------------
==================================================================================================*/



class Response{

	use Core_Trait_SingletonUnique, Core_Trait_BaseError, Core_Trait_Main;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	protected $httpcodes = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported'
	);


	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_PHP_ERROR, 'Вызов недопустимого метода или функции класса'),
		5	=> array(EVENT_HEADERS_ERROR, 'Невозможно добавить заголовок, поскольку заголовки уже были отправлены клиенту')
	);


	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/Request'
	);



	#Признак, указывающий что заголовки уже были отправлены в ответе
	protected $sent = false;

	#Массив заголовков Headers
	protected $headers = array();

	#Массив Cookies
	protected $cookies = array();



	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	protected function init(){

		#Определение, были ли уже отправлены заголовки или нет
		$this->sent = headers_sent();

	}#end function



	#--------------------------------------------------
	# Деструктор класса
	#--------------------------------------------------
	public function __destruct(){
		return $this->sendHeaders();
	}#end function



	#--------------------------------------------------
	# Чтение данных из недоступных свойств
	#--------------------------------------------------
	public function __get($name){
		
	}#end function



	#--------------------------------------------------
	# Вызов недоступных методов
	#--------------------------------------------------
	public function __call($name, $arguments){

	}#end function








	/*==============================================================================================
	Работа с Headers
	==============================================================================================*/



	#--------------------------------------------------
	# Отправка HTTP заголовков клиенту
	#--------------------------------------------------
	public function sendHeaders(){

		if(($this->sent = headers_sent())!==false) return false;

		#Отправка заголовков
		foreach($this->headers as $k=>$v){
			if(is_numeric($k)) header($k, true); else header($k.': '.$v, true);
		}

		#Отправка Cookies
		$this->sendCookies();

		$this->sent = true;
		return true;
	}#end function



	#--------------------------------------------------
	# Добавление HTTP заголовка
	#--------------------------------------------------
	public function add($key, $value=null){

		if(($this->sent = headers_sent())!==false) return $this->doErrorEvent(5, __FUNCTION__, __LINE__, $key.': '.$value);
		if(empty($key)) return false;
		if($value !== null) $this->headers[self::ucwordsHyphen(trim($key," :\r\n\t"))] = $value;
		else $this->headers[] = $key;

		return true;
	}#end function



	#--------------------------------------------------
	# Добавление HTTP заголовка: Location
	#--------------------------------------------------
	public function location($location) {
		$this->add('Location', $location);
	}#end function



	#--------------------------------------------------
	# Добавление HTTP заголовка: Content-Type
	#--------------------------------------------------
	public function contentType($media, $charset = '') {
		$this->add('Content-Type', $media . (empty($charset) ? '': '; charset=' . $charset));
	}#end function



	#--------------------------------------------------
	# Добавление HTTP заголовка: Content-Disposition
	#--------------------------------------------------
	public function contentDisposition($filename, $disposition = 'inline') {
		$this->add('Content-Disposition', $disposition . '; filename="' . $filename . '"');
	}#end function



	#--------------------------------------------------
	# Добавление HTTP заголовка: Статус ответа сервера
	#--------------------------------------------------
	public function status($httpcode) {
		$this->add('Status', $httpcode . ' ' . $this->httpcodes[$httpcode]);
		http_response_code($httpcode);
	}#end function



	#--------------------------------------------------
	# Добавление HTTP заголовка: Last-Modified
	#--------------------------------------------------
	public function lastModified($date){
		$date = (empty($date) ? gmdate('D, d M Y H:i:s \G\M\T', time()) : ( is_numeric($date) ?  gmdate('D, d M Y H:i:s \G\M\T', $date) : $date));
		$this->add('Last-Modified', $date);
	}#end function



	#--------------------------------------------------
	# Добавление HTTP заголовка: Etag
	#--------------------------------------------------
	public function etag($etag) {
		$this->add('Etag', $etag);
	}#end function








	/*==============================================================================================
	Работа с Cookie
	==============================================================================================*/




	#--------------------------------------------------
	# Отправка Cookies клиенту
	#--------------------------------------------------
	public function sendCookies() {

		$return = true;
		foreach($this->cookies as $cookie)
			$return &= setcookie($cookie['name'], $cookie['value'], $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);

		return $return;
	}#end function



	#--------------------------------------------------
	# Добавление Cookies
	#--------------------------------------------------
	public function addCookie($name, $value, $expire=0, $path = '', $domain = '', $secure = false, $httponly = false){

		if(empty($name)) return false;
		$this->cookies[$name] = array(
			'name'		=> $name,
			'value'		=> $value,
			'expire'	=> $expire,
			'path'		=> $path,
			'domain'	=> empty($domain) ? '' : '.'.ltrim($domain, " .\t\r\n"),
			'secure'	=> $secure,
			'httponly'	=> $httponly
		);
		return true;
	}#end function



	#--------------------------------------------------
	# Удаление Cookies
	#--------------------------------------------------
	public function deleteCookie($name){

		if(empty($name)) return false;
		if(isset($this->cookies[$name])) unset($this->cookies[$name]);

		return true;
	}#end function




}#end class


?>