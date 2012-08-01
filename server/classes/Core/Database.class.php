<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Класс работы с базами данных 
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Авторы	: Станислав В. Третьяков, Илья Гилёв
--------------------------------
==================================================================================================*/



class Database{

	use Core_Trait_SingletonArray, Core_Trait_BaseError;

	/*==============================================================================================
	Переменные класса
	==============================================================================================*/

	#Свойства подключения
	protected $dsn	=	null; 		#DSN подключения к СУБД
	protected $dbh	=	null; 		#Экземпляр класса PDO

	#Ссылка на экземпляр класса Log
	protected $log	=	null;		#Экземпляр класса Log


	#Настройки по-умолчанию для экземпляра класса
	protected $options = array(
		'type'			=> 'mysql',				#Тип СУБД
		'host'			=> 'localhost',			#Хост или IP
		'port'			=> null,				#Номер порта сервера СУБД (если NULL, то определяется номер порта по умолчанию для указанного типа сервера)
		'username'		=> '',					#Логин
		'password'		=> '',					#Пароль
		'database'		=> '',					#Имя базы
		'charset'		=> 'cp1251',			#Кодировка
		'connect'		=> true,				#Подключаться ли при инициализации
		'error_level'	=> 4,					#Уровень обработки ошибок: 
												#0 - Никак не реагировать на ошибки
												#1 - Записывать в LOG файл только системные ошибки (код от 0 до 99).
												#2 - Записывать в LOG файл все ошибки и предупреждения
												#3 - Записывать в LOG файл все + выдавать сообщения о системных ошибках
												#4 - Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях
												#5 - Записывать в LOG файл все + выдавать сообщения о всех ошибках и предупреждениях, при системных ошибках - завершать работу
		'log_select'		=> false,			#Признак логирования запросов SELECT - 1
		'log_insert'		=> true,			#Признак логирования запросов INSERT - 2
		'log_update'		=> true,			#Признак логирования запросов UPDATE - 3
		'log_delete'		=> true,			#Признак логирования запросов DELETE - 4
		'log_transact'		=> true,			#Признак логирования транзакций - 5
		'log_other'			=> false			#Признак логирования всех прочих запросов (SHOW, TRUNCATE, DROP, COMMIT, ROLLBACK, START TRANSACTION и т.д.) - 6
	);



	#Внутренние свойства
	protected $connected				= false;		#Признак подключения
	protected $correct_init				= false;		#Признак корректной инициализации
	public $type						= null;			#Тип СУБД

	public $transact_queries			= array();		#Пул запросов, выполняемых в рамках транзакции
	public $last_query					= null;			#Последний выполенный SQL запрос

	#свойства SQL
	public		$template				= '';			#Темплейт SQL запроса
	protected	$binds					= array();		#Параметры SQL запроса
	protected	$bind_type				= DB_NONE;		#Тип передаваемых параметров: DB_NONE - не определено, DB_ASSOC - ассоциированный массив, DB_NUM - линейный индексный массив

	public		$sql					= '';			#SQL запрос
	public		$res					= null;			#Результат (Объект PDOStatement)
	public		$fetch					= array();		#Обработанный результат
	public		$records				= null;			#Массив записей в ответе
	public		$row					= null;			#Текущая Запись



	#Массив описаний ошибок:
	#Каждая запись состоит из массива, содержащего
	#идентификатор генерируемого события и описание ошибки
	#события с идентификатором 0, NULL, FALSE, '' - не обрабатываются
	#Идентификаторы событий могут быть заданы в виде чисел (12,34,0xCC9087) или строк ('test_event','my_event')
	static protected $errors = array(
		#Системные ошибки, от 1 до 99
		0	=> array(0, 'Нет ошибки'),
		1	=> array(EVENT_PHP_ERROR, 'Вызов недопустимого метода или функции класса'),
		#Ошибки класса, от 1 до 99
		2	=> array(EVENT_DATABASE_ERROR, 'Не задан идентификатор соединения'),
		3	=> array(EVENT_DATABASE_ERROR, 'Не задан хост сервера / логин / имя базы данных'),
		5	=> array(EVENT_DATABASE_ERROR, 'Не удалось сгенерировать DSN строку'),
		6	=> array(EVENT_DATABASE_ERROR, 'Не удалось установить соединение с сервером баз данных'),
		8	=> array(EVENT_DATABASE_ERROR, 'Невозможно выполнить SQL запрос, поскольку не задана SQL инструкция'),
		11	=> array(EVENT_DATABASE_ERROR, 'Не удалось начать транзакцию, потому что соединение с БД не установлено'),
		12	=> array(EVENT_DATABASE_ERROR, 'Не удалось завершить транзакцию, потому что соединение с БД не было установлено'),
		13	=> array(EVENT_DATABASE_ERROR, 'Нельзя начать новую транзакцию, пока предыдущая не будет завершена'),
		14	=> array(EVENT_DATABASE_ERROR, 'Нельзя завершить транзакцию, поскольку транзакция не была открыта'),
		15	=> array(EVENT_DATABASE_ERROR, 'Ошибка PDO при старте новой транзакции'),
		16	=> array(EVENT_DATABASE_ERROR, 'Ошибка PDO при завершении транзакции'),
		17	=> array(EVENT_DATABASE_ERROR, 'Высоз COMMIT при отсутствии транзакции'),
		18	=> array(EVENT_DATABASE_ERROR, 'Высоз ROLLBACK при отсутствии транзакции'),
		20	=> array(EVENT_DATABASE_ERROR, 'Не удалось выполнить SQL-запрос, потому что соединение с БД не было установлено'),
		30	=> array(EVENT_DATABASE_ERROR, 'Не удалось сформировать запрос. Несоответствие шаблона prepare() с количеством вызовов bind()'),
		31	=> array(EVENT_DATABASE_ERROR, 'Не удалось сформировать запрос. Ошибка PDO'),
		32	=> array(EVENT_DATABASE_ERROR, 'Смешанный тип вызовов в bind()'),
		40	=> array(EVENT_DATABASE_ERROR, 'Запрос в функции result() вернул более одного результата')
	);

	#Информация о классе
	static protected $class_about = array(
		'module'	=> 'Core',
		'namespace'	=> __NAMESPACE__,
		'class'		=> __CLASS__,
		'file'		=> __FILE__,
		'log_file'	=> 'Core/Database'
	);







	/*==============================================================================================
	Инициализация
	==============================================================================================*/


	#--------------------------------------------------
	# Конструктор класса
	#--------------------------------------------------
	protected function init($connection='main', $options=null){

		#Установка опций
		if(is_array($options))
			$this->options = array_merge($this->options, $options);

		if(
			empty($this->options['host'])||
			empty($this->options['username'])||
			empty($this->options['database'])
		){
			return $this->doErrorEvent(3, __FUNCTION__, __LINE__, json_encode($this->options));
		}

		$this->log = LogFile::getInstance(
			'Database/'.$connection,
			array(
				#Путь к файлу журнала, пример: /var/www/test/server/logs/Database/localhost/dbname/2012-04-20.log
				'file' => DIR_LOGS.'/Database/'.$this->options['host'].'/'.$this->options['database'].'/'.date('Y-m-d',time()).'.log'
			)
		);


		#Обработчики событий - ошибки Database
		$this->error_level = $this->options['error_level'];

		$this->correct_init = true;
		$this->type			= $this->options['type'];

		#Соединение с базой данных
		if($this->options['connect']) $this->connect();

	}#end function



