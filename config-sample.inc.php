<?php
require_once 'libs/autoload.php';

use \jamesiarmes\PhpEws\Client;

$config['realm'] = "iCal Auth";
$config['host'] = "mail.company.com";
$config['logdir'] = "logs";
$config['cachedir'] = "cache";
$config['cat_blacklist'] = array("Privat");
$config['timerange_start'] = "-90 days";
$config['timerange_end'] = "+180 days";
$config['version'] = Client::VERSION_2016;
