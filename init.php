<?php
ini_set('display_errors',1);
error_reporting( -1 );

define('BUCK_ID_LEN', 3);

class ItemStatus {
	const Incoming = 1;
	const Accepted = 2;
	const WorkingOn = 3; 
}

define('BUCK_STATUS_INCOMING', 1);
define('BUCK_STATUS_ACCEPTED',)

require_once('elasticsearch.php');
$es = new ElasticSearch();
$es->index = 'buck';