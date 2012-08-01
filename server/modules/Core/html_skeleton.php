<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы / Приложение
Описание: Корневой HTML темплейт
Версия	: 1.0.0/ALPHA
Дата	: 2012-06-04
Автор	: Станислав В. Третьяков, Илья Гилев
--------------------------------

Доступные переменные:
$this - объект класса Page
$this->content - результирующий массив вывода на страницу

Структура массива $content:
array(

	#Массив, где каждый элемент представляет собой пару ключ и массив HTML кода 'html\body\h1id' => array('<div>...</div>','<div>...</div>')
	#Ключи используются для вставки HTML контента в соответствующие контейнеры
	#имя ключа никак не связано с HTML разметкой или DOM структурой документа и может быть задано произвольно,
	#однако, для удобства, в том числе отладки, рекомендуется называть ключи по иерархии HTML разметки, например html\body\h1id
	'html' => array(
		'html\body\h1id' => array(
			'<div>...</div>',
			'<div>...</div>'
		)
	),
	'actions'=array(
		'xxx'=>array(
			'html' => 'ghfghgjhjkhkjkk'
		)
	)


);
 

=================================================================================================*/


//~ $loadObj = array(
//~ 
	//~ 'prefix'			=> '/client',		// строка, вставляемая перед именем
	//~ 'names'				=> array(),			// имя или имена JS или CSS-файлов
	//~ 'suffix'			=> '',				// строка, вставляемая после имени. %seed% заменяется на случайное число
	//~ 'browser'			=> '',				// проверка на соответствие браузера. Формат: chrome|firefox|ie|opera|safari lt|lte|gt|gte [version], ..., ...
	//~ 'os'				=> '',				// проверка на соответствие ОС. Формат: win|mac|linux|ios|android|webos, ..., ...
	//~ 'requireCookie'		=> true,			// проверять на доступность cookie в браузере
	//~ 'addOptions'		=> array(),			// добавить данные в объект __loaderInfo.options
	//~ 'setGlobalOptions'	=> array(),			// добавить объект __loaderInfo.options в window.options || window[setGlobalOptions]
	//~ 'initial'			=> $this->js		// Response
//~ 
//~ );

$loadObj = array(
	array(
		'requireCookie' => true,
		'addOptions' => array(
			'pageURL' => 'http'.(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 's' : '').'//'.
				$_SERVER['HTTP_HOST'].(
					isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']
						? $_SERVER['REQUEST_URI']
						: '/'
				),
			'disableNav' => false,
			
			'cookieSkinID' => 'uiskin',
			'uiSkinName' => 'cookie({cookieSkinID}) or default',
			
			'jsPath' => '/client/js/',
			'jsLibPath' => '{jsPath}lib/',
			'jsUIPath' => '{jsPath}ui/',
			'jsUILayoutPath' => '{jsUIPath}{uiSkinName}/',
			
			'cssPath' => '/client/css/{uiSkinName}/',
			'fontsPath' => '{cssPath}fonts/',
			
			//'noLayout' => true,
			
			'initial' => $this->js
		)
	),
	
	array(
		'names' => '{fontsPath}open_sans-woff.css',
		'browser' => 'chrome, firefox, opera'
	),
	
	array(
		'names' => '{fontsPath}open_sans-ttf.css',
		'browser' => 'safari'
	),
	
	array(
		'names' => '{fontsPath}open_sans-woff-eot.css',
		'browser' => 'ie gte 9'
	),
	
	array(
		'names' => '{fontsPath}open_sans-woff-eot_ie_lte_8.css',
		'browser' => 'ie 7, ie 8'
	),
	
	array(
		'names' => array(
			'{cssPath}common.css',
			'{cssPath}ui-common.css',
			'{cssPath}ui-dialog.css',
			'{cssPath}ui-window.css',
			'{cssPath}ui-form.css'
		),
		'media' => 'screen'
	),
	
	array(
		'names' => array(
			'{jsLibPath}mootools-core-1.4.5.js',
			'{jsLibPath}mootools-more-small-1.4.0.1.js',
			'{jsLibPath}mootools-transform.js',
			'{jsLibPath}utils.js',
			
			
			'{jsLibPath}dump.js',//TEMP!!!
			
			
			'{jsLibPath}require.js',
			'{jsLibPath}nav.js',
			
			'{jsUIPath}ui-dialog.js',
			'{jsUIPath}ui-window.js',
			'{jsUIPath}ui-form.js',
			'{jsUIPath}ui-common.js',
			'{jsUIPath}ui-layout.js'
		)
	)
);

?><!DOCTYPE html>
<html>
	<head>
		<title><?=$this->htmlContainer('title',Config::getOption('Core/main','project_name',''));?></title>
		<script id='js-loader' src='/client/js/loader.js' type='text/javascript'><?= json_encode($loadObj) ?></script>
	</head>
	<body>
		<noscript>
			<div id='js-not-enabled'>
				<link href='/client/css/default/common.css' rel='stylesheet' type='text/css' media='screen' />
				<link href='/client/css/default/no-js.css' rel='stylesheet' type='text/css' media='screen' />
				<strong>Поддержка Javascript должна&nbsp;быть&nbsp;включена.</strong>
				<a href='http://yandex.ru/yandsearch?text=Как+вкоючить+Javascript' target='_blank'>Как включить Javascript?</a>
			</div>
		</noscript>
	</body>
</html>