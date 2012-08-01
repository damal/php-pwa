<?php
/***********************************************************************
 * AJAX обработчик: ACL MANAGER
 * Работа с ACL объектами
 * 
 * 
 * acl.object.add - Добавление нового объекта ACL
 * acl.object.update - Обновление объекта ACL
 * acl.object.delete - Удаление объекта ACL
 * 
 * acl.group.add - Добавление новой группы ACL
 * acl.group.update - Изменение группы ACL
 * acl.group.delete - Удаление ACL группы
 * 
 * acl.company.add - Добавление новой организации
 * acl.company.update - Изменение организации
 * acl.company.delete - Удаление организации
 * 
 * acl.role.objects - Запрос списка объектов ACL в контейнере роли
 * acl.role.insert - Добавление объекта ACL в контейнер роли
 * acl.role.delete - Удаление объекта ACL из контейнера роли
 * 
 * 
 **********************************************************************/

#Делаем текущий чанк уникальным
$this->chunk->setUnique();

#Проверка задано ли действие
if(empty($this->action)){
	$this->chunk->addMsgError('Ошибка выполнения', 'AJAX действие не задано в '.Request::_getRequestPath());
	return;

}

#Проверка доступа к объекту
if(!Acl::_userAccess($this->action)){
	$this->chunk->addMsgError('Ошибка доступа', $this->action.': '.Acl::_getErrstr());
	return;
}





