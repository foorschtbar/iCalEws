<?php
	
	#DEBUG
	#ini_set('display_errors', 1);
	#ini_set('display_startup_errors', 1);
	#error_reporting(E_ALL);
	#DEBUG
	
	spl_autoload_register(function($class_name) {
		$file = __DIR__.'/'.str_replace('\\', '/', $class_name).'.php';
		if (is_readable($file)) {
			include_once $file;
		}
	});
?>