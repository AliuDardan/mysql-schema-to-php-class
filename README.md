MySQLSchemaToPHPClass
==============
*Version 1.0*
--------------

This is a simple class that can be used when you to create PHP classes from Database(MySQL) Scheema ex. for a ORM. Best suitable for a MVC pattern architecture.

Example.php
==============
You can change the private attribute **$output_folder** in *mysql-schema-to-php-class.php* to change the output destination folder.
Setup in apache and execute it:

	define('DATABASE_HOST', '127.0.0.1');
	define('DATABASE_USER', 'root');
	define('DATABASE_PASS', '');
	define('DATABASE_NAME', 'schema2class');

	require_once 'mysql-schema-to-php-class.php';

	$mysql_schema_to_php_class = new MySQLSchemaToPHPClass(DATABASE_HOST, DATABASE_USER, DATABASE_PASS, DATABASE_NAME);
	$mysql_schema_to_php_class->execute();