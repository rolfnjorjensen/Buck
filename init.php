<?php
ini_set('display_errors',1);
error_reporting( -1 );
date_default_timezone_set('Europe/Copenhagen');

define('BUCK_ID_LEN', 3);

define('BUCK_MAX_SIZE', 50000); //size of queries

class ItemStatus {
	const Incoming = 1;
	const Accepted = 2;
	const WorkingOn = 3; 
}

require_once('elasticsearch.php');
$es = new ElasticSearch();
$es->index = 'buck';