#Обработка AJAX запроса, в зависимости от запрошенного действия
switch($this->action){


	/*
	 * Добавление нового объекта ACL * Добавление нового объекта ACL * Добавление нового объекта ACL * Добавление нового объекта ACL * Добавление нового объекта ACL * Добавление нового объекта ACL
	 */
	case 'acl.object.add':
	case 'role.admin':

		$object_name = Request::_getGPCValue('object_name','pg','');
		$object_desc = Request::_getGPCValue('object_desc','pg','');
		$object_type = Request::_getGPCValue('object_type','pg','');
		$object_group = Request::_getGPCValue('object_group','pg','');
		if(!is_array($object_group))$object_group = array($object_group);
		$object_lock = (Request::_getGPCValue('object_lock','pg', 0) == 1 ? 1 : 0);
		$form = new Validator(array(
			array(
				'name' => 'Имя объекта',
				'value' => $object_name,
				'type' => 'text',
				'required' => true,
				'exclude' => array('/','\\','"','\'',' ')
			),
			array(
				'name' => 'Тип объекта',
				'type' => 'uint',
				'value' => $object_type,
				'required' => true,
				'min' => 1
			),
			array(
				'name' => 'Группа объекта',
				'type' => 'uint',
				'value' => $object_group,
				'required' => true,
				'min' => 1
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if(!Acl::_objectTypeExists($object_type)){
			$this->chunk->addMsgError('Ошибка выполнения', 'Указанный тип ACL объекта не существует.');
			return;
		}

		$db = Acl::_getDb();

		$db->transaction();

		$db->prepare('SELECT count(*) FROM ? WHERE ?=? LIMIT 1');
		$db->bindField('acl_objects');
		$db->bindField('name');
		$db->bindText($object_name);
		if(($unique_test = $db->result()) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при проверке имени объекта.');
			$db->rollback(); return;
		}
		
		if($unique_test > 0){
			$this->chunk->addMsgError('Ошибка выполнения', 'Объект с именем '.$object_name.' уже существует.');
			$db->rollback(); return;
		}

		$sql = $db->buildInsert(
			'acl_objects',
			array(
				'type' => $object_type,
				'name' => $object_name,
				'desc' => $object_desc,
				'lock' => $object_lock
			)
		);

		if(($object_id = $db->insert($sql,'acl_objects_object_id_seq')) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при добавлении объекта в базу данных.');
			$db->rollback(); return;
		}

		foreach($object_group as $g){
			if(!Acl::_groupExists($g)) continue;
			$sql = $db->buildInsert(
				'acl_object_groups',
				array(
					'object_id' => $object_id,
					'group_id' => $g
				)
			);
			if($db->insert($sql) === false){
				$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при добавлении группы объекта в базу данных.');
				$db->rollback(); return;
			}
		}

		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>$object_type,
			#Идентификатор объекта
			'object_id'=>$object_id,
			#Описание действия
			'desc'=>'Создан ACL объект ID:'.$object_id.' ('.$object_name.')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array('create'=>array(
				'object_id'=>$object_id,
				'type' => $object_type,
				'name' => $object_name,
				'desc' => $object_desc,
				'groups' =>$object_group,
				'lock' => $object_lock
			))
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------


		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/actual', false);
		}

		$db->commit();

		#Обновление массива объектов
		Acl::_dbLoadObjects();

		#Возвращаем массив всех ACL объектов
		$this->chunk->setData(
			array(
				'objects' => Acl::_getAllObjects()
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.object.add












	/*
	 * Обновление объекта ACL * Обновление объекта ACL * Обновление объекта ACL * Обновление объекта ACL * Обновление объекта ACL * Обновление объекта ACL * Обновление объекта ACL
	 */
	case 'acl.object.update':


		$object_id = Request::_getGPCValue('object_id','pg','');
		$object_name = Request::_getGPCValue('object_name','pg','');
		$object_desc = Request::_getGPCValue('object_desc','pg','');
		$object_type = Request::_getGPCValue('object_type','pg','');
		$object_group = Request::_getGPCValue('object_group','pg','');
		if(!is_array($object_group))$object_group = array($object_group);
		$object_lock = (Request::_getGPCValue('object_lock','pg', 0) == 1 ? 1 : 0);
		$form = new Validator(array(
			array(
				'name' => 'Идентификатор объекта',
				'type' => 'uint',
				'value' => $object_id,
				'required' => true,
				'min' => 1
			),
			array(
				'name' => 'Имя объекта',
				'value' => $object_name,
				'type' => 'text',
				'required' => true,
				'exclude' => array('/','\\','"','\'',' ')
			),
			array(
				'name' => 'Тип объекта',
				'type' => 'uint',
				'value' => $object_type,
				'required' => true,
				'min' => 1
			),
			array(
				'name' => 'Группа объекта',
				'type' => 'uint',
				'value' => $object_group,
				'required' => true,
				'min' => 1
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($object = Acl::_getObject($object_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL объект с указанным идентификатором не существует.');
			return;
		}

		if(!Acl::_objectTypeExists($object_type)){
			$this->chunk->addMsgError('Ошибка выполнения', 'Указанный тип ACL объекта не существует.');
			return;
		}

		$db = Acl::_getDb();

		$db->transaction();

		$db->prepare('SELECT count(*) FROM ? WHERE ?=? AND ?!=? LIMIT 1');
		$db->bindField('acl_objects');
		$db->bindField('name');
		$db->bindText($object_name);
		$db->bindField('object_id');
		$db->bindNum($object_id);
		if(($unique_test = $db->result()) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при проверке имени объекта.');
			$db->rollback(); return;
		}
		
		if($unique_test > 0){
			$this->chunk->addMsgError('Ошибка выполнения', 'Объект с именем '.$object_name.' уже существует.');
			$db->rollback(); return;
		}

		$sql = $db->buildUpdate(
			'acl_objects',
			array(
				'type' => $object_type,
				'name' => $object_name,
				'desc' => $object_desc,
				'lock' => $object_lock
			),
			array(
				'object_id' => $object_id
			)
		);

		if(($object_id = $db->insert($sql,'acl_objects_object_id_seq')) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при обновлении объекта в базе данных.');
			$db->rollback(); return;
		}

		#Удаляем предыдущие группы объекта
		$sql = $db->buildDelete(
			'acl_object_groups',
			array(
				'object_id' => $object_id
			)
		);
		if($db->delete($sql) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при обновлении группы объекта в базе данных.');
			$db->rollback(); return;
		}

		#Добавляем новые группы объектов
		foreach($object_group as $g){
			if(!Acl::_groupExists($g)) continue;
			$sql = $db->buildInsert(
				'acl_object_groups',
				array(
					'object_id' => $object_id,
					'group_id' => $g
				)
			);
			if($db->insert($sql) === false){
				$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при добавлении группы объекта в базу данных.');
				$db->rollback(); return;
			}
		}

		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>$object_type,
			#Идентификатор объекта
			'object_id'=>$object_id,
			#Описание действия
			'desc'=>'Обновлен ACL объект ID:'.$object_id.' ('.$object['name'].')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array(
				'prev'=>array(
					'object_id'=>$object_id,
					'type' => $object['type'],
					'name' => $object['name'],
					'desc' => $object['desc'],
					'groups' =>$object['groups'],
					'lock' => $object['lock']
				),
				'new'=>array(
					'object_id'=>$object_id,
					'type' => $object_type,
					'name' => $object_name,
					'desc' => $object_desc,
					'groups' =>$object_group,
					'lock' => $object_lock
				)
			)
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------

		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/last_update', time());
			XCache::_set('acl/actual', false);
		}
		#Если XCache не активен, тогда обновляем во всех источниках данных ACL статус у пользователей статус need_update
		else{
			if(Acl::_dbSetNeedUpdate() === false){
				$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка установки пользователям признака принудительного обновления прав доступа.');
				$db->rollback(); return;
			}
		}

		$db->commit();

		#Обновление массива объектов
		Acl::_dbLoadObjects();

		#Возвращаем массив всех ACL объектов
		$this->chunk->setData(
			array(
				'objects' => Acl::_getAllObjects()
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.object.update








	/*
	 * Удаление объекта ACL * Удаление объекта ACL * Удаление объекта ACL * Удаление объекта ACL * Удаление объекта ACL * Удаление объекта ACL * Удаление объекта ACL
	 */
	case 'acl.object.delete':


		$object_id = Request::_getGPCValue('object_id','pg','');
		$form = new Validator(array(
			array(
				'name' => 'Идентификатор объекта',
				'type' => 'uint',
				'value' => $object_id,
				'required' => true,
				'min' => 1
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($object = Acl::_getObject($object_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL объект с указанным идентификатором не существует.');
			return;
		}

		$db = Acl::_getDb();

		$db->transaction();

		#Удаление объекта из таблицы acl_objects
		if($db->delete($db->buildDelete('acl_objects',array('object_id' => $object_id))) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении объекта из acl_objects.');
			$db->rollback(); return;
		}

		#Удаление объекта из таблицы acl_access
		if($db->delete($db->buildDelete('acl_access',array('object_id' => $object_id))) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении объекта из acl_access.');
			$db->rollback(); return;
		}

		#Удаление объекта из таблицы acl_object_groups
		if($db->delete($db->buildDelete('acl_object_groups',array('object_id' => $object_id))) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении объекта из acl_object_groups.');
			$db->rollback(); return;
		}

		#Удаление объекта из таблицы acl_roles
		if($db->delete($db->buildDelete('acl_roles',array('object_id' => $object_id,'child_id'=>$object_id),'OR')) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении объекта из acl_roles.');
			$db->rollback(); return;
		}


		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>$object['type'],
			#Идентификатор объекта
			'object_id'=>$object_id,
			#Описание действия
			'desc'=>'Удален ACL объект ID:'.$object_id.' ('.$object['name'].')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array('delete'=>array(
				'object_id'=>$object_id,
				'name' => $object['name']
			))
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------

		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/last_update', time());
			XCache::_set('acl/actual', false);
		}
		#Если XCache не активен, тогда обновляем во всех источниках данных ACL статус у пользователей статус need_update
		else{
			if(Acl::_dbSetNeedUpdate() === false){
				$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка установки пользователям признака принудительного обновления прав доступа.');
				$db->rollback(); return;
			}
		}

		$db->commit();

		#Обновление массива объектов
		Acl::_dbLoadObjects();

		#Возвращаем массив всех ACL объектов
		$this->chunk->setData(
			array(
				'objects' => Acl::_getAllObjects()
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.object.delete










	/*
	 * Добавление новой группы ACL * Добавление новой группы ACL * Добавление новой группы ACL * Добавление новой группы ACL * Добавление новой группы ACL * Добавление новой группы ACL
	 */
	case 'acl.group.add':

		$group_name = Request::_getGPCValue('group_name','pg','');
		$group_desc = Request::_getGPCValue('group_desc','pg','');
		$form = new Validator(array(
			array(
				'name' => 'Имя объекта',
				'value' => $group_name,
				'type' => 'text',
				'required' => true,
				'exclude' => array('/','\\','"','\'',' ')
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		$db = Acl::_getDb();

		$db->transaction();

		$db->prepare('SELECT count(*) FROM ? WHERE ?=? LIMIT 1');
		$db->bindField('acl_groups');
		$db->bindField('name');
		$db->bindText($group_name);
		if(($unique_test = $db->result()) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при проверке имени группы.');
			$db->rollback(); return;
		}
		
		if($unique_test > 0){
			$this->chunk->addMsgError('Ошибка выполнения', 'Группа ACL с именем '.$group_name.' уже существует.');
			$db->rollback(); return;
		}

		$sql = $db->buildInsert(
			'acl_groups',
			array(
				'name' => $group_name,
				'desc' => $group_desc
			)
		);

		if(($group_id = $db->insert($sql,'acl_groups_group_id_seq')) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при добавлении группы ACL в базу данных.');
			$db->rollback(); return;
		}

		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>ACL_OBJECT_GROUP,
			#Идентификатор объекта
			'object_id'=>$group_id,
			#Описание действия
			'desc'=>'Создана ACL группа ID:'.$group_id.' ('.$group_name.')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array('create'=>array(
				'object_id'=>$group_id,
				'type' => ACL_OBJECT_GROUP,
				'name' => $group_name,
				'desc' => $group_desc
			))
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------


		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/actual', false);
		}

		$db->commit();

		#Обновление массива групп
		Acl::_dbLoadGroups();

		#Возвращаем массив всех ACL групп
		$this->chunk->setData(
			array(
				'groups' => Acl::_getAllGroups(),
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.group.add









	/*
	 * Изменение группы ACL * Изменение группы ACL * Изменение группы ACL * Изменение группы ACL * Изменение группы ACL * Изменение группы ACL * Изменение группы ACL
	 */
	case 'acl.group.update':

		$group_id = Request::_getGPCValue('group_id','pg','');
		$group_name = Request::_getGPCValue('group_name','pg','');
		$group_desc = Request::_getGPCValue('group_desc','pg','');
		$form = new Validator(array(
			array(
				'name' => 'Идентификатор группы',
				'type' => 'uint',
				'value' => $group_id,
				'required' => true,
				'min' => 1
			),
			array(
				'name' => 'Имя объекта',
				'value' => $group_name,
				'type' => 'text',
				'required' => true,
				'exclude' => array('/','\\','"','\'',' ')
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($group = Acl::_getGroup($group_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL группа с указанным идентификатором не существует.');
			return;
		}


		$db = Acl::_getDb();

		$db->transaction();

		$db->prepare('SELECT count(*) FROM ? WHERE ?=? AND ?!=? LIMIT 1');
		$db->bindField('acl_groups');
		$db->bindField('name');
		$db->bindText($group_name);
		$db->bindField('group_id');
		$db->bindNum($group_id);
		if(($unique_test = $db->result()) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при проверке имени Acl группы.');
			$db->rollback(); return;
		}

		if($unique_test > 0){
			$this->chunk->addMsgError('Ошибка выполнения', 'Acl группа с именем '.$group_name.' уже существует.');
			$db->rollback(); return;
		}

		$sql = $db->buildUpdate(
			'acl_groups',
			array(
				'name' => $group_name,
				'desc' => $group_desc
			),
			array(
				'group_id' => $group_id
			)
		);

		if($db->update($sql) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при обновлении данных группы ACL.');
			$db->rollback(); return;
		}

		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>ACL_OBJECT_GROUP,
			#Идентификатор объекта
			'object_id'=>$group_id,
			#Описание действия
			'desc'=>'Изменена ACL группа ID:'.$group_id.' ('.$group_name.')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array(
				'prev'=>array(
					'group_id'=>$object_id,
					'name' => $group['name'],
					'desc' => $group['desc']
				),
				'new'=>array(
					'group_id'=>$group_id,
					'name' => $group_name,
					'desc' => $group_desc
				)
			)
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------


		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/actual', false);
		}

		$db->commit();

		#Обновление массива групп
		Acl::_dbLoadGroups();

		#Возвращаем массив всех ACL групп
		$this->chunk->setData(
			array(
				'groups' => Acl::_getAllGroups(),
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.group.update









	/*
	 * Удаление ACL группы * Удаление ACL группы * Удаление ACL группы * Удаление ACL группы * Удаление ACL группы * Удаление ACL группы * Удаление ACL группы
	 */
	case 'acl.group.delete':


		$group_id = Request::_getGPCValue('group_id','pg','');
		$form = new Validator(array(
			array(
				'name' => 'Идентификатор группы',
				'type' => 'uint',
				'value' => $group_id,
				'required' => true,
				'min' => 1
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($group = Acl::_getGroup($group_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL группа с указанным идентификатором не существует.');
			return;
		}

		$db = Acl::_getDb();

		$db->transaction();

		#Удаление объекта из таблицы acl_groups
		if($db->delete($db->buildDelete('acl_groups',array('group_id' => $group_id))) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении объекта из acl_groups.');
			$db->rollback(); return;
		}

		#Удаление объекта из таблицы acl_user_groups
		if($db->delete($db->buildDelete('acl_user_groups',array('group_id' => $group_id))) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении объекта из acl_user_groups.');
			$db->rollback(); return;
		}


		#Удаление объекта из таблицы acl_object_groups
		if($db->delete($db->buildDelete('acl_object_groups',array('group_id' => $group_id))) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении объекта из acl_object_groups.');
			$db->rollback(); return;
		}


		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>ACL_OBJECT_GROUP,
			#Идентификатор объекта
			'object_id'=>$group_id,
			#Описание действия
			'desc'=>'Удалена ACL группа ID:'.$group_id.' ('.$group['name'].')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array('delete'=>array(
				'group_id'=>$group_id,
				'name' => $group['name']
			))
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------

		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/last_update', time());
			XCache::_set('acl/actual', false);
		}
		#Если XCache не активен, тогда обновляем во всех источниках данных ACL статус у пользователей статус need_update
		else{
			if(Acl::_dbSetNeedUpdate() === false){
				$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка установки пользователям признака принудительного обновления прав доступа.');
				$db->rollback(); return;
			}
		}

		$db->commit();

		#Обновление массива объектов
		Acl::_dbLoadGroups();
		Acl::_dbLoadObjects();

		#Возвращаем массив всех ACL объектов и ACL групп
		$this->chunk->setData(
			array(
				'groups' => Acl::_getAllGroups(),
				'objects' => Acl::_getAllObjects()
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.group.delete








	/*
	 * Добавление новой организации * Добавление новой организации * Добавление новой организации * Добавление новой организации * Добавление новой организации
	 */
	case 'acl.company.add':

		$company_name = Request::_getGPCValue('company_name','pg','');
		$company_lock = (Request::_getGPCValue('company_lock','pg', 0) == 1 ? 1 : 0);
		$form = new Validator(array(
			array(
				'name' => 'Наименование организации',
				'value' => $company_name,
				'type' => 'text',
				'required' => true
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		$db = Acl::_getDb();

		$db->transaction();


		$sql = $db->buildInsert(
			'acl_companies',
			array(
				'name' => $company_name,
				'lock' => $company_lock,
				'deleted' => 0
			)
		);

		if(($company_id = $db->insert($sql,'acl_companies_company_id_seq')) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при добавлении организации в базу данных.');
			$db->rollback(); return;
		}

		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>ACL_OBJECT_COMPANY,
			#Идентификатор объекта
			'object_id'=>$company_id,
			#Описание действия
			'desc'=>'Создана организация ID:'.$company_id.' ('.$company_name.')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array('create'=>array(
				'company_id'=>$company_id,
				'name' => $company_name,
				'lock' => $company_lock
			))
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------


		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/actual', false);
		}

		$db->commit();

		#Обновление массива организаций
		Acl::_dbLoadCompanies();

		#Возвращаем массив всех организаций
		$this->chunk->setData(
			array(
				'companies' => Acl::_getAllСompanies(),
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.company.add









	/*
	 * Изменение организации * Изменение организации * Изменение организации * Изменение организации * Изменение организации * Изменение организации
	 */
	case 'acl.company.update':

		$company_id = Request::_getGPCValue('company_id','pg','');
		$company_name = Request::_getGPCValue('company_name','pg','');
		$company_lock = (Request::_getGPCValue('company_lock','pg', 0) == 1 ? 1 : 0);
		$form = new Validator(array(
			array(
				'name' => 'Идентификатор организации',
				'type' => 'uint',
				'value' => $company_id,
				'required' => true,
				'min' => 1
			),
			array(
				'name' => 'Наименование организации',
				'value' => $company_name,
				'type' => 'text',
				'required' => true
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($company = Acl::_getCompany($company_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Организация с указанным идентификатором не существует.');
			return;
		}


		$db = Acl::_getDb();

		$db->transaction();

		$sql = $db->buildUpdate(
			'acl_companies',
			array(
				'name' => $company_name,
				'lock' => $company_lock
			),
			array(
				'company_id' => $company_id
			)
		);

		if($db->update($sql) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при обновлении данных организации.');
			$db->rollback(); return;
		}

		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>ACL_OBJECT_COMPANY,
			#Идентификатор объекта
			'object_id'=>$company_id,
			#Описание действия
			'desc'=>'Изменена организация ID:'.$company_id.' ('.$company_name.')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array(
				'prev'=>array(
					'company_id'=>$company_id,
					'name' => $company['name'],
					'lock' => $company['lock']
				),
				'new'=>array(
					'company_id'=>$company_id,
					'name' => $company_name,
					'lock' => $company_lock
				)
			)
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------


		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/actual', false);
		}

		$db->commit();

		#Обновление массива организаций
		Acl::_dbLoadCompanies();

		#Возвращаем массив всех организаций
		$this->chunk->setData(
			array(
				'companies' => Acl::_getAllСompanies(),
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.company.update






	/*
	 * Удаление организаци * Удаление организаци * Удаление организаци * Удаление организаци * Удаление организаци * Удаление организаци * Удаление организаци * Удаление организаци
	 */
	case 'acl.company.delete':

		$company_id = Request::_getGPCValue('company_id','pg','');

		$form = new Validator(array(
			array(
				'name' => 'Идентификатор организации',
				'type' => 'uint',
				'value' => $company_id,
				'required' => true,
				'min' => 1
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($company = Acl::_getCompany($company_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Организация с указанным идентификатором не существует.');
			return;
		}

		$db = Acl::_getDb();
		$db->transaction();

		#Удаление объекта из таблицы acl_companies
		if($db->delete($db->buildDelete('acl_companies',array('company_id' => $company_id))) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении организации из acl_companies.');
			$db->rollback(); return;
		}

		#Удаление объекта из таблицы acl_access
		if($db->delete($db->buildDelete('acl_access',array('company_id' => $company_id))) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении организации из acl_access.');
			$db->rollback(); return;
		}



		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>ACL_OBJECT_COMPANY,
			#Идентификатор объекта
			'object_id'=>$company_id,
			#Описание действия
			'desc'=>'Удалена организация ID:'.$company_id.' ('.$company['name'],
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array(
				'delete'=>array(
					'company_id'=>$company_id,
					'name' => $company['name']
				)
			)
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------


		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/last_update', time());
			XCache::_set('acl/actual', false);
		}
		#Если XCache не активен, тогда обновляем во всех источниках данных ACL статус у пользователей статус need_update
		else{
			if(Acl::_dbSetNeedUpdate() === false){
				$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка установки пользователям признака принудительного обновления прав доступа.');
				$db->rollback(); return;
			}
		}

		$db->commit();

		#Обновление массива объектов
		Acl::_dbLoadCompanies();

		#Возвращаем массив всех ACL объектов
		$this->chunk->setData(
			array(
				'companies' => Acl::_getAllCompanies()
			)
		);

		#Если текущая организация пользователя - это удаляемая организация,
		#Добавляем редирект для обновления текущей страницы
		if($company_id == Acl::_userAclAttribute('company_id')){
			$this->chunk->setLocation('refresh');
		}

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.company.delete











	/*
	 * Запрос списка объектов ACL в контейнере роли * Запрос списка объектов ACL в контейнере роли * Запрос списка объектов ACL в контейнере роли * Запрос списка объектов ACL в контейнере роли
	 */
	case 'acl.role.objects':

		$role_id = Request::_getGPCValue('role_id','pg','');
		$form = new Validator(array(
			array(
				'name' => 'Идентификатор роли',
				'type' => 'uint',
				'value' => $role_id,
				'required' => true,
				'min' => 1
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($role = Acl::_getObject($role_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL объект с указанным идентификатором не существует.');
			return;
		}

		if( ($role['type'] !== ACL_OBJECT_ROLE){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL объект с указанным идентификатором не является контейнером роли.');
			return;
		}

		#Возвращаем массив идентификаторов всех ACL объектов, включенных в роль
		$this->chunk->setData(
			array(
				'childs' => $role['childs']
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.role.objects














	/*
	 * Добавление объекта ACL в контейнер роли * Добавление объекта ACL в контейнер роли * Добавление объекта ACL в контейнер роли * Добавление объекта ACL в контейнер роли
	 */
	case 'acl.role.insert':

		$role_id = Request::_getGPCValue('role_id','pg','');
		$child_id = Request::_getGPCValue('child_id','pg','');

		$form = new Validator(array(
			array(
				'name' => 'Идентификатор роли',
				'type' => 'uint',
				'value' => $role_id,
				'required' => true,
				'min' => 1
			),
			array(
				'name' => 'Идентификатор дочернего элемента',
				'type' => 'uint',
				'value' => $child_id,
				'required' => true,
				'min' => 1
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($role = Acl::_getObject($role_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL объект роли с указанным идентификатором не существует.');
			return;
		}

		if( ($role['type'] !== ACL_OBJECT_ROLE){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL объект с указанным идентификатором не является контейнером роли.');
			return;
		}

		if( ($child = Acl::_getObject($child_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Дочерний ACL объект с указанным идентификатором не существует.');
			return;
		}

		if(Acl::_haveCollision($role_id, $child_id)){
			$this->chunk->addMsgError('Ошибка выполнения', 'Добавляемый в роль объект является родительским объектом для данной роли.');
			return;
		}

		$db = Acl::_getDb();

		$db->transaction();

		$db->prepare('SELECT count(*) FROM ? WHERE ?=? AND ?=? LIMIT 1');
		$db->bindField('acl_roles');
		$db->bindField('object_id');
		$db->bindNum($role_id);
		$db->bindField('child_id');
		$db->bindNum($child_id);
		if(($unique_test = $db->result()) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при проверке наличия дочернего объекта в контейнере роли.');
			$db->rollback(); return;
		}
		
		if($unique_test > 0){
			$this->chunk->addMsgError('Ошибка выполнения', 'Дочерний объект уже присутствует в контейнере роли.');
			$db->rollback(); return;
		}


		$sql = $db->buildInsert(
			'acl_roles',
			array(
				'object_id' => $role_id,
				'child_id' => $child_id
			)
		);

		if($db->insert($sql) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при добавлении объекта в контейнер роли.');
			$db->rollback(); return;
		}

		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>ACL_OBJECT_ROLE,
			#Идентификатор объекта
			'object_id'=>$role_id,
			#Описание действия
			'desc'=>'В роль ('.$role['name'].') добавлен ACL объект ID:'.$child_id.' ('.$child['name'].')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array(
				'add'=>array(
					'object_id'=>$child_id,
					'type' => $child['type'],
					'name' => $child['name']
				)
			)
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------


		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/last_update', time());
			XCache::_set('acl/actual', false);
		}
		#Если XCache не активен, тогда обновляем во всех источниках данных ACL статус у пользователей статус need_update
		else{
			if(Acl::_dbSetNeedUpdate() === false){
				$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка установки пользователям признака принудительного обновления прав доступа.');
				$db->rollback(); return;
			}
		}

		$db->commit();

		#Обновление массива объектов
		Acl::_dbLoadObjects();

		#Возвращаем массив всех ACL объектов
		$this->chunk->setData(
			array(
				'childs' => Acl::_getObjectChilds($role_id)
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.role.insert











	/*
	 * Удаление объекта ACL из контейнера роли * Удаление объекта ACL из контейнера роли * Удаление объекта ACL из контейнера роли * Удаление объекта ACL из контейнера роли
	 */
	case 'acl.role.delete':

		$role_id = Request::_getGPCValue('role_id','pg','');
		$child_id = Request::_getGPCValue('child_id','pg','');

		$form = new Validator(array(
			array(
				'name' => 'Идентификатор роли',
				'type' => 'uint',
				'value' => $role_id,
				'required' => true,
				'min' => 1
			),
			array(
				'name' => 'Идентификатор дочернего элемента',
				'type' => 'uint',
				'value' => $child_id,
				'required' => true,
				'min' => 1
			)
		));

		#Проверка полей формы
		if(!$form->validate()){
			$e = $form->getErrors();
			foreach($e as $m) $this->chunk->addMsgError($m['name'],$m['text'],null);
			return;
		}

		if( ($role = Acl::_getObject($role_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL объект роли с указанным идентификатором не существует.');
			return;
		}

		if( ($role['type'] !== ACL_OBJECT_ROLE){
			$this->chunk->addMsgError('Ошибка выполнения', 'ACL объект с указанным идентификатором не является контейнером роли.');
			return;
		}

		if( ($child = Acl::_getObject($child_id)) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Дочерний ACL объект с указанным идентификатором не существует.');
			return;
		}

		$db = Acl::_getDb();

		$db->transaction();


		$sql = $db->buildDelete(
			'acl_roles',
			array(
				'object_id' => $role_id,
				'child_id' => $child_id
			)
		);

		if($db->delete($sql) === false){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка при удалении объекта из контейнера роли.');
			$db->rollback(); return;
		}

		#Журналирование действия -------------------------------------------------------------------
		if(!Acl::_logAction(array(
			#Текущее действие
			'action'=>$this->action,
			#Тип Объекта, над которым производится действие, типы объектов определены в массиве acl_objects класса ACL ($this->acl->acl_objects)
			'object_type'=>ACL_OBJECT_ROLE,
			#Идентификатор объекта
			'object_id'=>$role_id,
			#Описание действия
			'desc'=>'Из роли ('.$role['name'].') удален ACL объект ID:'.$child_id.' ('.$child['name'].')',
			#Изменяемые значения в рамках выполняемого действия
			'value'=>array(
				'delete'=>array(
					'object_id'=>$child_id,
					'type' => $child['type'],
					'name' => $child['name']
				)
			)
		))){
			$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка протоколирования действия.');
			$db->rollback(); return;
		}
		#----------------------------------------------------------------------------------------


		#Если XCache активен
		if(XCache::_isEnabled()){
			XCache::_set('acl/last_update', time());
			XCache::_set('acl/actual', false);
		}
		#Если XCache не активен, тогда обновляем во всех источниках данных ACL статус у пользователей статус need_update
		else{
			if(Acl::_dbSetNeedUpdate() === false){
				$this->chunk->addMsgError('Ошибка выполнения', 'Ошибка установки пользователям признака принудительного обновления прав доступа.');
				$db->rollback(); return;
			}
		}

		$db->commit();

		#Обновление массива объектов
		Acl::_dbLoadObjects();

		#Возвращаем массив всех ACL объектов
		$this->chunk->setData(
			array(
				'childs' => Acl::_getObjectChilds($role_id)
			)
		);

		#Выполнено успешно
		$this->chunk->addMsgSuccess('Выполнено успешно', 'Операция выполнена успешно');

	break; #END: acl.role.delete




























	default:
		$this->chunk->setData();
		$this->chunk->addMsgError('Ошибка выполнения', 'Не найден обработчик AJAX действия '.$this->action.' в '.Request::_getRequestPath());
}

?>