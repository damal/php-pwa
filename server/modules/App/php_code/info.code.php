<?php
$this->chunk->addHtml('MyContainer001','<h1>Welcome to Info page of App module!</h1>');
$this->chunk->addHtml('MyContainer001','<h2><i>Ups, no widgets :)</i></h2>');
$this->chunk->addHtml('MyContainer001','<br><br><a href="/main">Go to Main page of module Core, path = /main</a>');

$this->chunk->disableWidgets();
?>