<?php
	define('db_host', 'localhost');
	define('db_user', 'summaries');
	define('db_password', 'HXIUI39kasbb6Bji');
	define('db_name', 'summariesDB');
	
	//faz a ligação
	
	$connection = @mysqli_connect (db_host, db_user, db_password, db_name) or die ('Não foi possivel ligar á database: '. mysqli_connect_error() );
	
	//charset
	
	mysqli_set_charset($connection, 'utf8');
	
?>