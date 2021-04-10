<?php
require_once 'libs/autoload.php';

use \jamesiarmes\PhpEws\Client;

$config['realm'] = "iCal Auth";
$config['host'] = "mail.company.com";
$config['logdir'] = "logs"; // if empty, logging goes to stdout
$config['cachedir'] = "cache";
$config['accesstoken'] = "6fc7f7656261b47b9253d83a34a267913c8c40f9dd3eb5980297f678"; // SHA2 - 56 chars!
$config['cat_blacklist'] = array("Privat");
$config['timerange_start'] = "-90 days";
$config['timerange_end'] = "+180 days";
$config['version'] = Client::VERSION_2016;
