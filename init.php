<?php
ini_set('display_errors',1);
error_reporting( -1 );

define('BUCK_ID_LEN', 3);

require_once('elasticsearch.php');
$es = new ElasticSearch();
$es->index = 'buck';