	#--------------------------------------------------
	# Деструктор класса
	#--------------------------------------------------
	public function __destruct(){
		$this->close();
	}#end function



	#--------------------------------------------------
	# В контексте объекта при вызове недоступных методов
	#--------------------------------------------------
	public function __call($name, $arguments){

		switch ($name) {
			case 'transaction':
				return $this->transactionControl('beginTransaction');
			break;
			case 'commit':
				return $this->transactionControl('commit');
			break;
			case 'rollBack':
			case 'rollback':
				return $this->transactionControl('rollBack');
			break;
		}

		return $this->doErrorEvent(1, __FUNCTION__, __LINE__, json_encode(array('name'=>$name,'arguments'=>$arguments)));
	}#end function



	#--------------------------------------------------
	# Чтение данных из недоступных свойств
	#--------------------------------------------------
	public function __get($name){

		switch ($name) {
			#Экземпляр PDO
			case 'pdo':
			case 'dbh':
				return $this->dbh;
			break;
			#Признак установленного подключения
			case 'connected':
				return $this->connected;
			break;
			case 'correct_init':
				return $this->correct_init;
			break;
			#Признак начатой транзакции
			case 'in_transaction':
			case 'in_transact':
				return $this->inTransaction();
			break;
		}

		return $this->doErrorEvent(1, __FUNCTION__, __LINE__, $name);
	}#end function









	/*==============================================================================================
	Функции: обработка ошибок
	==============================================================================================*/


	#Функция, вызываемая в классе при возникновении ошибки (вызове функции doErrorEvent)
	#$event_name - передается идентификатор события
	#$data - информация об ошибке
	protected function doErrorAction($event_name, $data){
		
	}








	/*==============================================================================================
	Логирование
	==============================================================================================*/


	/*
	#--------------------------------------------------
	#Логирование выпоненного SQL запроса
	#--------------------------------------------------
	#
	# Принимает аргументы:
	# $log_transact - признак, указывающий о необходимости логировании транзакции (COMMIT)
	*/
	private function queryLog($log_transact = false){

		#Если выполнение запроса происходит в транзакции - выход
		if($this->inTransaction() == true) return;

		/*
		Реализовать механизм определения типа запроса:
		SELECT
		INSERT
		UPDATE
		DELETE
		начала и завершения транзакции

		'log_select'		=> false,			#Признак логирования запросов SELECT - 1
		'log_insert'		=> true,			#Признак логирования запросов INSERT - 2
		'log_update'		=> true,			#Признак логирования запросов UPDATE - 3
		'log_delete'		=> true,			#Признак логирования запросов DELETE - 4
		'log_transact'		=> true,			#Признак логирования транзакций - 5
		'log_other'			=> false			#Признак логирования всех прочих запросов (SHOW, TRUNCATE, DROP, COMMIT, ROLLBACK, START TRANSACTION и т.д.) - 6
		*/

		$need_log = false;

		#Определение типа запроса и необходимости его логирования
		if(!$log_transact){
			if($this->options['log_select'] == true && strncasecmp('SELECT',$this->last_query, 6) == 0) $need_log = true;
			else
			if($this->options['log_insert'] == true && strncasecmp('INSERT',$this->last_query, 6) == 0) $need_log = true;
			else
			if($this->options['log_update'] == true && strncasecmp('UPDATE',$this->last_query, 6) == 0) $need_log = true;
			else
			if($this->options['log_delete'] == true && strncasecmp('DELETE',$this->last_query, 6) == 0) $need_log = true;
			else
			if($this->options['log_other'] == true) $need_log = true;
		}else{
			$need_log = ($this->options['log_transact']);
		}

		#Логирование запроса
		if($need_log){
			$this->log->writeCustomLine(
				array(
				($log_transact == true ? 'TRANSACT' : 'SINGLE'),
				($log_transact == true ? $this->transact_queries : $this->last_query)
				)
			);
		}

		#Генерация события об успешной обработке SQL запроса
		Event::getInstance()->fireEvent(
			EVENT_DATABASE_QUERY, 
			array(
				'connection'	=> $this->connection,
				'database'		=> $this->options['database'],
				'transaction'	=> ($log_transact == true ? true : false),
				'query'			=> ($log_transact == true ? $this->transact_queries : $this->last_query)
			)
		);


	}#end function









	/*==============================================================================================
	Функции формирования SQL: Параметрические запросы
	==============================================================================================*/



	/*
	 * Задает шаблон для SQL-запроса (данные следует заменить на ? при привязке по порядку или на :имя\s при привязке по имени)
	 * Cовмещать два типа привязки нельзя.
	 * 
	 * $template - SQL шаблон
	 * $binds - массив параметров для подстановки в шаблон
	 */
	public function prepare($template='', $binds=null){

		$this->template	= $template;
		$this->binds = array();
		$this->bind_type = DB_NONE;
		if(is_array($binds)) 
			return $this->bind($binds);

		return $this;
	}#end function



	/*
	 * Возвращает поле, обрамленное в соответствующие выбранной СУБД кавычки 
	 * 
	 * $field - имя поля или выражение
	 * 
	 * Примеры:
	 * getQuotedField('field')				-> [field] <<< для MSSQL
	 * getQuotedField('table.field')		-> [table].[field] <<< для MSSQL
	 * getQuotedField('max(field)')			-> max(field) <<< для MSSQL
	 * getQuotedField('max(`field`)')		-> max([field]) <<< для MSSQL
	 * getQuotedField('field x')			-> [field] as [x] <<< для MSSQL
	 * getQuotedField('field as x')			-> [field] as [x] <<< для MSSQL
	 * getQuotedField('max(`field`) x')		-> max([field]) as [x] <<< для MSSQL
	 */
	public function getQuotedField($field=''){

		if(empty($field)) return $field;
		$field = trim($field,"\r\n\t ");

		#Преобразование нескольких пробелов в один
		$field = preg_replace('/[\t ]+/', ' ', $field);

		#Проверяем наличие "(" и выходим в случае обнаружения
		#Считаем, что поля переданные с символом "(" являются функциями, 
		#типа min(field), max(field), count(*)
		#При этом конвертируем содержимое max(`field`) в представление выбранной СУБД
		#max(`field`+`field2`) для MSSQL вернет max([field]+[field2])
		if( ($p1 = strpos($field, '(')) !== false){
			 $p2 = strrpos($field, ')');
			if($p2){
				$sb = substr($field,0,$p1);
				$ss = substr($field,$p1,$p2-$p1);
				$sa = substr($field,$p2,strlen($field)-$p2);
				$alias = '';
				$this->getQuotedFieldAlias($sa, $alias);
				return $sb.$this->convertTemplateQuotes($ss).$sa.$alias;
			}
		}


		#Проверка наличия алиаса в названии поля
		#Алиас может быть задан одним из следующих способов:
		#field as alias
		#field alias
		$alias = '';
		$this->getQuotedFieldAlias($field, $alias);

		return $this->getQuotedFieldEscape($field).$alias;
	}#end function


	/*
	 * Возвращает поле, обрамленное в соответствующие выбранной СУБД кавычки, внутренняя функция
	 */
	private function getQuotedFieldEscape($field=''){

		#Имя переданного поля имеет формат table.field
		if(strpbrk($field,'.')!==false){
			return implode('.', array_map(array($this, 'getQuotedField'),explode('.',$field)));
		}

		if($field == '*') return $field;

		$field = trim($field,'[]"`\'');
		#Замена обрамлений полей для разных типов СУБД
		switch($this->type){
			case 'pgsql': return DQ.$field.DQ;
			case 'mysql': return BQ.$field.BQ;
			case 'sqlsrv':
			case 'mssql': return '['.$field.']';
			default: return $field;
		}

	}#end function



