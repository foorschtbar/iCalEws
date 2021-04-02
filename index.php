<?php

require_once 'libs/autoload.php';
require_once 'config.inc.php';

$icalews = new foorschtbar\iCalEws\Main($config);

$icalews->httpauth();

if (isset($_GET['debug'])) {

	header('Content-type: text/plain; charset=utf-8');

	echo "Welcome to iCalEws - Debug\n\n";
	echo "Commands:\n";
	echo "debug -> this page;\n";
	echo "update -> force update;\n";
	echo "verbose -> extends debug with verbose data. works only with debug and update\n\n";
	echo "Log:\n";

	$icalews->debug = true;
	if (isset($_GET['verbose'])) {
		$icalews->verbose = true;
	}
}

if (isset($_GET['wife'])) {
	$icalews->wifemode = true;
}

if (isset($_GET['update'])) {

	$icalews->getitems();
	$icalews->getevents();
	$icalews->cachesave();
	echo "ReturnStatus:NOERROR";
} else {

	$icalews->cacheload();
	$icalews->icalbuild();
	$icalews->icalout();
}
