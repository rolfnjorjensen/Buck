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
		
		if ( !empty( $data['data'] ) ) {
			$data = json_decode( $data['data'], true ); //decode json into an associative array
		}
		
		if ( strpos( $_SERVER['REQUEST_URI'], '/' ) === 0 ) {
			$reqUri = substr( $_SERVER['REQUEST_URI'], 1, strlen( $_SERVER['REQUEST_URI'] )-2 ); //strip "/" from beginning and end
			$reqUri = explode( '/', $reqUri );
			array_shift( $reqUri ); //first element is always "api"
			$reqUri[0] = strtolower($reqUri[0]); //first uri fragment is always lowercase
			$reqUri[1] = strtoupper($reqUri[1]); //second is always UPPER
		}
		
		$request = array( 'method' => $reqMethod, 'request' => $reqUri, 'data' => $data );
		
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
	private static function buckets( $r ) {
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
						$aBucket['userHandles'] = array();
						foreach ( $r['data']['userHandles'] as $userId ) { //check them all
							/**
							 * @todo use "count" instead of "query"
							*/
							$user = self::$es->query( 'member', array('q'=>'handle:'.$userId )); //if they exists
							if ( $user->hits->total === 1 ) { //and if they do
								$aBucket['userHandles'][] = $user->hits->hits[0]->_source->handle; //store them
							}
						}
					}	
				}
				$bucketId=self::nextId();
				$aBucket['bucketId'] = $bucketId;
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
							$bucket['userHandles'] = array();
							foreach ( $newBucket['userHandles'] as $userId ) { //check them all
								/**
								 * @todo use "count" instead of "query"
								*/
								$user = self::$es->query( 'member', array('q'=>'handle:'.$userId )); //if they exists
								if ( $user->hits->total === 1 ) { //and if they do
									$bucket['userHandles'][] = $user->hits->hits[0]->_source->handle; //store them
								}
							}
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
				self::$es->delete('bucket',$r['request'][1]);
				return 1;
			} else {
				return -1;
			}
			break;
		}
	}
	
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
	 * handles all item logic
	*/
	private static function items( $r ) {
		
	}
} echo BuckServer::init();