	/*
	 * Проверка наличия в имени поля алиаса и приведение его к корректному для СУБД виду
	 */
	private function getQuotedFieldAlias(&$field, &$alias){

		#Проверка наличия алиаса в названии поля
		#Алиас может быть задан одним из следующих способов:
		#field as alias
		#field alias
		$alias = '';
		if(strpos($field, ' ') !== false){
			$alias = strstr($field, ' ');
			$field = substr($field, 0, - strlen($alias));
			$alias = preg_replace('/^AS /i', '', ltrim($alias));
			$alias = ' as '.$this->getQuotedFieldEscape($alias);
			return true;
		}

		return false;
	}#end function




	/*
	 * Квотирование для СУБД MySQL
	 */
	public static function mysqlEscape($str){
		$search=array("\\","\0","\n","\r","\x1a","'",'"');
		$replace=array("\\\\","\\0","\\n","\\r","\Z","\'",'\"');
		return str_replace($search,$replace,$str);
	}#end function



	/*
	 * Квотирование для СУБД MSSQL
	 */
	public static function mssqlEscape($str){
		$del = array(
			'/%0[0-8bcef]/',						// url encoded 00-08, 11, 12, 14, 15
			'/%1[0-9a-f]/',							// url encoded 16-31
			'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'	// 00-08, 11, 12, 14-31, 127
		);
		do{
			$str = preg_replace($del, '', $str, -1, $count);
		}while($count);
		$str = str_replace("'", "''", $str);
		return $str;
	}#end function



	/*
	 * Квотирование для СУБД PostgreSQL
	 */
	public static function pgsqlEscape($str){
		return pg_escape_string($str);
	}#end function



	/*
	 * Возвращает строку, экранированную в соответствии с выбранной СУБД 
	 */
	public function getQuotedValue($value=''){

		#Замена обрамлений полей для разных типов СУБД
		switch($this->type){
			case 'pgsql': return SQ.self::pgsqlEscape($value).SQ;
			case 'mysql': return SQ.self::mysqlEscape($value).SQ;
			case 'sqlsrv':
			case 'mssql': return SQ.self::mssqlEscape($value).SQ;
			default: return addslashes($value);
		}

	}#end function



	/*
	 * Обрабатывает пользовательский массив binds и возвращает результирующий массив,
	 * подходящий для обработки темплейта в parseTemplate
	 * 
	 * Пример:
	 * getBinds(array(
	 * 		null,
	 * 		'value1',
	 * 		array('value2', 'field1'),
	 * 		array(777, 'field2', BIND_NUM),
	 * 		'field3' => 'value3'
	 * ));
	 * Результат:
	 * Array(
	 * 		[0]			=> NULL
	 * 		[1]			=> 'value1'
	 * 		[field1]	=> 'value2'
	 * 		[field2]	=> 777
	 * 		[field3]	=> 'value3'
	 * )
	 */
	public function getBinds($binds=null){

		if(!is_array($binds)||empty($binds)) return array();

		$result = array();
		foreach ($binds as $key=>$bind){

			if(is_array($bind)){
				if(!isset($bind[0])) continue;
				$value = $bind[0];
				$name = empty($bind[1])||is_numeric($bind[1]) ? null : $bind[1];
				$type = empty($bind[2]) ? BIND_TEXT : $bind[2];
			}else{
				$value = $bind;
				$name = (!is_numeric($key)) ? $key : null;
				$type = BIND_TEXT;
			}

			switch($type){
				case BIND_NULL: $value = 'NULL'; break;
				case BIND_FIELD: $value = $this->getQuotedField($value); break;
				case BIND_NUM: $value = (is_numeric($value) ? $value : $this->getQuotedValue($value));
				case BIND_SQL:
				break;
				case BIND_TEXT:
				default:
					$value = (is_null($value)) ? 'NULL' : $this->getQuotedValue($value);
			}

			if(empty($name)){
				$result[] = $value;
			}else{
				$name = ltrim($name, ':');
				$result[$name] = $value;
			}

		}

		return $result;
	}#end function



	/*
	 * Заменяет следующий знак ? в шаблоне запроса на экранированную строку данных
	 * Может принимать массив (в т.ч. ассоциативный) в кач. значения
	 * Cовмещать два типа привязки нельзя.
	 * 
	 * $value - добавляемое значени или массив значений
	 * $name - ключ добавляемого значения
	 * $type - тип значения, может принимать следующие аргументы:
	 * 			BIND_NULL - null,
	 * 			BIND_TEXT - текст, автоматически обрамляется соответствующими кавычками и квотируется
	 * 			BIND_NUM - число (целое, с запятой), ничего не делается, остается в заданном виде
	 * 			BIND_FIELD - имя таблицы или поля, обрамляется соответствующими для СУБД кавычками
	 * 			BIND_SQL - sql инструкция, никак не обрабатывается, просто подставляется в указанное место, аля trait
	 */
	public function bind($value='', $name=null, $type=BIND_TEXT){

		if($this->bind_type == DB_ERROR) return false;

		if(is_array($value)){
			foreach ($value as $k=>$v){
				$this->bind($v, (!is_numeric($k) ? $k : null), $type);
			}
			return $this;
		}

		switch($type){
			case BIND_NULL: $value = 'NULL'; break;
			case BIND_FIELD: $value = $this->getQuotedField($value); break;
			case BIND_NUM: $value = (is_numeric($value) ? $value : $this->getQuotedValue($value));
			case BIND_SQL:
			break;
			case BIND_TEXT:
			default:
				$value = (is_null($value)) ? 'NULL' : $this->getQuotedValue($value);
		}


		if(empty($name)){
			$bind_type = DB_NUM;
			$this->binds[] = $value;
		}else{
			$bind_type = DB_ASSOC;
			$name = ltrim($name, ':');
			$this->binds[$name] = $value;
		}

		if($this->bind_type == DB_NONE){
			$this->bind_type = $bind_type;
		}else{
			if($this->bind_type != $bind_type){
				$this->bind_type = DB_ERROR;
				return $this->doErrorEvent(32, __FUNCTION__, __LINE__, json_encode(func_get_args()));
			}
		}

		return $this;
	}#end function

	public function bindNull($value='', $name=null){return $this->bind($value,$name,BIND_NULL);}
	public function bindText($value='', $name=null){return $this->bind($value,$name,BIND_TEXT);}
	public function bindNum($value='', $name=null){return $this->bind($value,$name,BIND_NUM);}
	public function bindField($value='', $name=null){return $this->bind($value,$name,BIND_FIELD);}
	public function bindSql($value='', $name=null){return $this->bind($value,$name,BIND_SQL);}



