<?php
/**
 * Main Menu Widget
 */


if(!$this->ajax || Acl::_userNeedUpdate()){

	if(!XCache::_exists('widget/menu/map')||XCache::_get('widget/menu/actual')==false){

		$map = Map::_getMap(
			array(
				'user' => true
			),
			array(
				'id',
				'parent_id'=>'parent',
				'path',
				'title',
				'childs'
			)
		);

		XCache::_set('widget/menu/map', $map);
		XCache::_set('widget/menu/actual', true);

	}else{
		$map = XCache::_get('widget/menu/map');
	}

	$this->chunk->addActionMod(array(
		'type' =>'element',
		'selector' =>'#main_menu_div',
		'set'=>array('html'=>'<pre>'.print_r($map,true).'</pre>')
	));

	if(!$this->ajax) $this->chunk->addHtml('#MyContainer001','<div id="main_menu_div"></div>');

}
?>