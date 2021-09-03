<?php



	header('Content-Type:text/html; charset=utf-8');



	error_reporting(E_ALL );



	

	define('DB_HOST', 'localhost');

	define('DB_USER', 'root');

	define('DB_PWD', 'yuanzhi');

	define('DB_NAME', 'wxsc1');

	define('DB_PREFIX', 'wemall_');


define('DB_DSN'  , 'mysql:host=localhost;dbname=test;charset=UTF8');

	



	$conn = mysqli_connect(DB_HOST, DB_USER, DB_PWD,DB_NAME) or die('数据库链接失败：'.mysqli_error());



	







	



	//mysqli_query('SET NAMES UTF8') or die('字符集错误：'.mysqli_error());



?>