	/*
	 * Конвертирует экранируемые символы темплейта в формат выбранной СУБД
	 * Пример: 
	 * $template = select `all`,`xx`+m.`tt`,sum(`lexx`) from (`xxx`,`yyy`,`zzz`)
	 * Result: 
	 * select "all","xx"+m."tt",sum("lexx") from ("xxx","yyy","zzz") <<< для PostgreSQL
	 * select [all],[xx]+m.[tt],sum([lexx]) from ([xxx],[yyy],[zzz]) <<< для MSSQL
	 */
	public function convertTemplateQuotes($template='', $type=null){

		if(empty($template)) return '';
		$type = (empty($type)) ? $this->type : $type;

		#Замена обрамлений полей для разных типов СУБД
		switch($type){
			case 'pgsql':
				$template = strtr($template,'`[]','"""');
			break;
			case 'mysql':
				$template = strtr($template,'"[]','```');
			break;
			case 'mssql':
				$template = preg_replace(
					array(
						'/^[\"\`]/',
						'/[\"\`]$/',
						'/[\"\`]([ \t\!\@\#\$\%\^\&\*\(\)\=\+\:\;\,\?\>\<\.])/',
						'/([ \t\!\@\#\$\%\^\&\*\(\)\=\+\:\;\,\?\>\<\.])[\"\`]/'
					), 
					array(
						'[',
						']',
						']$1',
						'$1['
					), 
					$template
				);
			break;
		}

		return $template;
	}#end function



	/*
	 * Возвращает SQL-запрос с учетом переданных в массив bind параметров
	 * 
	 * $template - произвольный SQL темплейт, если не задан, то будет использован темплейт, заданный через prepare()
	 * $binds - массив binds, если не задан, то будут использованы подстановки, заданные через bind()
	 * $bind_type - тип массива $binds, переданного в функцию, используется, если в функцию передан массив $binds
	 * 
	 * Пример:
	 * parseTemplate(
	 * 		'select * from :table; where :f_xxx; > :xxx; and :f_yyy;=:yyy;',
	 * 		array(
	 * 			array('my_table','table',BIND_FIELD),
	 * 			array('xxx','f_xxx',BIND_FIELD),
	 * 			array('yyy','f_yyy',BIND_FIELD),
	 * 			array(777,'xxx',BIND_NUM),
	 * 			'yyy' => 'val2'
	 * 		),
	 * 		DB_ASSOC
	 * )
	 * Результат:
	 * select * from [my_table] where [xxx] > 777 and [yyy]='val2' <<< для MSSQL
	 * select * from "my_table" where "xxx" > 777 and "yyy"='val2' <<< для PostgreSQL
	 * select * from `my_table` where `xxx` > 777 and `yyy`='val2' <<< для MySQL
	 */
	public function parseTemplate($template='', $binds=null, $bind_type=DB_NUM){

		if(!is_array($binds)||empty($binds)){
			if($this->bind_type == DB_ERROR) return false;
			$binds = $this->binds;
			$bind_type = $this->bind_type;
		}else{
			$binds = $this->getBinds($binds);
		}

		$template = $this->convertTemplateQuotes((!empty($template) ? $template : $this->template));

		$sql = '';

		if($bind_type == DB_NUM){
			$aq = explode('?', $template);
			$aq_cnt = count($aq);
			if($aq_cnt != (count($binds)+1)){
				return $this->doErrorEvent(30, __FUNCTION__, __LINE__, json_encode(array('template'=>$template,'binds'=>$binds))); #Не совпадает шаблон prepare() и количество вызовов bind()
			}
			for($i=0; $i<$aq_cnt-1; $i++){
				$sql .= $aq[$i] . $binds[$i];
			}
			$sql .= $aq[$i];
		}else{
			$aq = explode(':', $template);
			$aq_cnt = count($aq);
			$sql = $aq[0];
			if($aq_cnt > 1){
				for($i=1; $i<$aq_cnt; $i++){
					$kv = explode(';',$aq[$i],2);
					if(count($kv) > 1){
						$key = $kv[0];
						$text = $kv[1];
					}else{
						$key = $kv[0];
						$text = '';
					}
					if(!isset($binds[$key])) return $this->doErrorEvent(30, __FUNCTION__, __LINE__, json_encode(array('template'=>$template,'binds'=>$binds,'needkey'=>$key))); #Не совпадает шаблон prepare() и количество вызовов bind()
					$sql .= $binds[$key] . $text;
				}
			}


		}

		return $sql;
	}#end function


















	/*==============================================================================================
	Функции формирования SQL: Обработка условий
	==============================================================================================*/


