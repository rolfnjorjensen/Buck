<?php

require_once('init.php');

$_items = $es->query( 'item', array('q'=>'_type:item','size'=>BUCK_MAX_SIZE ));
if ( $_items->hits->total > 0 ) {
	foreach ( $_items->hits->hits as $item ) {
		$item = $item->_source;
		if ( empty( $item->decay ) ) {
			$item->decay = time()+(ItemDecay::Incoming*86400);
		}
		$result = $es->add('item',$item->itemId,json_encode($item));
		var_dump( $result );
	}
}