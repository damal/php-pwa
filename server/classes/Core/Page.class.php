<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс подготовки странрицы ответа
Версия	: 1.0.0/ALPHA
Дата	: 2012-06-04
Авторы	: Станислав В. Третьяков, Илья Гилёв
--------------------------------
==================================================================================================*/


class Page{

	use Core_Trait_SingletonUnique, Core_Trait_BaseError;

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
		'log_file'	=> 'Core/Page'
	);


	#Результирующий массив контента
	private $content = array(
			'headers'	=> array(),
			'httpcode'	=> 200,
			'html'		=> array(),
			'template'	=> null,
			'tmplfile'	=> null,
			'actions'	=> array(),
			'noCache'	=> false,
			'location'	=> null,
			'download'	=> null,
			'title'		=> null,
			'status'	=> 'ok',
			'messages'	=> array(),
			'data'		=> null,
			'tmpl'		=> 0,
			'layout'	=> null
	);
	private $headers 	= array(); #$this->content['headers']
	private $html 		= array(); #$this->content['html']
	private $js 		= array(); #Все кроме $this->content['headers'] и $this->content['html']

	private $parser_html= array(); #Внутренний массив для parseTemplate() и parseTemplateMacro()








	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	/*
	 * Конструктор класса
	*/
	protected function init(){


	}#end function













	/*==============================================================================================
	Построение ответа
	==============================================================================================*/



	/*
	 * Построение результирующего массива контента
	 * 
	 * $content - указатель на массив, в который следует записать результат
	 * $event_name - генерируемое событие для запроса чанков
	 * $pathlist - путь для которого запрашивается контент
	 * $iteration - уровень вложенности при рекурсивном запросе чанков, максимальная вложенность - 10
	 * $data - произвольные данные, передаваемые при вызове события
	 * 
	 * Возвращает TRUEб если чанки были найдены и FALSE в противном случае
	 */
	private function buildContent(&$content, $event_name=EVENT_PAGE_GET_CHUNKS, $iteration=0, $data=null){

		if($iteration > 10) return false;
		$chunks_not_found = false;

		fstart:

		#Событие - построение содержимого вывода разными модулями
		$chunks = Event::_fireEventWithResult($event_name, $data);

		#Сортировка чанков, полученных от модулей по приоритету, если получено несколько чанков
		if(count($chunks)>1){
			usort($chunks, function($a, $b){
				if(!isset($a['data']['_opt_']['priority'])||!isset($b['data']['_opt_']['priority']))return 1;
				if($a['data']['_opt_']['priority'] == $b['data']['_opt_']['priority']) return 1;
				return ($a['data']['_opt_']['priority'] > $b['data']['_opt_']['priority']) ? -1 : 1;
			});
		}

		#Сколько реально найдено чанков
		$found = 0;

		#Сливаем куски результатов от функций в единый массив $content
		foreach($chunks as $ch){

			$chunk = &$ch['data'];
			if(empty($chunk)&&!is_array($chunk)) continue;


			$found++;

			#Аналзирует опции модуля переданные ядру
			if(!empty($chunk['_opt_'])){
				if(!empty($chunk['_opt_']['exit'])) exit;
				if(!empty($chunk['_opt_']['die'])) die($chunk['_opt_']['die']);
				if(!empty($chunk['_opt_']['event'])){
					$this->buildContent((!empty($chunk['_opt_']['unique']) ? $chunk : $content), $chunk['_opt_']['event'], $iteration+1, $data);
				}
				if(!empty($chunk['_opt_']['unique'])){
					$content = $chunk;
					return $chunk;
				}
				if(!empty($chunk['_opt_']['no_widgets'])) $content['_opt_']['no_widgets'] = true;
				if(!empty($chunk['_opt_']['wgt_white'])) $content['_opt_']['wgt_white'] = array_merge($content['_opt_']['wgt_white'], $chunk['_opt_']['wgt_white']);
				if(!empty($chunk['_opt_']['wgt_black'])) $content['_opt_']['wgt_black'] = array_merge($content['_opt_']['wgt_black'], $chunk['_opt_']['wgt_black']);
			}#Анализирует опции модуля переданные ядру


			#----------------------------------------
			#Объединение элементов массивов
			#----------------------------------------

			#headers
			if(!empty($chunk['headers'])&&is_array($chunk['headers'])){
				$content['headers'] = array_merge($content['headers'], $chunk['headers']);
			}

			#template
			if(!empty($chunk['template'])&&is_string($chunk['template'])){
				$content['template'] = $chunk['template'];
			}

			#tmplfile
			if(!empty($chunk['tmplfile'])&&is_string($chunk['tmplfile'])){
				$content['tmplfile'] = $chunk['tmplfile'];
			}

			#html
			if(!empty($chunk['html'])&&is_array($chunk['html'])){
				$content['html'] = array_merge_recursive($content['html'], $chunk['html']);
			}

			#require
			if(!empty($chunk['require'])&&is_array($chunk['require'])){
				$content['require'] = array_merge($content['require'], $chunk['require']);
			}

			#layout
			if(!empty($chunk['layout'])&&is_string($chunk['layout'])&&empty($content['layout'])){
				$content['layout'] = $chunk['layout'];
			}

			#download
			if(!empty($chunk['download'])&&is_array($chunk['download'])){
				$content['download'] = array_merge($content['download'], $chunk['download']);
			}

			#actions
			if(!empty($chunk['actions'])&&is_array($chunk['actions'])){
				$content['actions'] = array_merge($content['actions'], $chunk['actions']);
			}

			if(!empty($chunk['noCache'])) $content['noCache'] = $chunk['noCache'];
			if(!empty($chunk['location'])) $content['location'] = $chunk['location'];
			if(!empty($chunk['title'])) $content['title'] = $chunk['title'];
			if(!empty($chunk['status'])&&$chunk['status']!='ok') $content['status'] = $chunk['status'];
			if(!empty($chunk['httpcode'])&&$chunk['httpcode']!=200) $content['httpcode'] = $chunk['httpcode'];

			#messages
			if(!empty($chunk['messages'])&&is_array($chunk['messages'])){
				$content['messages'] = array_merge_recursive($content['messages'], $chunk['messages']);
			}

			#data
			if(!empty($chunk['data'])){

				#Если в data контенте нет данных
				if(is_null($content['data'])){
					$content['data'] = $chunk['data'];
				}
				#Если в data контента - массив
				else
				if(is_array($content['data'])){
					if(is_array($chunk['data'])){
						$content['data'] = array_merge_recursive($content['data'], $chunk['data']);
					}else{
						array_push($content['data'], $chunk['data']);
					}
				}
				#Если в data контента - текст
				else{
					if(is_array($chunk['data'])){
						$content['data'] = array($content['data']);
						array_push($content['data'], $chunk['data']);
					}else{
						$content['data'] .= $chunk['data'];
					}
				}

			}#data

		}#Сливаем куски результатов от функций в единый массив $content


		#Если это первый запрос ($iteration=0) и чанки не найдены - 404
		if($iteration == 0 && $found == 0){
			$iteration = 1;
			$chunks_not_found = true;
			if($event_name == EVENT_PAGE_GET_CHUNKS || $event_name == EVENT_PAGE_RAW_CHUNKS){
				Request::_setRequestPath('core/404');
				goto fstart;
			}else
			if($event_name == EVENT_PAGE_UNAUTH_CHUNKS){
				Request::_setRequestPath('core/login');
				goto fstart;
			}
		}

		return !$chunks_not_found;
	}#end function



	/*
	 * Конструктор страницы
	 * 
	 * $options - опции построения и отображения страницы, представляет собой массив опций
	 * array(
	 * 		'return'	=> false,	#признак, указывающий что недо вернуть собранный контент (TRUE), а не выводить его сразу клиенту
	 * 		'template'	=> true,	#Признак, указывающий что надо использовать темплейт (работает кроме AJAX запросов)
	 * 		'headers'	=> true,	#Признак, указывающий что клиенту надо отправить headers
	 * );
	 */
	public function build($options=array()){

		$options = array_merge(
			array(
				'return' => false,
				'template' => true,
				'headers' => true
			),
			$options
		);

		#Результирующий массив контента
		$this->content = Chunk::$default;

		#Если это стандартный или AJAX запрос
		$is_raw  = Request::_getRequestInfo('raw',false);
		$is_ajax = Request::_getRequestInfo('ajax',false);
		$js_tmpl = Request::_getRequestInfo('tmpl',0);
		$js_tmpl_id = Request::_getRequestInfo('tmpl_id',null);
		$userauth = Request::_getRequestInfo('userauth',false);


		#Определение статуса аутентификации клиента
		#Если клиент не аутентифицирован
		if(!$userauth){

			#При RAW запросе - передаем запрос на обработку модулям,
			#Контроллеры модулей должны самостоятельно определить свое поведение при 
			#отсутствии у клиента аутентификации
			if($is_raw){
				$this->buildContent($this->content, EVENT_PAGE_RAW_CHUNKS, 0);
			}
			#При AJAX запросе - возвращаем в массиве требование аутентифицироваться
			else
			if($is_ajax){
				//$this->content['status'] = 'login';
				$this->buildContent($this->content, EVENT_PAGE_UNAUTH_CHUNKS, 0);
			}
			#При стандартном запросе - вызываем событие EVENT_PAGE_UNAUTH_CHUNKS - сбор чанков при отсутствующей аутентификации,
			#Здесь целенаправленно не сделан переход на страницу Login, т.к. ядро не может гарантировать, 
			#что запрошенную страницу действительно нельзя отображать не аутентифицированным пользователям
			#Контроллеры модулей должны самостоятельно определить свое поведение при 
			#отсутствии у клиента аутентификации
			else{
				$this->buildContent($this->content, EVENT_PAGE_UNAUTH_CHUNKS, 0);
			}

		}
		#Клиент успешно аутентифицирован
		else{
			#Событие - построение содержимого вывода разными модулями
			if($is_raw == true){
				$this->buildContent($this->content, EVENT_PAGE_RAW_CHUNKS, 0);
			}else{
				$this->buildContent($this->content, EVENT_PAGE_GET_CHUNKS, 0);

				#Если для страницы не запрещены все виджеты - запрашиваем их
				if(!$this->content['_opt_']['no_widgets']){
					$this->buildContent($this->content, EVENT_PAGE_GET_WIDGETS, 0, $this->content['_opt_']);
				}

			}

		}

		//print_r($this->content);exit;

		#Добавление Headers
		if(!empty($this->content['headers'])&&is_array($this->content['headers'])){
			$this->headers = $this->content['headers'];
			foreach($this->content['headers'] as $k=>$v) Response::_add($k,$v);
		}else{
			$this->headers = array();
		}

		if($this->content['httpcode'] != 200){
			Response::_status($this->content['httpcode']);
		}

		#Отправка Headers
		if($options['headers'] == true) Response::_sendHeaders();


		$this->html = $this->content['html'];
		$this->js = $this->content;
		unset($this->js['headers']);
		//if($is_ajax != true) unset($this->js['html']);
		unset($this->js['template']);
		unset($this->js['tmplfile']);
		unset($this->js['_opt_']);
		unset($this->js['httpcode']);


		$tmpl = null;
		$tmpl_complete = false;

		#Если задан HTML контент, используем его
		if(!empty($this->content['template'])){
			//$tmpl = $this->parseTemplate($this->content['template'], $this->html);
			$tmpl = $this->content['template'];
			$tmpl_complete = true;
		}
		#Если задан файл HTML контента, используем его
		else
		if(!empty($this->content['tmplfile'])){
			if(($tmpl = $this->templateFromInclude($this->content['tmplfile'])) !== false){
				//$tmpl = $this->parseTemplate($tmpl, $this->html);
				$tmpl_complete = true;
			}
		}


		#Если запрос - RAW запрос,
		#т.е. необходимо сформировать и вернуть чистый HTML 
		if($is_raw == true){

			#Во всех остальных случаях используем $this->buildRaw()
			if(!$tmpl_complete){
				$raw = $this->parseTemplate($this->buildRaw($this->html), $this->html);
			}

			if($options['return'] == true) return $raw;

			echo $raw;
			return true;
		}#Если запрос - RAW запрос


		#Если запрос в AJAX, то темплейт не используется, преобразуем результирующий массив в JSON и отправляем клиенту
		if($is_ajax == true || $options['template'] == false){

			$this->js['template'] = null;

			#Если задано, что темплейт необходимо вернуть в JSON ответе,
			#При этом если темплейт не задан чанками, то вернуть корневой темплейт
			if($js_tmpl == 2 && !$tmpl_complete && ($tmpl = $this->templateFromInclude(Config::getOption('Core/main','root_template'))) !== false) $tmpl_complete = true;

			#Если темплейт найден
			if($tmpl_complete){

				$this->js['tmpl_id'] = md5($tmpl);

				if($js_tmpl == 0){
					#Если темплейт явно не запрошен, при этом передан хеш текущего темплейта страницы, 
					#и хеши текущей и запрошенной страницы не совпадают - возвращаем темплейт
					if(!empty($js_tmpl_id) && $js_tmpl_id != $this->js['tmpl_id']) $this->js['template'] = $tmpl;
				}
				else{
					$this->js['template'] = $tmpl;
				}

			}#Если темплейт найден

			#Если результат не надо выводить на экран, а требуется только вернуть
			if($options['return'] == true) return json_encode($this->js);
			else echo json_encode($this->js);
		}
		else if($options['template'] == true){

			#Во всех остальных случаях используем корневой темплейт
			$this->js['template'] = (!$tmpl_complete) ? $this->templateFromInclude(Config::getOption('Core/main','root_template')) : $tmpl;
			$this->js['tmpl_id'] = md5($this->js['template']);

			#грузим "скелет"
			$tmpl = $this->templateFromInclude(Config::getOption('Core/main','html_skeleton'));


			if($options['return'] == true) return $tmpl;

			echo $tmpl;

		}#Стандартный запрос или AJAX запрос


		return true;
	}#end function



	/*
	 * Получает темплейт через include

	 */
	private function templateFromInclude($file=null){

		if(empty($file)) return false;
		if(strpos($file,'../')!==false) return false;
		$file = DIR_MODULES.'/'.ltrim($file, " .\r\n\t\\/");
		if(!file_exists($file)||!is_readable($file))return false;
		ob_start();
			include($file);
			$tmpl = ob_get_contents();
		ob_end_clean();

		return $tmpl;
	}#end function



	/*
	 * Конструктор RAW страницы
	 * 
	 * $content - представляет собой многомерный ассоциированный массив, описывающий контент RAW страницы
	 * имеет следующую структуру
	 * array(
	 * 		#HTTP заголовки, отправляемые клиенту
	 * 		'headers' => array(
	 * 			'Content-type' => 'text/html'
	 * 		),
	 * 		#HTML контент, содержимое тега <html></html>
	 * 		'html'=>array(
	 * 			#Используется, для задания произвольного HTML тега
	 * 			'_html' => '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">',
	 * 			#Содержимое тега <head></head>
	 * 			'head' => array(
	 * 				'<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
	 * 				'<title>Compas</title>'
	 * 			),
	 * 			#Содержимое тега <body></body>
	 * 			'body' => array(
	 * 				#Используется, для задания произвольного BODY тега
	 * 				'_body' => '<body class="test">',
	 * 				'<div>....</div>',
	 * 				'<div>....</div>'
	 * 			)
	 * 		)
	 * );
	 * 
	 */
	public function buildRaw($html=null){

		if(empty($html)||!is_array($html)) $html = $this->html;
		if(empty($html)||!is_array($html)) return '';

		$result = (!empty($html['_html']) ? $html['_html'] :'<html>');

		$result.= '<head>';
		if(!empty($html['head'])&&is_array($html['head'])){
			foreach($html['head'] as $k=>$v) $result .= $v;
		}
		$result.= '</head>';

		if(isset($html['body']['_body'])){
			$result.= (!empty($html['body']['_body']) ? $html['body']['_body'] :'<body>');
			unset($html['body']['_body']);
		}else{
			$result.='<body id="content_body">';
		}

		if(!empty($html['body'])&&is_array($html['body'])){
			foreach($html['body'] as $k=>$v) $result .= $v;
		}

		$result.= '</body></html>';
		return $result;
	}#end function












	/*
	 * ===============================================================================================
	 * TEMPLATE PARSER
	 * ===============================================================================================
	 */



	/*
	 * Построение HTML контента для контейнера по ключу
	 */
	public function htmlContainer($key='', $default='', $html=null){

		if(empty($html)||!is_array($html)) $html = $this->content['html'];

		if(empty($key)||(empty($html[$key])&&empty($html['html'][$key])))return $default;
		return implode('', (!empty($html[$key]) ? $html[$key] : $html['html'][$key]));

	}#end function



	/*
	 * Парсинг HTML темплейта и замена макроподстановок на соответствующие значения
	 */
	public function parseTemplate($tmpl='', $html=null){

		$count = 0;
		$level = 0;
		$this->parser_html = (empty($html)||!is_array($html)) ? $html = $this->content['html'] : $html;

		#Запуск цикла замены макроподстановок их значениями
		#Будем заменять до последней макроподстановки
		#В цикле сделано потому как вложеннойсть макроподстановок неизвестна
		#Но не более 10 раз
		do{
			$tmpl = preg_replace_callback('/{\%(\S+)\%}/s', array($this,'parseTemplateMacro'), $tmpl, -1, $count);
			$level++;
		}while($count>0&&$level<10);

		#Результат
		return $tmpl;
	}


	/*
	 * Разбор темплейта и замена макроподстановок
	 */
	private function parseTemplateMacro($matches){

		//if($matches[1]!=$matches[3]) return ''; #Ошибка обработки темплейта: неверный синтаксис
		//$prefix = $matches[1]; #Префикс, определяющий тип подстановки

		#Вычленение имени переменной/функции/файла
		$key = trim($matches[1]);

		$result='';

		if(!empty($this->parser_html[$key])){
			if(is_array($this->parser_html[$key])){
				foreach($this->parser_html[$key] as $v) $result.=$v;
			}else{
				$result = $this->parser_html[$key];
			}
		}

		return $result;
	}#end function





}#end class


?>