	/*
	 * Построение части SQL запроса на основании данных массива условий
	 * 
	 * $conditions - массив условий
	 * $separator - связка между условиями: 
	 * 		если часть SQL запроса будет как перечисление полей для UPDATE, используйте ","
	 * 		если часть SQL запроса будет после WHERE или ON, используйте для связки "AND" или "OR" в зависимости от запроса
	 * 
	 * Запись в $conditions:
	 * $conditions = array(
	 * 
	 * 		'testfield=25',			#Так задается SQL текст, который не будет вставлен в результирующую SQL строку без каких-либо изменений
	 * 
	 * 		'myfield'=>'test',		#Так задается конструкция [поле][=][значение], поле и значение квотируются, 
	 * 								#между ними применяется оператор равенства, результатом для MySQL будет: `myfield`='test'
	 * 
	 * 		'field2'=>array(1,2,3),	#Так задается конструкция [поле] IN ([значение1],[значение2],[значение3]), поле и значение квотируются,
	 * 								#между ними применяется оператор IN (входит в перечисление), результатом для MySQL будет: `field2` IN ('1','2','3')
	 * 
	 * 		array(							#Так задается произвольная конструкция вида [поле][=][значение], если значение value является массивом, 
	 * 			'field' => 'test',			#То обработка массива осуществялется в зависимости от значения в bridge (если не задано, по умолчанию ",")
	 * 			'value' => array(1,2,3),	#при bridge="," -> `test` NOT IN (1,2,3)
	 * 			'glue' => 'NOT IN',			#при bridge="OR"("AND") -> (`test` NOT IN (1) OR `test` NOT IN (2) OR `test` NOT IN (3))
	 * 			'bridge' => ',',			#за исключением, когда оператор задан как "BETWEEN", bridge в этом случае не используется
	 * 			'type' => BIND_NUM			#будет обработано только 2 элемента массива value -> `test` BETWEEN 1 AND 2
	 * 		),
	 * 		array('test',array(1,2,3),'NOT IN',',',BIND_NUM)	#Альтернативная запись вышеуказанного массива в неассоциированном виде, где элементы:
	 * 															#[0]-поле(*), [1]-значение(null), [2]-тип данных(BIND_TEXT), [3]-оператор(= или IN), [4]-связка(,)
	 * );
	 * 
	 * Примеры элементов в массиве $conditions и результат (для MySQL):
	 * "test != 'xxx'"													-> test != 'xxx'
	 * 'test' => 'xxx'													-> `test`='xxx'
	 * 'test' => null													-> `test`=NULL
	 * 'test' => array(1,2,3)											-> `test` IN ('1','2','3')
	 * array('test','xxx') 												-> `test`='xxx'
	 * array('test',123,null,'>=') 										-> `test`>='123'
	 * array('test',999,BIND_NUM,'!=','') 								-> `test`!=999
	 * array('test',array(1,2,3)) 										-> `test` IN ('1','2','3')
	 * array('field'=>'test','value'=>array(1,2,3),'type'=>BIND_NUM) 	-> `test` IN (1,2,3)
	 * array('test',array(1,2,3),BIND_NUM,'NOT IN','') 					-> `test` NOT IN (1,2,3)
	 * array('test',array(1,2,3),BIND_NUM,'!=','AND')					-> (`test` != 1 AND `test` != 2 AND `test` != 3)
	 * array('test',array(4,8,6),'','BETWEEN') 							-> `test` BETWEEN '4' AND '8' <<< '6' отсекается, используется только первые два элемента массива
	 * array('test',array(4,8),BIND_NUM,'BETWEEN','') 					-> `test` BETWEEN 4 AND 8
	 * array('test') 													-> `test` = NULL
	 * array('test',null) 												-> `test` = NULL
	 * array('test',null,'','!=') 										-> `test` !=NULL
	 * array('','SELECT field FROM table WHERE field>4',BIND_SQL)		-> (SELECT field FROM table WHERE field>4)
	 * 
	 * Примеры некорректных элементов:
	 * array('test',9,BIND_NUM,'BETWEEN','') 							-> `test` BETWEEN 9 <<< некорректный SQL!
	 * array('test',array()) 											-> `test`='' <<< внимание!
	 * array('test',array(),'','IN') 									-> `test` IN '' <<< внимание! некорректный SQL!
	 * array('test',9,'','IN') 											-> `test` IN '9' <<< некорректный SQL!
	 * array()															->  <<< некорректно, будет пропущено
	 * null,															->  <<< некорректно, будет пропущено
	 * 
	 * Пример вызова:
	 * $sql_conditions = $db->buildSqlConditions(array(
	 * array('test',array(1,2,3),'!=','AND',BIND_NUM)
	 * ),'AND');
	 */
	public function buildSqlConditions($conditions=null, $separator='AND'){

		if(empty($conditions)||!is_array($conditions)) return '';

		$result = array();

		#Просмотр conditions
		foreach($conditions as $k=>$v){

			$k_is_field = !is_numeric($k);
			$v_is_array = is_array($v);

			#Значение не задано массивом
			if(!$v_is_array){
				#'test' => 'xxx'
				if($k_is_field){
					$result[] = $this->getQuotedField($k).'='.(($v!=null) ? $this->getQuotedValue($v) : 'NULL');
				}
				#"test != 'xxx'"
				else{
					if($v!=null) $result[] = $v;
				}

				continue;
			}

			#'test' => array(1,2,3)
			if($v_is_array && $k_is_field){
				$result[] = $this->getQuotedField($k).' IN ('.implode(',',array_map(array($this,'getQuotedValue'),$v)).')';
				continue;
			}

			#$v - ассоциированный массив
			if(isset($v['field'])){
				$field	= $this->getQuotedField($v['field']);
				$value	= isset($v['value']) ? $v['value'] : null;
				$type	= isset($v['type']) ? $v['type'] : BIND_TEXT;
				$glue	= isset($v['glue']) ? strtoupper($v['glue']) : '=';
				$bridge	= !empty($v['bridge']) ? $v['bridge'] : ',';
			}
			#$v - линейный индексный массив
			else{
				#Если задан пустой массив - пропускаем
				if(!isset($v[0])) continue;
				$field	= $this->getQuotedField($v[0]);
				$value	= isset($v[1]) ? $v[1] : null;
				$type	= isset($v[2]) ? $v[2] : BIND_TEXT;
				$glue	= isset($v[3]) ? strtoupper($v[3]) : '=';
				$bridge	= !empty($v[4]) ? $v[4] : ',';
			}

			#Если нужно вернуть только имя поля
			if($type==BIND_FIELD){
				$result[] = $field;
				continue;
			}

			#Фикс для оператора, удаляем символы %
			#Могут присутствовать в операторах "%LIKE%", "LIKE%", "%LIKE", "NOT %LIKE%", "NOT LIKE%", "NOT %LIKE"
			$gluefix = str_replace('%','',$glue);

			#Если значение должно быть NULL
			if(is_null($value)||$type==BIND_NULL){
				$result[] = $field.' '.$gluefix.' NULL';
				continue;
			}

			#Если значение - SQL подзапрос
			if($type==BIND_SQL){
				$str = (is_array($value) ? $this->buildSqlConditions($value, ($bridge==',' ? 'AND' : $bridge)) : $value);
				$result[] = ($field==null?'':$field.' ').(empty($gluefix)?'':$gluefix).' ('.$str.')';
				continue;
			}

			#Если значение не задано
			if(empty($value)){
				switch($type){
					case BIND_NUM: $value='0'; break;
					case BIND_TEXT:
					default: $value='';
				}
			}

			#Значение задано массивом
			if(is_array($value)){

				if($gluefix == '=' && $bridge == ',') $gluefix='IN';

				#`test` BETWEEN 23 AND 334
				if($gluefix =='BETWEEN'){
					$v1 = ($type==BIND_NUM ? $value[0] : $this->getQuotedValue($value[0]));
					$v2 = (isset($value[1]) ? ($type==BIND_NUM ? $value[1] : $this->getQuotedValue($value[1])) : ($type==BIND_NUM ? '0' : '\'\''));
					$result[] = $field.' BETWEEN '.$v1.' AND '.$v2;
					continue;
				}

				if($bridge == ','){
					if($type!=BIND_NUM) $value = array_map(array($this,'getQuotedValue'),$value);
					$result[] = $field.' '.$gluefix.' ('.implode(',',$value).')';
				}else{
					$str = '';
					foreach($value as $item){
						#Обработка значения в зависимости от оператора, передаем именно $glue, а не $gluefix
						if($type!=BIND_NUM) $item = $this->buildSqlConditionsCheckGlue($glue, $item);
						$str .= (empty($str) ? '(' : ' '.$bridge.' ') . $field.' '.$gluefix.' '.$item;
					}
					$result[] = $str.')';
				}

				continue;
			}#Значение задано массивом

			#Значение задано как число
			if($type==BIND_NUM){
				$result[] = $field.' '.$gluefix.' '.$value;
				continue;
			}

			#Обработка значения в зависимости от оператора, передаем именно $glue, а не $gluefix
			$value = $this->buildSqlConditionsCheckGlue($glue, $value);

			#Обычное значение
			$result[] = $field.' '.$gluefix.' '.$value;


		}#Просмотр conditions

		#Результат
		return (count($result) > 0 ? implode(' '.$separator.' ',$result) : '');
	}#end function



	/*
	 * Вспомогательная функция, проверяет тип оператора и соответствующим образом преобразует значение
	 */
	private function buildSqlConditionsCheckGlue($glue, $value){

		#Обработка операторов "%LIKE%", "LIKE%", "%LIKE", "NOT %LIKE%", "NOT LIKE%", "NOT %LIKE"
		switch($glue){
			case 'LIKE%':
			case 'NOT LIKE%':
				return $this->getQuotedValue(rtrim($value,'%').'%'); 

			case '%LIKE':
			case 'NOT %LIKE':
				return $this->getQuotedValue('%'.ltrim($value,'%'));

			case '%LIKE%':
			case 'NOT %LIKE%':
				return $this->getQuotedValue('%'.trim($value,'%').'%'); 

			default:
				return $this->getQuotedValue($value);
		}

	}#end function
















	/*==============================================================================================
	Информационные функции
	==============================================================================================*/



	/*
	 * Функция определяет, является ли SQL запрос "на запись" 
	 */
	public function isWriteSql($sql){
		return (preg_match('/^\s*"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD DATA|COPY|ALTER|GRANT|REVOKE|LOCK|UNLOCK)\s+/i', $sql));
	}#end function
















	/*==============================================================================================
	Функции формирования SQL: инструкции SELECT UPDATE DELETE и прочее
	==============================================================================================*/



