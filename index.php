<?php
require_once('init.php');

class BuckServer {
	private static $es; //elastic search wrapper

	/**
	 * Some authentication
	*/
	private static function authenticate() {
		//if session/cookie is empty or not in sync with eachother quit
		if ( 
			empty($_SESSION['userHandle']) || 
			empty($_COOKIE['userHandle']) ||
			($_SESSION['userHandle'] != $_COOKIE['userHandle']) 
		) {
			$_COOKIE['userHandle'] = null;
			unset($_COOKIE['userHandle']);
			header("HTTP/1.0 401 Unauthorized");
			exit;
		}
	}
	/**
	 * process the incoming REST request
	*/
	public static function init() {
		self::authenticate();
		
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
			case 'members':
				$result = self::members( $request );
			break;
		}
		return json_encode($result);
	}
	
	/**
	 * handles all member logic (search/getall only)
	*/
	protected static function members( $r ) {
		switch ( $r['method'] ) {
			case 'get':
				if ( !empty( $_GET['q'] ) ) {
					$q = $_GET['q'];
					$_members = self::$es->query( 'member', array('q'=>'*'.$q.'*','size'=>BUCK_MAX_SIZE ));
					if ( $_members->hits->total > 0 ) {
						$members = array();
						foreach ( $_members->hits->hits as $member ) {
							$members[] = array( 'id' => $member->_source->handle, 'name' => $member->_source->name ); //jQuery.tokenInput needs this format
						}
						return $members;
					}
				} else {
					$_members = self::$es->query( 'member', array('q'=>'_type:member','size'=>BUCK_MAX_SIZE ));
					if ( $_members->hits->total > 0 ) {
						$members = array();
						foreach ( $_members->hits->hits as $member ) {
							$members[] = $member->_source;
						}
						return $members;
					}
				}
			break;
		}
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
					$aBucket['bucketId'] = $bucketId = self::nextId('bucket');
					/**
					 * @todo store json file somewhere
					*/
					$result = self::$es->add('bucket',$bucketId,json_encode( $aBucket ));
					if ( $result !== NULL && $result->ok == true ) {
						return $result->_id;
					}
				}
				return -1;
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
			//get bucket(s)
			case 'get':
				if ( !empty($r['request'][1]) ) {
					$bucket = self::$es->query( 'bucket', array('q'=>'bucketId:'.$r['request'][1] ));
					if ( $bucket->hits->total === 1 ) {
						return $bucket->hits->hits[0]->_source;
					}
				} else {
					$_buckets = self::$es->query( 'bucket', array('q'=>'_type:bucket','size'=>BUCK_MAX_SIZE ));
					if ( $_buckets->hits->total > 0 ) {
						$buckets = array();
						foreach ( $_buckets->hits->hits as $bucket ) {
							$buckets[] = $bucket->_source;
						}
						return $buckets;
					}
				}
				return -1;
			break;
			//delete bucket
			case 'delete':
				if ( !empty($r['request'][1]) ) { 
					$result = self::$es->delete('bucket',$r['request'][1]);
					if ( $result->ok == true ) {
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
				if ( !empty($r['data']['name']) && !empty($r['data']['bucketId']) ) {
					$anItem['name'] = $r['data']['name'];
					$anItem['submitter'] = $_SESSION['userHandle'] ;
					if ( !empty( $r['data']['desc'] ) ) {
						$anItem['desc'] = $r['data']['desc'];
					}
					if ( !empty( $r['data']['hardDeadline'] ) ) { //if there is a hardDeadline supplied						
						if ( !is_numeric( $r['data']['hardDeadline'] ) ) { //and it's not numeric
							$r['data']['hardDeadline'] = strtotime( $r['data']['hardDeadline'] ); //try to get the timestamp
							if ( $r['data']['hardDeadline'] !== false ) { //if successful
								$anItem['hardDeadline'] = $r['data']['hardDeadline']; //store it
							}
						} else {
							$anItem['hardDeadline'] = $r['data']['hardDeadline']; //if it's numeric(unix timestamp), simply store it 
						}
					}
					if ( !empty( $r['data']['bucketId'] ) ) {
						if ( self::verifyBucketId( $r['data']['bucketId'] ) ) {
							$anItem['bucketId'] = $r['data']['bucketId'];
						} else {
							return -2;
						}
					}
					$anItem['created'] = time();
					$anItem['decay'] = time()+(ItemDecay::Incoming*86400);
					$anItem['status'] = ItemStatus::Incoming;
					$anItem['itemId'] = $itemId = self::nextId('item');
					/**
					 * @todo store json file somewhere
					*/
					$result = self::$es->add('item',$itemId,json_encode( $anItem ));
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
							switch ( (int)$item['status'] ) {
								case 3:
									$item['decay'] = time()+(ItemDecay::WorkingOn*86400);
								break;
								case 2:
									$item['decay'] = time()+(ItemDecay::Accepted*86400);
								break;
								case 1:
									$item['decay'] = time()+(ItemDecay::Incoming*86400);
								break;
							}
						}
						if ( !empty($newItem['delayDecay']) ) {
							$item['decay'] += ((int)$newItem['delayDecay'])*86400;
						}
						if ( !empty($newItem['hardDeadline']) ) {
							if ( !is_numeric( $newItem['hardDeadline'] ) ) {
								$newItem['hardDeadline'] = strtotime( $newItem['hardDeadline'] );
							}
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
			//get item or get all items for the current user
			case 'get':
				if ( !empty($r['request'][1]) ) {
					$item = self::$es->query( 'item', array('q'=>'itemId:'.$r['request'][1] ));
					if ( $item->hits->total === 1 ) {
						$item = $item->hits->hits[0]->_source;
						/**
						 * @todo make this 8601 creation into a function
						*/
						$item->created8601 = date('c', $item->created);
						if ( !empty( $item->hardDeadline ) ) {
							$item->hardDeadline8601 = date('c', $item->hardDeadline);
						}
						if ( !empty( $item->decay ) ) {
							$item->decay8601 = date( 'c', $item->decay );
						}
						return $item;
					}
				} else {
					//get all buckets the user is in
					$_buckets = self::$es->query( 'bucket', array('q'=>'_type:bucket AND memberHandles:'.$_SESSION['userHandle'] ,'size'=>BUCK_MAX_SIZE ));
					if ( $_buckets->hits->total > 0 ) {
						$buckets = array();
						foreach ( $_buckets->hits->hits as $bucket ) {
							$buckets[] = $bucket->_source;
						}
					}
					//get all items from those buckets
					$items = array();
					foreach ( $buckets as $bucket ) {
						$_items = self::$es->query( 'item', array('q'=>'_type:item AND bucketId:'.$bucket->bucketId,'size'=>BUCK_MAX_SIZE ));
						if ( $_items->hits->total > 0 ) {
							foreach ( $_items->hits->hits as $item ) {
								$item = $item->_source;
								/**
								 * @todo make this 8601 creation into a function
								*/
								$item->created8601 = date('c', $item->created);
								if ( !empty( $item->hardDeadline ) ) {
									$item->hardDeadline8601 = date('c', $item->hardDeadline);
								}
								if ( !empty( $item->decay ) ) {
									$item->decay8601 = date( 'c', $item->decay );
								}
								$items[] = $item;
							}
						}
					}	
					return $items;
				}
				return -1;
			break;
			//delete item
			case 'delete':
				if ( !empty($r['request'][1]) ) { 
					$result = self::$es->delete('item',$r['request'][1]);
					if ( $result->ok == true ) {
						return 1;
					}
				}
				return -1;
			break;
		}
	}
} echo BuckServer::init();