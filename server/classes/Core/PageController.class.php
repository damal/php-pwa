<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Контроллер запросов страниц к ядру
Версия	: 1.0.0/ALPHA
Дата	: 2012-06-08
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/



class PageController{

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
		'log_file'	=> 'Core/PageController'
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
		return PageController::getInstance($options);
	}#end function


	/*
	 * Конструктор класса
	 */
	protected function init($options=null){

		Event::_addListener(
			EVENT_PAGE_UNAUTH_CHUNKS,
			array(
				$this,
				'getChunk'
			)
		)->addListener(
			EVENT_PAGE_GET_CHUNKS,
			array(
				$this,
				'getChunk'
			)
		);

	}#end function







	/*==============================================================================================
	Функции контроллера - обработка страниц
	==============================================================================================*/



	/*
	 * Обработка запрашиваемой страницы: для не аутентифицированных клиентов
	 */
	public function getChunk($event_name=null, $data=null){

		#Определение, какому модулю адресован запрос
		$this->module = ucfirst(Request::_getRequestInfo('imodule'));

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

		#Определение, относится ли запрос к контроллеру ядра
		//if($this->module == 'Core') return false;

		#Поскольку запрос явно к модулю Core, то устанавливаем чанку максимальный приоритет
		$this->chunk->setPriority(CHUNK_PRIORITY_OWNER);

		#Определение, относится ли запрос к контроллеру ядра
		if($this->module == 'Core'){

			switch($this->page){

				#Аутентификация
				case 'login':
					$this->doLogin();
				break;

				#Выход
				case 'logout':
					$this->doLogout();
				break;

				#Ошибка
				case '403':
				case '404':
				case '500':
					$this->doError($this->page);
				break;

				#Пинг
				case '200':
				case 'ping':
					$this->chunk->setDie('ok');
				break;

				case 'ver':
				case 'credits':
					$this->chunk->setDie(
					'<html><head><title>About application</title></head><body>'.
					'<h2>Server core v.1.2.2 at 16/09/2011</h2>Copyright &copy; Stanislav V. Tretyakov (svtrnd@gmail.com)<br/>License: <a href="http://www.gnu.org/licenses/gpl">The GNU General Public License v3.0</a><br/><br/>'.
					'<h2>Client core v.1.0.0 at 01/06/2012</h2>Copyright &copy; Ilya S. Gilev<br/>License: <a href="http://www.gnu.org/licenses/gpl">The GNU General Public License v3.0</a>'.
					'</body></html>');
				break;

			#Остальные страницы ядра
			default:

				#Для не аутентифицированных пользователей - LOGIN страница
				if($event_name == EVENT_PAGE_UNAUTH_CHUNKS){
					Request::_setRequestPath('core/login');
					$this->pathlist	= array('core','login');
					$this->dir		= '/';
					$this->page		= 'login';
					$this->doLogin();
				}
				#Аутентифицированные пользователи
				else{

					#Серверный файл 
					if(!$this->doPage()){
						Request::_setRequestPath('core/404');
						$this->pathlist	= array('core','404');
						$this->dir		= '/';
						$this->page		= '404';
						$this->doError(404);
					 }

				}

			}#switch

			return $this->chunk->build();

		}#$this->module == 'Core'

		if($event_name == EVENT_PAGE_UNAUTH_CHUNKS) return false;
		#Запрошена страница не модуля Core
		#Проверяем активность модуля
		if(($module = Config::getOption('modules',$this->module))===false) return false;
		if(empty($module['active'])) return false;
		if(!$this->doPage()) return false;

		return $this->chunk->build();
	}#end function



	/*
	 * Запрошенная страница
	 */
	private function doPage(){

		#Серверный файл 
		$found = false;
		$php_code = DIR_MODULES.'/'.$this->module.'/php_code'.$this->dir.'/'.$this->page.'.code.php';
		$html_template = '/'.$this->module.'/html_template'.$this->dir.'/'.$this->page.'.html.php';
		if(file_exists(DIR_MODULES.$html_template)&&is_readable(DIR_MODULES.$html_template)){
			$this->chunk->setTemplateFile($html_template);
			$found = true;
		}
		if(file_exists($php_code)&&is_readable($php_code)){
			include($php_code);
			$found = true;
		}

		return $found;
	}#end function



	/*
	 * Вход
	 */
	private function doLogin(){

		$this->chunk->disableWidgets();

		#Метод запроса - POST
		if($this->method == 'POST'){

			$login		= Request::_keyGet('username', REQUEST_POST, null);
			$password	= Request::_keyGet('password', REQUEST_POST, null);

			#Аутентификация
			if(!empty($login)&&!empty($password)){

				#Если аутентификация пользователя прошла неудачно - обработка ошибок
				if(Acl::_userAuth($login,$password)==false){

					switch(Acl::_getErrno()){
						case 110: #Не задано Имя пользователя и/или пароль
						case 120: #Имя пользователя и/или пароль указаны неверно
						case 130: #Учетная запись заблокирована
						case 135: #Ваша учетная запись не входит ни в одну группу доступа. Обратитесь к администратору.
							$this->chunk->addMsgCustom('Ошибка аутентификации',Acl::_getErrstr(),'error');
						break;
						case 145: #Для Вашей учетной записи не были присвоены какие-либо права доступа. Обратитесь к администратору.
							$this->chunk->addMsgCustom('Внимание',Acl::_getErrstr(), 'warning');
						break;
					default:
						$this->chunk->addMsgCustom('Внимание','Сервис временно недоступен, попробуйте позднее: '.Acl::_getErrno(), 'warning');
					}
				}
				#Аутентификация пользователя прошла успешно
				else{

					#Если была явно запрошена LOGIN страница - редирект на /main
					if(
						Request::_getRequestInfo('module') == 'core' && 
						Request::_getRequestInfo('page') == 'login'
					){
						$this->doLocation('/main');
						$this->chunk->setTmplStatus(2);
					}
					#Если была запрошена не LOGIN страница - запрос контента
					else{
						$this->doLocation('/'.Request::_getRequestInfo('path'));
					}

					return;
				}

			}#Аутентификация

		}#Метод запроса - POST

		#Чанк LOGIN страницы
		$this->chunk->setTemplateFile(Config::getOption('Core/main','login_template','Core/html_template/template_login.php'));
		$this->chunk->setLayout('ui-login-layout.js');


	}#end function




	/*
	 * Выход
	 */
	private function doLogout(){
		Session::_stop();
		$this->doLocation('/login');
		$this->chunk->setTmplStatus(2);
	}#end function




	/*
	 * Ошибка
	 */
	private function doError($errno=404){
		Request::_setRequestPath('core/'.$errno);
		$this->chunk->setTitle($errno);
		$this->chunk->setHttpCode($errno);
		$this->chunk->setTemplateFile(Config::getOption('Core/main','error_template','Core/html_template/template_error.php'));
		$this->chunk->addHtml('httpcode',$errno);
		$this->chunk->disableWidgets();
	}#end function



	/*
	 * Редирект
	 */
	private function doLocation($location='/main'){

		$this->chunk->setUnique(true);
		if($this->ajax){
			$this->chunk->setPushLocation($location);
		}else{
			$this->chunk->addHeader('Location',$location);
		}
		$this->chunk->disableWidgets();

	}#end function






}#end class



?>