	/*
	 * Построение UPDATE запроса
	 */
	public function buildUpdate($tables='', $fields=array(), $filter=array(), $separator='AND'){

		if(empty($tables)||empty($fields)) return false;

		if(is_array($tables))
			$tables = implode(',',array_map(array($this,'getQuotedField'),$tables));
		else
			$tables = $this->getQuotedField($tables);

		$fields = $this->buildSqlConditions($fields, ',');
		$filter = $this->buildSqlConditions($filter, $separator);

		return 'UPDATE '.$tables.' SET '.$fields.(empty($filter) ? '' : ' WHERE '.$filter);
	}#end function



	/*
	 * Построение DELETE запроса
	 */
	public function buildDelete($table='', $filter=array(), $separator='AND'){

		if(empty($table)||empty($filter)) return false;

		$table = $this->getQuotedField($table);
		$filter = $this->buildSqlConditions($filter, $separator);

		return 'DELETE FROM '.$table.(empty($filter) ? '' : ' WHERE '.$filter);
	}#end function



	/*
	 * Построение INSERT запроса
	 */
	public function buildInsert($table='', $data=array()){

		if(empty($table)||empty($data)) return false;

		$table = $this->getQuotedField($table);
		
		$fields = array();
		$values = array();
		$assoc_count = 0;

		foreach ($data as $key => $val){

			if(!is_numeric($key)){
				$assoc_count++;
				$fields[] = $this->getQuotedField($key);
			}else{
				$fields[] = $key;
			}

			if(!is_array($val)) $val = array($val, BIND_TEXT);

			$v = (isset($val[0]) ? $val[0] : NULL);
			$t = (isset($val[1]) ? $val[1] : BIND_TEXT);
			if(is_null($v)){
				$values[] = 'NULL';
				continue;
			}

			switch($t){
				case BIND_NULL: $values[] = 'NULL'; break;
				case BIND_NUM: $values[] = $v; break;
				case BIND_TEXT: 
				default:
					$values[] = $this->getQuotedValue($v);
			}
		}

		if($assoc_count > 0)
			return "INSERT INTO ".$table." (".implode(', ', $fields).") VALUES (".implode(', ', $values).")";

		return "INSERT INTO ".$table." VALUES (".implode(', ', $values).")";
	}#end function



	/*
	 * Преобразование текстовой строки, содержащей числовые перечисления, в SQL запрос
	 * 
	 * $str 		- Текстовая строка с перечислением числовых значений
	 * $field		- Имя поля, которое будет подставляться в SQL. 
	 * 				  Поле должно быть передано уже с учетом getQuotedField(), т.е. обрамлено в соответствии с используемой СУБД
	 * $delimiter 	- Символ-разделитель, обозначающий перечисление
	 * $group 		- Символ-разделитель, обозначающий группу
	 * 
	 * Пример:
	 * str: 12, 34-56, 47, 31, 85-97 (базовая строка)
	 * field: num (название поля таблицы, которое следует подставить)
	 * Результат:
	 * num IN (12,47,31) OR (num BETWEEN 34 AND 56) OR (num BETWEEN 85 AND 97)
	 * 
	 * Еще примеры:
	 * 1,2,3			-> field IN (1,2,3)
	 * 1-3				-> (field BETWEEN 1 AND 3)
	 * 1,3-6,9			-> field IN (1,9) OR (field BETWEEN 3 AND 6)
	 * 1,-6,9			-> field IN (1,9) OR field <= 6
	 * 1,2,3,6-			-> field IN (1,2,3) OR field >= 6
	 */
	public function buildIntList($str='',$field='', $delimiter=',', $group='-'){

		$result='';
		if(empty($field))$field = '?';
		if($field[0] != '?' && $field[0] != ':') $field = $this->getQuotedFieldEscape($field);
		$single = array();
		$multi = array();
		$data = explode(',', $str);
		foreach($data as $item){
			$item = str_replace(' ', '', $item);
			if(strpos($item,$group)===false){
				if(is_numeric($item)) $single[] = $item;
			}else{
				$item = explode($group, $item);
				$v1 = $item[0];
				$v2 = $item[1];
				if(is_numeric($v1)&&is_numeric($v2)){
					if($v2==$v1) $single[] = $v1;
					else{
						if($v1>$v2){
							$tmp = $v1;
							$v1 = $v2;
							$v2 = $tmp;
						}
						$multi[] = '('.$field.' BETWEEN '.$v1.' AND '.$v2.')';
					}
				}else
				if(empty($v1) && is_numeric($v2)) $multi[] = $field.' <= '.$v2;
				else
				if(empty($v2) && is_numeric($v1)) $multi[] = $field.' >= '.$v1;
			}
		}

		$result_single = (count($single) > 1 ) ? $field.' IN ('.implode(',',$single).')' : ( (count($single) == 1 ) ? $field.'='.$single[0] : '');
		$result_multi = (count($multi) > 1 ) ? implode(' OR ',$multi) : ( (count($multi) == 1 ) ? $multi[0] : '');
		if(strlen($result_single)>0 || strlen($result_multi)>0)
			$result = $result_single.((strlen($result_single)>0&&strlen($result_multi)>0) ? ' OR ' : '').$result_multi;

		return $result;
	}#end function















	/*==============================================================================================
	Рабочие функции
	==============================================================================================*/



	/*
	 * Подключение к базе данных. Получение экземпляра PDO
	*/
	public function connect() {

		if(!$this->correct_init) return false;

		#если есть открытое соединение, закрываем
		if ($this->connected) $this->close();

		#Получения идентификатора DSN,
		#Если DSN не поучен, значит тип драйвера СУБД указан неверно
		if( ($this->dsn = $this->getDSN()) === false){
			return $this->doErrorEvent(5, __FUNCTION__, __LINE__,json_encode($this->options)); #Внутренняя ошибка: Не удалось вгенерировать DSN строку
		}


		#Подключение и получение PDO
		try {

			$connect_options = array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "'.$this->options['charset'].'"',
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			);
			if($this->options['type'] == 'mysql') $connect_options[PDO::ATTR_AUTOCOMMIT] = true;

			$this->dbh = new PDO($this->dsn, $this->options['username'], $this->options['password'], $connect_options);


		}
		#Если подключение не удалось, генерируем ошибку и возвращаем FALSE
		catch(PDOException $e){
			#Внутренняя ошибка: Не удалось установить соединение с сервером баз данных
			return $this->doErrorEvent(6, __FUNCTION__, __LINE__, json_encode(array(
				'dsn'=>$this->dsn,
				'errno'=>$e->getCode(),
				'errmsg'=>$e->getMessage()
			)));
		}

		#если в опциях передана кодировка, устанавлмваем её
		if ($this->options['charset']){
			switch($this->options['type']){
				case 'mysql': $this->dbh->exec('SET CHARACTER SET '.SQ.$this->options['charset'].SQ); break;
				case 'pgsql': $this->dbh->exec('SET CLIENT_ENCODING TO '.SQ.$this->options['charset'].SQ); break;
			}
		}

