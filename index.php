<?php

require_once('init.php');
class BuckServer {
	private static $es;
	/**
	 * process the incoming REST request
	*/
	public static function init() {
		global $es;
		self::$es = $es;
		
		$reqMethod = strtolower( $_SERVER['REQUEST_METHOD'] );
		$data = file_get_contents('php://input');
		if ( !empty( $data ) ) {
			$data = json_decode( $data, true ); //decode json into an associative array
		}
		
		if ( strpos( $_SERVER['REQUEST_URI'], '/' ) === 0 ) {
			$reqUri = substr( $_SERVER['REQUEST_URI'], 1, strlen( $_SERVER['REQUEST_URI'] )-2 ); //strip "/" from beginning and end
			$reqUri = explode( '/', $reqUri );
			array_shift( $reqUri ); //first element is always "api"
			$reqUri[0] = strtolower($reqUri[0]); //first uri fragment is always lowercase
			if ( !empty( $reqUri[1] ) ) { //if there is a second one
				$reqUri[1] = strtoupper($reqUri[1]); //second is always UPPER
			}
		}
		
		$request = array( 'method' => $reqMethod, 'request' => $reqUri, 'data' => $data );
		
		$result = array();
		
		switch ( strtolower($reqUri[0]) ) {
			case 'buckets':
				$result = self::buckets( $request );
			break;
			case 'items':
				$result = self::items( $request );
			break;
		}
		return json_encode($result);
	}
	
	/**
	 * handles all bucket logic
	*/
	protected static function buckets( $r ) {
		switch ( $r['method'] ) {
			//new bucket
			case 'post':
				$aBucket = array();
				if ( !empty($r['data']['name']) ) { //name is required
					$aBucket['name'] = $r['data']['name'];
					if ( !empty($r['data']['desc']) ) { //desc is optional
						$aBucket['desc'] = $r['data']['desc'];
					}
					if ( !empty($r['data']['memberHandles']) && is_array($r['data']['memberHandles']) ) { //if has members
						$aBucket['memberHandles'] = self::verifyMembers( $r['data']['memberHandles'] );
					}	
				}
				$aBucket['bucketId'] = $bucketId = self::nextId('bucket');
				$aBucket = json_encode( $aBucket );
				/**
				 * @todo store json file somewhere
				*/
				$result = self::$es->add('bucket',$bucketId,$aBucket);
				if ( $result !== NULL && $result->ok == true ) {
					return $result->_id;
				} else {
					return -1;
				}
			break;
			//edit bucket
			case 'put':
				if ( !empty($r['request'][1]) ) {
					$bucket = self::$es->query( 'bucket', array('q'=>'bucketId:'.$r['request'][1] ));
					if ( $bucket->hits->total !== 1 ) {
						return -1;
					} else {
						$bucket = (array)$bucket->hits->hits[0]->_source;
						$newBucket = $r['data'];
						if ( !empty($newBucket['name']) ) {
							$bucket['name'] = $newBucket['name'];
						}
						if ( !empty($newBucket['desc']) ) {
							$bucket['desc'] = $newBucket['desc'];
						}
						if ( !empty($newBucket['memberHandles']) && is_array($newBucket['memberHandles']) ) {
							$bucket['memberHandles'] = self::verifyMembers( $newBucket['memberHandles'] );
						}
						$result = self::$es->add('bucket',$r['request'][1],json_encode($bucket));
						if ( $result !== NULL && $result->ok == true ) {
							return $result->_id;
						} else {
							return -2;
						}
					}
				}
			break;
			//get bucket
			case 'get':
				if ( !empty($r['request'][1]) ) {
					$bucket = self::$es->query( 'bucket', array('q'=>'bucketId:'.$r['request'][1] ));
					if ( $bucket->hits->total === 1 ) {
						return $bucket->hits->hits[0]->_source;
					}
				}
				return -1;
			break;
			//delete bucket
			case 'delete':
				if ( !empty($r['request'][1]) ) { 
					$result = self::$es->delete('bucket',$r['request'][1]);
					if ( $result['ok'] == true ) {
						return 1;
					}
				}
				return -1;
			break;
		}
	}
	
	/**
	 * get a random BUCK_ID_LEN long id comprised of alpha characters which is not already in use
	*/
	private static function nextId( $type = 'item' ) {	
		$possibleChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$maxLen = strlen( $possibleChars );
		/**
		 * @todo use built in index generation
		*/
		do {
			$randomId = '';
			for ( $i = BUCK_ID_LEN; $i > 0; --$i ) {
				$randomId .= $possibleChars[rand(0,$maxLen-1)];
			}
			$elem = self::$es->query( $type,
				array(
					'q' => $type.'Id:'.$randomId
				)
			);
			
			if ( $elem->hits->total === 0 ) {
				$idUsed = false;
			} else {
				$idUsed = true;
			}
		} while ( $idUsed );
		return $randomId;
	}
	
