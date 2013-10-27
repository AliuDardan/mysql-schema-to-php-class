<?php

define('DATABASE_HOST', '127.0.0.1');
define('DATABASE_USER', 'root');
define('DATABASE_PASS', '');
define('DATABASE_NAME', 'schema2class');

require_once 'mysql-schema-to-php-class.php';

$mysql_schema_to_php_class = new MySQLSchemaToPHPClass(DATABASE_HOST, DATABASE_USER, DATABASE_PASS, DATABASE_NAME);
$mysql_schema_to_php_class->execute();
?>