		return $this->connected = true;
	}#end function




	/*
	 * Получение идентификатора DSN.
	 * 
	 * Поддерживаемые типы соединений:
	 * sqlsrv 	-> Microsoft SQL Server (Работает под Windows, принимает все SQL Server версии [максимально поддерживаемая версия - MS SQL Server 2008])
	 * mssql	-> Microsoft SQL Server (Работает под Windows и Linux, но поддерживает только MS SQL Server 2000)
	 * mysql 	-> MySQL
	 * pgsql	-> PostgreSQL
	 * ibm		-> IBM
	 * dblib	-> DBLIB
	 * oracle	-> ORACLE
	 * ifmx 	-> Informix
	 * fbd		-> Firebird
	*/
	private function getDSN(){

		$host = $this->options['host'];
		$port = $this->options['port'];
		$database = $this->options['database'];
		$charset = $this->options['charset'];

		if(empty($host)||empty($database)) return false;

		switch($this->options['type']){

			case 'mssql':
				'mssql:host='.$host.';dbname='.$database;
			break;

			case 'sqlsrv':
				return 'sqlsrv:server='.$host.';database='.$database;
			break;

			case 'ibm':
				return 'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE='.$database.'; HOSTNAME='.$host.';PORT='.$port.';PROTOCOL=TCPIP;';
			break;

			case 'dblib':
				return 'dblib:host='.$host.':'.$port.';dbname='.$database;
			break;

			case 'oracle':
				return 'OCI:dbname='.$database.';charset='.$charset;
			break;

			case 'ifmx':
				return 'informix:DSN=InformixDB';
			break;

			case 'fbd':
				return 'firebird:dbname='.$host.':'.$database;
			break;

			case 'mysql':
				return 'mysql:host='.$host.';dbname='.$database;
			break;

			case 'pgsql':
				return 'pgsql:dbname='.$database.';host='.$host;
			break;
		}

		return false;
	}#end function



	/*
	 * Закрытие соединения
	 */
	public function close(){

		if(!$this->correct_init) return false;

		#если нет открытого соединения, выходим
		if (!$this->connected) return false;

		#если начата транзакция, заканчиваем с отменой изменений
		if ($this->inTransaction()) $this->rollBack();

		$this->freeResult();

		#Закрываем соединение, обнуляем свойства
		$this->dbh = $this->dsn = null;

		$this->connected = false;

		return true;
	}#end function



	/*
	 * Управление транзакциями
	*/
	private function transactionControl($method) {

		if(!$this->correct_init) return false;

		#если нет открытого соединения...
		if (!$this->connected) {

			#...при старте транзакции...
			if ($method == 'beginTransaction') {

				#...пытаемся создать. Если не удается создать, генерируем предупреждение
				if (!$this->connect()) {
					return $this->doErrorEvent(11, __FUNCTION__, __LINE__); #Не удалось начать транзакцию, потому что соединение с БД не установлено
				}

			}
			#...при завершении транзакции...
			else {
				return $this->doErrorEvent(12, __FUNCTION__, __LINE__); #Не удалось завершить транзакцию, потому что соединение с БД не было установлено
			}

		}

		#Определение, есть ли запущенная транзакция
		$in_transaction = $this->inTransaction();

		#При старте транзакции, если транзакция уже начата, выходим и генерируем ошибку
		if ($method == 'beginTransaction' && $in_transaction) {
			return $this->doErrorEvent(13, __FUNCTION__, __LINE__); #Нельзя начать новую транзакцию, пока предыдущая не будет завершена
		}

		#При завершении транзакции, если нет транзакции, генерируем ошибку и выходим
		else if ($method == 'commit' && !$in_transaction) {
			return $this->doErrorEvent(17, __FUNCTION__, __LINE__); #Высоз COMMIT при отсутствии транзакции
		}

		#При завершении транзакции, если нет транзакции, генерируем ошибку и выходим
		else if ($method == 'rollBack' && !$in_transaction) {
			return $this->doErrorEvent(18, __FUNCTION__, __LINE__); #Высоз ROLLBACK при отсутствии транзакции
		}

		#Выполняем требуемое действие. Если по каким-либо причинам это сделать не удалось, генерируем ошибку 
		if (!$result = $this->dbh->$method()) {

			#15 - Ошибка PDO при старте новой транзакции
			#16 - Ошибка PDO при завершении транзакции
			return $this->doErrorEvent( ($method == 'beginTransaction' ? 15 : 16) , __FUNCTION__, __LINE__, $method);
		}

		switch($method){

			case 'beginTransaction':
				$this->transact_queries = array();
			break;

			case 'commit':
				$this->queryLog(true); #Логирование транзакции
			break;

			case 'rollBack':
				$this->transact_queries = array();
			break;

		}


		return true;
	}#end function


	/*
	 * Проверка наличия транзакции
	 */
	private function inTransaction() {

		if(!$this->correct_init) return false;

		return
			$this->connected &&
			$this->dbh &&
			$this->dbh->inTransaction();

	}#end function



	/*
	 * Возвращает код ошибки SQLSTATE. Приводит к целому числу, если установлен соотв. аргумент
	 */
	private function getPdoErrorCode($asInt = false) {

		if (!$this->connected || !$this->dbh) return $asInt ? 0 : '0';
		$error_code = $this->dbh->errorCode();

		return $asInt ? intval($error_code) : $error_code;
	}#end function



	/*
	 * Возвращает ошибку PDO. 
	 * 
	 * Возвращает массив с элементами:
	 * 1 - код ошибки SQLSTATE
	 * 2 - код ошибки драйвера
	 * 3 - описание ошибки драйвера
	 */
	private function getPdoErrorInfo() {
		if (!$this->connected || !$this->dbh) return null;
		return $this->dbh->errorInfo();
	}#end function



	/*
	 * Проверяет, установлено ли соединение.
	 * 
	 * Если соединение не установлено, и $autoconnect = true - пытается установить соединение.
	 */
	private function checkConnection($autoconnect=true){

		if (!$this->connected && $autoconnect) $this->connect();

		return $this->connected;
	}#end function



















	/*==============================================================================================
	Функции SQL
	==============================================================================================*/



	/*
	 * Выполняет сформированный запрос. Возвращает объект PDOStatement
	 */
	public function execute($query = null){

		if(!$this->correct_init) return false;

		#если нет открытого соединения генерируем предупреждение и выходим
		if (!$this->checkConnection()) return $this->doErrorEvent(20, __FUNCTION__, __LINE__); #Не удалось выполнить SQL-запрос, потому что соединение с БД не было установлено

		#Очистка результатов за исключением массива binds и SQL
		$this->freeResult(array('binds','sql'));

		$sql_query = (!empty($query) ? $query : $this->sql);

		if(empty($query)){
			if(($sql_query = $this->parseTemplate()) === false) return false;
		}else{
			/*
			#Замена обрамлений для полей
			switch($this->options['type']){
				case 'pgsql': 
					$sql_query = str_replace(
						array('` ',' `','`.'),
						array('" ',' "','".'),
						$sql_query
					); 
				break;
			}
			*/
		}

		#SQL инструкция не задана
		if(empty($sql_query)) return $this->doErrorEvent(8, __FUNCTION__, __LINE__); #Невозможно выполнить SQL запрос, поскольку не задана SQL инструкция

		#Запрос
		try{
			$this->res = $this->dbh->query($sql_query);
		}catch(PDOException $e){
			return $this->doErrorEvent(31, __FUNCTION__, __LINE__, json_encode(array('message'=>$e->getMessage(),'sql'=>$sql_query))); ##Не удалось сформировать запрос. Ошибка PDO
		}

		#Последний успешный SQL запрос
		$this->last_query = $sql_query;

		#Если SQL запрос выпоняется в рамках транзакции,
		#добавляем SQL в пут операций транзакции
		if( $this->inTransaction() == true) array_push($this->transact_queries, $sql_query);

		#Логирование операции
		$this->queryLog();

		return $this->res;
	}#end function



	/*
	 * Освобождает результат запроса
	 */
	public function freeResult($leave = null){

		if (!is_array($leave)) $leave = array($leave);

		foreach(array(
			'binds'					=> array(),
			'inerpolation_result'	=> array(),
			'res'					=> null,
			'fetch'					=> array(),
			'records'				=> null,
			'row'					=> null
		) as $k => $v) {
			if (in_array($k, $leave)) continue;
			if($k=='res' && $this->res instanceof PDOStatement) $this->res->closeCursor();
			$this->$k = $v;
		}

		return true;
	}#end function



	/*
	 * Последний вставленный ID с автоинкремента
	 */
	public function insertId($name = null){
		return (int)$this->dbh->lastInsertId($name);
	}#end function



	/*
	 * Количество затронутых строк при изменении
	 */
	public function affectedRows() {

		if (!$this->res) return false;

		return $this->res->rowCount();
	}#end function



	/*
	 * Количество строк выборки
	 */
	public function numRows(){
		return is_array($this->fetch) ? count($this->fetch) : false;
	}#end function



	/*
	 * Выбор одной записи
	 */
	function result($sql=null){

		if (false === $this->execute($sql)) return false;

		$this->fetch = $this->res->fetchAll(PDO::FETCH_NUM);
		$num_rows = $this->numRows();

		if ($num_rows == 0) return NULL;
		if ($num_rows > 1) return $this->doErrorEvent(40, __FUNCTION__, __LINE__,$this->last_sql); #Запрос в функции result() вернул более одного результата
		if (!isset($this->fetch[0][0])) return NULL;

		$result = $this->fetch[0][0];
		$this->freeResult();

		return stripslashes($result);
	}#end function



	/*
	 * INSERT
	 */
	public function insert($sql=null, $name = null){

		if (false === $this->execute($sql)) return false;

		return $this->insertId($name);
	}#end function



	/*
	 * UPDATE
	 */
	public function update($sql=null){

		if (false === $this->execute($sql)) return false;

		return $this->affectedRows();
	}#end function



	/*
	 * DELETE
	 */
	public function delete($sql=null){

		if (false === $this->execute($sql)) return false;

		return $this->affectedRows();
	}#end function




	/*
	* результаты запроса SELECT
	*
	* Принимает аргументы:
	* $sql - SQL инструкция, если не указана, будет выполнена инструкция из шаблона prepare()
	* $type - тип возвращаемых результатов: DB_RESULT_ASSOC - каждая запись в ассоциированном массиве, DB_RESULT_NUM - каждая запись в нумерованном массиве и т.п.
	* $records - массив, к которому будут добавлены записи результатов выборки
	* $binds - дополнительные вызовы binds, для замены существующих значений
	*
	* Возвращает:
	* Массив результатов или FALSE в случае ошибки
	*/
	public function select($sql = null, $records = null, $type = DB_ASSOC){

		if (false === $this->execute($sql)) return false;

		$this->records = !is_array($records) ? array() : $records;
		$this->fetch = $this->res->fetchAll($type);

		$this->records = ( !empty($this->records) ? array_merge($this->records, $this->fixSlashes($this->fetch)) : $this->fixSlashes($this->fetch));
		$this->freeResult('records');

		return $this->records;
	}#end function



	/*
	* результаты запроса SELECT (одна строка)
	*
	* Принимает аргументы:
	* $sql - SQL инструкция, если не указана, будет выполнена инструкция из шаблона prepare()
	* $type - тип возвращаемых результатов: DB_RESULT_ASSOC - запись в ассоциированном массиве, DB_RESULT_NUM - запись в нумерованном массиве и т.п.
	* $binds - дополнительные вызовы binds, для замены существующих значений
	*
	* Возвращает:
	* Массив результатов или FALSE в случае ошибки
	*/
	public function selectRecord($sql = null, $type = DB_ASSOC){

		if (false === $this->execute($sql)) return false;

		$this->fetch = $this->res->fetch($type);

		$this->row = $this->fetch === false ? NULL : $this->fixSlashes($this->fetch);
		$this->freeResult('row');

		return $this->row;
	}#end function



	/*
	* результаты запроса SELECT со значениями указанного поля в качестве ключа
	*
	* Принимает аргументы:
	* $field - поле, которое будет использоваться в качестве ключа
	* $sql - SQL инструкция, если не указана, будет выполнена инструкция из шаблона prepare()
	* $type - тип возвращаемых результатов: DB_RESULT_ASSOC - каждая запись в ассоциированном массиве, DB_RESULT_NUM - каждая запись в нумерованном массиве и т.п.
	* $records - массив, к которому будут добавлены записи результатов выборки
	* $prefix - Префикс, добавляемый перед полученным значением ключа: префикс=ххх, ключ из БД = 123, ключ = ххх123
	*
	* Возвращает:
	* Массив результатов или FALSE в случае ошибки
	*/
	public function selectByKey($field = null, $sql = null, $prefix='', $records = null, $type = DB_ASSOC){

		if (false === $this->execute($sql)) return false;

		$this->fetch = $this->res->fetchAll($type);

		$this->records = !is_array($records) ? array() : $records;

		foreach ($this->fetch as $row) {
			if (isset($row[$field]))
				$key = $row[$field];
			else {
				$fields = array_keys($row);
				$key = $fields[0];
			}
			$this->records[$prefix.$key] = $this->fixSlashes($row);
		}

		reset($this->records);
		$this->freeResult('records');

		return $this->records;
	}#end function



	/*
	* результаты запроса SELECT по одному полю $field
	*
	* Принимает аргументы:
	* $field - поле, которое будет использоваться для вывода данных
	* $sql - SQL инструкция, если не указана, будет выполнена инструкция из шаблона prepare()
	* $type - тип возвращаемых результатов: DB_RESULT_ASSOC - каждая запись в ассоциированном массиве, DB_RESULT_NUM - каждая запись в нумерованном массиве и т.п.
	* $records - массив, к которому будут добавлены записи результатов выборки
	* $binds - дополнительные вызовы binds, для замены существующих значений
	*
	* Возвращает:
	* Одномерный массив результатов со значениями поля FIELD или FALSE в случае ошибки
	*/
	public function selectField($field = 0, $sql = null, $records = null, $type = DB_ASSOC){

		if (false === $this->execute($sql)) return false;

		$this->fetch = $this->res->fetchAll($type);

		$this->records = !is_array($records) ? array() : $records;

		foreach ($this->fetch as $row) {
			if (isset($row[$field]))
				$key = $row[$field];
			else {
				$fields = array_keys($row);
				$key = $fields[0];
			}
			$this->records[] = $this->fixSlashes($key);
		}

		reset($this->records);
		$this->freeResult('records');

		return $this->records;
	}#end function



	/*
	 * Запрос без возврата результата
	 */
	public function simple($sql=null){

		if (false === $this->execute($sql)) return false;

		return true;
	}#end function




	/*
	 * Удаление экранирования со значений
	*/
	private function fixSlashes($src = null) {

		if (!$src) return $src;
		if (is_array($src)) {
			$res = array();
			foreach ($src as $k => $v) {
				$res[$k] = $this->fixSlashes($v);
			}
			return $res;
		}
		elseif (is_string($src))
			return stripslashes($src);
		else
			return $src;

	}#end function




}#end class


?>