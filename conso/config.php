<?php

require 'vendor/autoload.php';
$host = "192.168.155.15";    /* Host name */
$user = "crypteo";         /* User */
$password = "";         /* Password */
$dbname = "azaerp";   /* Database name */

$pdo = new PDO('mysql:host='.$host.';dbname='.$dbname, $user);
$pdo->exec("SET NAMES 'utf8'");

$db = db_Driver::Create('MyDb', array(
	 'dsn'=>'mysql:host='.$host.';dbname='.$dbname,
	 'username'=>$user,
	 'password'=>$password,
	 'dbname' => $dbname,
	 'options' => []
));
$db->execute("SET NAMES 'utf8'");
// http://192.168.155.15/_db/html/