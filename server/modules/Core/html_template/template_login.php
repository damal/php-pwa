<div id='top-message'></div>
<div id='page-container'>
	
	
	<div id='header'>
		<div class='container'>
			<div class='title'>
				<h1><?=$this->htmlContainer('>project_name',Config::getOption('Core/main','project_name','ТК-Система'));?></h1>
				<span class='desc'><?=$this->htmlContainer('>project_desc',Config::getOption('Core/main','project_desc','Топливный сервис'));?></span>
			</div>
			<a href='http://www.compas-card.ru/' target='_blank'>
				<img class='compas-logo' src='/client/img/0.gif' border=0 />
			</a>
		</div>
	</div>
	
	
	
	<div id='page-content'>
		
		<div class='login-dialog'>
			<div class='form-container'>
				<h2>Вход</h2>
				<form id='login-form' action='/login' method='post' _autovalidate>
					<div class='input-field'>
						<input placeholder='Логин' class='ui-form text' style='width:100%' id='username' type='text' name='username' autocomplete='off' requiredlength=5 maxlength=20 required />
					</div>
					<div class='input-field'>
						<input placeholder='Пароль' class='ui-form text' style='width:100%' id='password' type='password' name='password' requiredlength=5 maxlength=20 seteye required />
					</div>
					<div class='input-field'>
						<input class='ui-form submit' type='submit' value='Войти' disabled />
						<div id='screen-keyboard-switch' title='Экранная клавиатура'></div>
					</div>
				</form>
			</div>
		</div>
	
	</div>
	
	<div id='footer'>
		<div class='container'>
			<div class='copy'>&copy; 2012 <a href='http://sintez-r.ru/'>ООО &laquo;Синтез&raquo;</a></div>
			<div id='tools-switch' title="Инструменты"></div>
		</div>
	</div>
	
</div>
<div id='tools'>
	<div id='tabs-container'></div>
	<div id='minimized-bar'></div>
</div>