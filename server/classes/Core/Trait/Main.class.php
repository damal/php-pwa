<?php
/*==================================================================================================
--------------------------------
Модуль	: Ядро платформы
Описание: Основные функции
Версия	: 1.0.0/ALPHA
Дата	: 2012-05-13
Автор	: Станислав В. Третьяков
--------------------------------
==================================================================================================*/



trait Core_Trait_Main{


	/*==============================================================================================
	Функции
	==============================================================================================*/


	#--------------------------------------------------
	# Аналог PHP is_array(), только быстрее
	#--------------------------------------------------
	#Приведено как конструкция, не для использования
	#При вызове через self::is_array() работает медленнее PHP is_array()
	#Однако при использовании непосредственно конструкции ((array) $mixed === $mixed)
	#вместо PHP is_array() увеличивает скорость работы в полтора раза 
	public static function is_array($mixed){
		return ((array) $mixed === $mixed);
	}#end function




	/*
	 * Пишет каждое слово в строке с заглавной буквы,
	 * даже если слова разделены не пробелами, а символом '-'
	 * 
	 * Применяется для корректного формирования заголовков Headers
	 * 
	 * x-requested-with -> X-Requested-With
	 * X-REQUESTED-with -> X-Requested-With
	 */
	public static function ucwordsHyphen($str=''){
		//str_replace('- ','-',ucwords(str_replace('-','- ',strtolower($str))));
		return strtr(ucwords(strtolower(strtr($str, '-', ' '))),' ','-');
	}#end function


}#end class


?>