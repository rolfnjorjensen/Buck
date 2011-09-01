<?php
require_once('init.php');
$es->drop();
$es->create(); //create index

$es->map('member','{
	"member": {
		"properties": {
			"handle": {
				"type": "string", 
				"store": "yes"
			},
			"name": {
				"type": "string",
				"store": "yes"
			},
			"level": {
				"type": "integer",
				"store": "yes"
			}
		}
	}
}');

$es->map('item','{
	"item": {
		"properties": {
			"itemId": {
				"type": "string", 
				"store": "yes"
			},
			"name": {
				"type": "string",
				"store": "yes"
			},
			"desc": {
				"type": "string",
				"store": "yes"
			},
			"created": {
				"type": "integer",
				"store": "yes"
			},
			"decay": {
				"type": "integer",
				"store": "yes"
			},
			"status": {
				"type": "integer",
				"store": "yes"
			},
			"hardDeadline": {
				"type": "integer",
				"store": "yes"
			},
			"submitter": {
				"type": "string",
				"store": "yes"
			},
			"bucketId": {
				"type": "string",
				"store": "yes"
			}
		}
	}
}');

$es->map('bucket','{
	"bucket": {
		"properties": {
			"bucketId": {
				"type": "string", 
				"store": "yes"
			},
			"name": {
				"type": "string",
				"store": "yes"
			},
			"desc": {
				"type": "string",
				"store": "yes"
			},
			"memberHandles": {
				"type": "string",
				"store": "yes"
			}
		}
	}
}');
require_once('getmembers.php');
