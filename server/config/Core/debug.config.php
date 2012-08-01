<?php
/*
======================================================================================
	Файл: debug.config.php
	Описание: Настройки обрабочика ошибок PHP
	Разработано (с) 2012 svtretyakov
======================================================================================
*/

/*--------------------------------------------------------------------------------------
Настройки Access Control List
--------------------------------------------------------------------------------------*/
return array(

	'display_errors' => true,	#Признак указывающий на необходимость отображения ошибок PHP на экране

	#Типы ошибок PHP, их описание и реакция
	#Типы ошибок задаются в формате 'e'.[Номер ошибки], пример: e1024
	'error_types' => array(
		#0 - неопределенная ошибка
		'e'.E_UNDEFINED => array(
			'type'		=> 'E_UNDEFINED',			#Текстовое представление типа ошибки
			'name'		=> 'Неизвестная ошибка',	#Описание ошибки
			'desc'		=> 'Неизвестная ошибка',	#Описание ошибки
			'display'	=> true,					#Вывести сообщение на экран
			'trace'		=> true, 					#Добавить сведения о трассировке
			'email'		=> false					#Массив email адресов для отправки сообщения об ошибке
		),
		#1 - Фатальная ошибка времени исполнения
		'e'.E_ERROR 	=> array(
			'type'		=> 'E_ERROR',
			'name'		=> 'Ошибка',
			'desc'		=> 'Фатальные ошибки времени выполнения',
			'display'	=> true,
			'trace'		=> true,
			'email'		=> array(
								CORE_EMAIL_ADMIN
			)
		),
		#2 - Предупреждение времени исполнения
		'e'.E_WARNING	=> array(
			'type'		=> 'E_WARNING',
			'name'		=> 'Предупреждение',
			'desc'		=> 'Предупреждения времени выполнения (нефатальные ошибки)',
			'display'	=> true,
			'trace'		=> true,
			'email'		=> false
		),
		#4 - Ошибки на этапе парсинга
		'e'.E_PARSE		=> array(
			'type'		=> 'E_PARSE',
			'name'		=> 'Ошибка парсинга',
			'desc'		=> 'Ошибки на этапе парсинга скрипта',
			'display'	=> true,
			'trace'		=> false,
			'email'		=> false
		),
		#8 - Уведомления времени выполнения.
		'e'.E_NOTICE	=> array(
			'type'		=> 'E_NOTICE',
			'name'		=> 'Уведомление',
			'desc'		=> 'Уведомления времени выполнения',
			'display'	=> true,
			'trace'		=> true,
			'email'		=> false
		),
		#16 - Фатальные ошибки, которые происходят во время запуска РНР.
		'e'.E_CORE_ERROR=> array(
			'type'		=> 'E_CORE_ERROR',
			'name'		=> 'Ошибка ядра',
			'desc'		=> 'Фатальные ошибки ядра PHP',
			'display'	=> true,
			'trace'		=> false,
			'email'		=> array(
							CORE_EMAIL_ADMIN
			)
		),
		#32 - Предупреждения (нефатальные ошибки), которые происходят во время начального запуска РНР
		'e'.E_CORE_WARNING => array(
			'type'=>'E_CORE_WARNING',
			'name'=>'Предупреждение ядра',
			'desc'=>'Предупреждения при инициализации ядра PHP',
			'display'	=> true,
			'trace'=>true,
			'email'=>array(
				CORE_EMAIL_ADMIN
			)
		),
		#64 - Фатальные ошибки на этапе компиляции, генерируются скриптовым движком Zend.
		'e'.E_COMPILE_ERROR => array(
			'type'=>'E_COMPILE_ERROR',
			'name'=>'Ошибка компиляции',
			'desc'=>'Фатальные ошибки компиляции',
			'display'	=> true,
			'trace'=>false,
			'email'=>array(
				CORE_EMAIL_ADMIN
			)
		),
		#128 - Предупреждения на этапе компиляции (нефатальные ошибки), генерируются скриптовым движком Zend.
		'e'.E_COMPILE_WARNING => array(
			'type'=>'E_COMPILE_WARNING',
			'name'=>'Предупреждение компиляции',
			'desc'=>'Предупреждения на этапе компиляции',
			'display'	=> true,
			'trace'=>false,
			'email'=>array(
				CORE_EMAIL_ADMIN
			)
		),
		#256 - Сообщения об ошибках сгенерированные пользователем.
		'e'.E_USER_ERROR => array(
			'type'=>'E_USER_ERROR',
			'name'=>'Ошибка пользователя',
			'desc'=>'Ошибки сгенерированные пользователем',
			'display'	=> true,
			'trace'=>true,
			'email'=>array(
				CORE_EMAIL_ADMIN
			)
		),
		#512 - Предупреждения сгенерированные пользователем.
		'e'.E_USER_WARNING => array(
			'type'=>'E_USER_WARNING',
			'name'=>'Предупреждение пользователя',
			'desc'=>'Предупреждения сгенерированные пользователем',
			'display'	=> true,
			'trace'=>true,
			'email'=>false
		),
		#1024 - Уведомления сгенерированные пользователем
		'e'.E_USER_NOTICE => array(
			'type'=>'E_USER_NOTICE',
			'name'=>'Уведомление пользователя',
			'desc'=>'Уведомления сгенерированные пользователем',
			'display'	=> true,
			'trace'=>false,
			'email'=>false
		),
		#2048 - Уведомления PHP, предлагающие изменения в коде, которые обеспечат лучшее взаимодействие и совместимость кода.
		'e'.E_STRICT => array(
			'type'=>'E_STRICT',
			'name'=>'Уведомление совместимости',
			'desc'=>'Уведомления PHP для обеспечения лучшего взаимодействия и совместимости кода',
			'display'	=> true,
			'trace'=>false,
			'email'=>false
		),
		#4096 - Фатальные ошибки с возможностью обработки
		'e'.E_RECOVERABLE_ERROR => array(
			'type'=>'E_RECOVERABLE_ERROR',
			'name'=>'Ошибка с обработкой',
			'desc'=>'Фатальные ошибки с возможностью обработки',
			'display'	=> true,
			'trace'=>true,
			'email'=>array(
				CORE_EMAIL_ADMIN
			)
		),
		#8192 - Уведомления времени выполнения об использовании устаревших конструкций
		'e'.E_DEPRECATED => array(
			'type'=>'E_DEPRECATED',
			'name'=>'Устаревшая конструкция',
			'desc'=>'Уведомления времени выполнения об использовании устаревших конструкций',
			'display'	=> true,
			'trace'=>false,
			'email'=>false
		),
		#16384 - Уведомления времени выполнения об использовании устаревших конструкций, сгенерированные пользователем.
		'e'.E_USER_DEPRECATED => array(
			'type'=>'E_USER_DEPRECATED',
			'name'=>'Устарело пользователя',
			'desc'=>'Устаревшая конструкция ползователя',
			'display'	=> true,
			'trace'=>false,
			'email'=>false
		)
	),



	#Тип данных: 
	#vars - переменные, 
	#defines - константы, декларируются через define(), все переменные массива должны быть скалярными
	'__type__' => 'vars'

);#end

?>