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
		$data = array();
		
		switch ( $reqMethod ) {
			case 'get':
				$data = $_GET;
			break;
			case 'post':
				$data = $_POST;
			break;
			case 'put':
				parse_str( file_get_contents('php://input'), $put_vars ); //nasty php tricks
				$data = $put_vars;
			break;
			default:
			break;
		}
		
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
					if ( !empty($r['data']['userHandles']) && is_array($r['data']['userHandles']) ) { //if has users
						$aBucket['userHandles'] = self::verifyMembers( $r['data']['userHandles'] );
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
						if ( !empty($newBucket['userHandles']) && is_array($newBucket['userHandles']) ) {
							$bucket['userHandles'] = self::verifyMembers( $newBucket['userHandles'] );
						}
						$result = self::$es->add('bucket',$r['request'][1],json_encode($bucket));
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
	 * verify an array of user handles
	*/
	private static function verifyMembers( $members ) {
		$validMembers = array();
		foreach ( $members as $userHandle ) { //check them all
			$user = self::$es->count( 'member', array('q'=>'handle:'.$userHandle )); //if they exists
			if ( $user->count === 1 ) { //and if they do
				$validMembers[] = $userHandle; //store them
			}
		}
		return $validMembers;
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
				if ( !empty($r['data']['name']) && !empty($r['data']['submitter']) ) {
					$anItem['name'] = $r['data']['name'];
					$anItem['submitter'] = self::verifyMembers( array($r['data']['submitter']) );
					if ( empty( $anItem['submitter'] ) ) { //submitter invalid
						return -1;
					}
					if ( !empty( $r['data']['desc'] ) ) {
						$anItem['desc'] = $r['data']['desc'];
					}
					if ( !empty( $r['data']['hardDeadline'] ) ) {
						$anItem['hardDeadline'] = $r['data']['hardDeadline'];
					}
					$anItem['created'] = time();
					$anItem['status'] = ItemStatus::Incoming;
					$anItem['itemId'] = $itemId = self::nextId('item');
					$anItem = json_encode( $anItem );
					/**
					 * @todo store json file somewhere
					*/
					$result = self::$es->add('item',$itemId,$anItem);
					if ( $result !== NULL && $result->ok == true ) {
						return $result->_id;
					}
				}
				return -1;
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