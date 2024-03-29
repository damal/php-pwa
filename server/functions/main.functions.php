<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Основные функции
Версия	: 1.0.0/ALPHA
Дата	: 2012-04-20
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/










	/*==============================================================================================
	Функции работы со временем
	==============================================================================================*/




#--------------------------------------------------
# Возвращает время в микросекундах
#--------------------------------------------------
function main_microtime(){
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}#end function



#--------------------------------------------------
# Возвращает текущий временной штамп
#--------------------------------------------------
function main_timestamp($format="Y-m-d H:i:s"){
	return date($format, time());
}#end function













	/*==============================================================================================
	Формирование JS массивов и объектов
	==============================================================================================*/



/*
#--------------------------------------------------
# Функция преобразует ассоциированный массив в объект JS
#--------------------------------------------------
#
# Входные параметры:
# $arr(*) - ассоциированный массив полей, которые нужно преобразовать
# $white - массив полей по белому списку, только их значения учавствуют в результате
#          черный список имеет приоритет над белым (если в черном и белом списках есть поле "hash", оно не будет учтено в результирующей строке)
# $black - массив полей по черному списку
# 
# Функция возвращает строку объекта JS
*/
function main_toJSObject($arr=null, $white = null, $black = null){

	if(empty($arr)||!is_array($arr)) return '{}';
	$use_white = (!empty($white)&&is_array($white));
	$use_black = (!empty($black)&&is_array($black));

	$count = 0;
	$result = '{';
	foreach($arr as $key=>$value){

		#Проверка на наличесе в списке игнорируемых полей, которые не участвуют в результате
		if($use_black&&in_array($key,$black)) continue;
		if(!$use_white || ($use_white&&in_array($key,$white))){
			if($count > 0) $result.=',';
			$result .= '"'.addcslashes($key,"\\\'\"\n\r\t").'":'.(is_null($value) ? 'null' : (is_array($value) ? main_toJSObject($value, $white, $black) : '"'.addcslashes($value,"\\\'\"\n\r\t").'"'));
			$count++;
		}

	}
	$result.= '}';

	return $result;

}#end function



/*
#--------------------------------------------------
# Функция преобразует массив PHP в массив JS
#--------------------------------------------------
#
# Входные параметры:
# $arr(*) - массив полей, которые нужно преобразовать
# $white - массив полей по белому списку, только их значения учавствуют в результате
#          черный список имеет приоритет над белым (если в черном и белом списках есть поле "hash", оно не будет учтено в результирующей строке)
# $black - массив полей по черному списку
# 
# Функция возвращает строку массива JS
*/
function main_toJSArray($arr=null, $white = null, $black = null){

	if(empty($arr)||!is_array($arr)) return '[]';
	$use_white = (!empty($white)&&is_array($white));
	$use_black = (!empty($black)&&is_array($black));

	$count = 0;
	$result = '{';
	foreach($arr as $value){

		#Проверка на наличесе в списке игнорируемых полей, которые не участвуют в результате
		if($use_black&&in_array($value, $black)) continue;
		if(!$use_white || ($use_white&&in_array($value, $white))){
			if($count > 0) $result.=',';
			$result .= (is_null($value) ? 'null' : (is_array($value) ? main_toJSArray($value, $white, $black) : '"'.addslashes($value).'"'));
			$count++;
		}

	}
	$result.= ']';

	return $result;
}#end function



/*
#--------------------------------------------------
# Функция преобразует массив PHP в массив JS Объектов
#--------------------------------------------------
#
# Входные параметры:
# $arr(*) - ассоциированный массив полей, которые нужно преобразовать
# $white - массив полей по белому списку, только их значения учавствуют в результате
#          черный список имеет приоритет над белым (если в черном и белом списках есть поле "hash", оно не будет учтено в результирующей строке)
# $black - массив полей по черному списку
# 
# Функция возвращает строку массива JS объектов
*/
#Функция преобразовывает массив PHP в массив JS
function main_toJSObjectArray($arr=null, $white = null, $black = null){

	if(empty($arr)||!is_array($arr)) return '[]';

	$count = 0;
	$result = '{';
	foreach($arr as $value){

		if(empty($value)) continue;
		if($count > 0) $result.=',';
		$result .= (is_array($value) ? main_toJSObject($value, $white, $black) : '"'.addslashes($value).'"');
		$count++;

	}
	$result.= ']';

	return $result;
}#end function











	/*==============================================================================================
	Работа с текстом
	==============================================================================================*/


#--------------------------------------------------
# Функция превода текста с кириллицы в траскрипт
#--------------------------------------------------
function main_rus2eng($st='',$remove=array()){

	#Сначала заменяем "односимвольные" фонемы.
	$st=strtr($st,"абвгдеёзийклмнопрстуфхъыэ_", "abvgdeeziyklmnoprstufh'iei");
	$st=strtr($st,"АБВГДЕЁЗИЙКЛМНОПРСТУФХЪЫЭ_", "ABVGDEEZIYKLMNOPRSTUFH'IEI");

	#Затем - "многосимвольные".
	$st=strtr($st, array(
		"ж"=>"zh", "ц"=>"ts", "ч"=>"ch", "ш"=>"sh", 
		"щ"=>"shch","ь"=>"", "ю"=>"yu", "я"=>"ya",
		"Ж"=>"ZH", "Ц"=>"TS", "Ч"=>"CH", "Ш"=>"SH", 
		"Щ"=>"SHCH","Ь"=>"", "Ю"=>"YU", "Я"=>"YA",
		"ї"=>"i", "Ї"=>"Yi", "є"=>"ie", "Є"=>"Ye"
		)
	);
	#Затем - удаляем совпадения, которые надо исключить
	$st = str_replace($remove, '', $st);

	// Возвращаем результат.
	return $st;
}#end function







?>