	/**
	 * verify an array of member handles
	*/
	private static function verifyMembers( $members ) {
		$validMembers = array();
		foreach ( $members as $memberHandle ) { //check them all
			$member = self::$es->count( 'member', array('q'=>'handle:'.$memberHandle )); //if they exists
			if ( $member->count === 1 ) { //and if they do
				$validMembers[] = $memberHandle; //store them
			}
		}
		return $validMembers;
	}

	
	/**
	 * verify an array of member handles
	*/
	private static function verifyBucketId( $bucketId ) {
		$bucket = self::$es->count( 'bucket', array('q'=>'bucketId:'.$bucketId )); //if they exists
		if ( $bucket->count === 1 ) { //and if they do
			return true;
		}
		return false;
	}
	
	/**
	 * handles all item logic
	*/
	protected static function items( $r ) {
			switch ( $r['method'] ) {
			//new item
			case 'post':
				$anItem = array();
				/**
				 * @todo submitter should be handled through google SSO, not from the json input
				*/
				var_dump( $r['data'] );
				if ( !empty($r['data']['name']) && !empty($r['data']['submitter']) && !empty($r['data']['bucketId']) ) {
					$anItem['name'] = $r['data']['name'];
					$anItem['submitter'] = self::verifyMembers( array($r['data']['submitter']) );
					if ( empty( $anItem['submitter'][0] ) ) { //submitter invalid
						return -1;
					}
					$anItem['submitter'] = $anItem['submitter'][0];
					if ( !empty( $r['data']['desc'] ) ) {
						$anItem['desc'] = $r['data']['desc'];
					}
					if ( !empty( $r['data']['hardDeadline'] ) ) {
						$anItem['hardDeadline'] = $r['data']['hardDeadline'];
					}
					if ( !empty( $r['data']['bucketId'] ) ) {
						if ( self::verifyBucketId( $r['data']['bucketId'] ) ) {
							$anItem['bucketId'] = $r['data']['bucketId'];
						} else {
							return -2;
						}
					}
					$anItem['created'] = time();
					$anItem['status'] = ItemStatus::Incoming;
					$anItem['itemId'] = $itemId = self::nextId('item');
					$anItem = json_encode( $anItem );
					/**
					 * @todo store json file somewhere
					*/
					echo '$result = self::$es->add(\'item\','.$itemId.','.$anItem.');'."\n";
					$result = self::$es->add('item',$itemId,$anItem);
					var_dump( $result );
					if ( $result !== NULL && $result->ok == true ) {
						return $result->_id;
					}
				}
				return -3;
			break;
			//edit item
			case 'put':
				if ( !empty($r['request'][1]) ) {
					$item = self::$es->query( 'item', array('q'=>'itemId:'.$r['request'][1] ));
					if ( $item->hits->total !== 1 ) {
						return -1;
					} else {
						$item = (array)$item->hits->hits[0]->_source;
						$newItem = $r['data'];
						if ( !empty($newItem['name']) ) {
							$item['name'] = $newItem['name'];
						}
						if ( !empty($newItem['desc']) ) {
							$item['desc'] = $newItem['desc'];
						}
						if ( !empty($newItem['status']) ) {
							$item['status'] = $newItem['status'];
						}
						if ( !empty($newItem['hardDeadline']) ) {
							$item['hardDeadline'] = $newItem['hardDeadline'];
						}
						$result = self::$es->add('item',$r['request'][1],json_encode($item));
						if ( $result !== NULL && $result->ok == true ) {
							return $result->_id;
						} else {
							return -1;
						}
					}
				}
			break;
			//get bucket
			case 'get':
				if ( !empty($r['request'][1]) ) {
					$item = self::$es->query( 'item', array('q'=>'itemId:'.$r['request'][1] ));
					if ( $item->hits->total === 1 ) {
						return $item->hits->hits[0]->_source;
					}
				}
				return -1;
			break;
			//delete bucket
			case 'delete':
				if ( !empty($r['request'][1]) ) { 
					$result = self::$es->delete('item',$r['request'][1]);
					if ( $result['ok'] == true ) {
						return 1;
					}
				}
				return -1;
			break;
		}
	}
} echo BuckServer::init();