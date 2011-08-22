<?php
/* Copyright (c) 2009 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Author: Eric Bidelman <e.bidelman>
*/
error_reporting( -1 );
ini_set('display_errors',0);

function defineDL() {
	function dl($string){return false;}
}

if ( !function_exists('dl') ) {
	defineDL();
}

session_start();

// OAuth/OpenID libraries and utility functions.
require_once 'common.inc.php';

// Load the necessary Zend Gdata classes.
require_once 'Zend/Loader.php';
Zend_Loader::loadClass('Zend_Gdata_HttpClient');
Zend_Loader::loadClass('Zend_Gdata_Docs');
Zend_Loader::loadClass('Zend_Gdata_Spreadsheets');

// Setup OAuth consumer with our "credentials"
$REALM = 'tradeshift.com';
$CONSUMER_KEY = 'buck.tradeshift.com';
$CONSUMER_SECRET = 'Anonymous';
$consumer = new OAuthConsumer($CONSUMER_KEY, $CONSUMER_SECRET);
$sig_method = $SIG_METHODS['HMAC-SHA1'];
$scopes = array(
  'http://docs.google.com/feeds/',
  'http://spreadsheets.google.com/feeds/',
  'http://www-opensocial.googleusercontent.com/api/people/'
);

$openid_params = array(
  'openid.ns'                => 'http://specs.openid.net/auth/2.0',
  'openid.claimed_id'        => 'http://specs.openid.net/auth/2.0/identifier_select',
  'openid.identity'          => 'http://specs.openid.net/auth/2.0/identifier_select',
  'openid.return_to'         => "http://{$CONSUMER_KEY}{$_SERVER['PHP_SELF']}",
  'openid.realm'             => "http://{$CONSUMER_KEY}",
  'openid.mode'              => @$_REQUEST['openid_mode'],
  'openid.ns.ui'             => 'http://specs.openid.net/extensions/ui/1.0',
  'openid.ns.ext1'           => 'http://openid.net/srv/ax/1.0',
  'openid.ext1.mode'         => 'fetch_request',
  'openid.ext1.type.email'   => 'http://axschema.org/contact/email',
  'openid.ext1.type.first'   => 'http://axschema.org/namePerson/first',
  'openid.ext1.type.last'    => 'http://axschema.org/namePerson/last',
  'openid.ext1.type.country' => 'http://axschema.org/contact/country/home',
  'openid.ext1.type.lang'    => 'http://axschema.org/pref/language',
  'openid.ext1.required'     => 'email,first,last,country,lang',
  'openid.ns.oauth'          => 'http://specs.openid.net/extensions/oauth/1.0',
  'openid.oauth.consumer'    => $CONSUMER_KEY,
  'openid.oauth.scope'       => implode(' ', $scopes)
);

$openid_ext = array(
  'openid.ns.ext1'           => 'http://openid.net/srv/ax/1.0',
  'openid.ext1.mode'         => 'fetch_request',
  'openid.ext1.type.email'   => 'http://axschema.org/contact/email',
  'openid.ext1.type.first'   => 'http://axschema.org/namePerson/first',
  'openid.ext1.type.last'    => 'http://axschema.org/namePerson/last',
  'openid.ext1.type.country' => 'http://axschema.org/contact/country/home',
  'openid.ext1.type.lang'    => 'http://axschema.org/pref/language',
  'openid.ext1.required'     => 'email,first,last,country,lang',
  'openid.ns.oauth'          => 'http://specs.openid.net/extensions/oauth/1.0',
  'openid.oauth.consumer'    => $CONSUMER_KEY,
  'openid.oauth.scope'       => implode(' ', $scopes),
  'openid.ui.icon'           => 'true'
);


if (isset($_REQUEST['popup']) && !isset($_SESSION['redirect_to'])) {
  $query_params = '';
  if($_POST) {
    $kv = array();
    foreach ($_POST as $key => $value) {
      $kv[] = "$key=$value";
    }
    $query_params = join('&', $kv);
  } else {
    $query_params = substr($_SERVER['QUERY_STRING'], strlen('popup=true') + 1);
  }

  $_SESSION['redirect_to'] = "http://{$CONSUMER_KEY}{$_SERVER['PHP_SELF']}?{$query_params}";
  echo '<script type = "text/javascript">window.close();</script>';
  exit;
} else if (isset($_SESSION['redirect_to'])) {
  $redirect = $_SESSION['redirect_to'];
  unset($_SESSION['redirect_to']);
  header('Location: ' .$redirect);
}

$request_token = @$_REQUEST['openid_ext2_request_token'];
if ($request_token) {
  $data = array();
  $httpClient = new Zend_Gdata_HttpClient();
  $access_token = getAccessToken($request_token);
}

switch(@$_REQUEST['openid_mode']) {
  case 'checkid_setup':
  case 'checkid_immediate':
    $identifier = $_REQUEST['openid_identifier'];
    if ($identifier) {
      $fetcher = Auth_Yadis_Yadis::getHTTPFetcher();
      list($normalized_identifier, $endpoints) =
          Auth_OpenID_discover($identifier, $fetcher);

      if (!$endpoints) {
        debug('No OpenID endpoint found.');
      }

      $uri = '';
      foreach ($openid_params as $key => $param) {
        $uri .= $key . '=' . urlencode($param) . '&';
      }
      header('Location: ' . $endpoints[0]->server_url . '?' . rtrim($uri, '&'));
    } else {
      debug('No OpenID endpoint found.');
    }
    break;
  case 'cancel':
    debug('Sign-in was cancelled.');
    break;
  case 'associate':
    // TODO
    break;
}

/**
 * Upgrades an OAuth request token to an access token.
 *
 * @param string $request_token_str An authorized OAuth request token
 * @return string The access token
 */
function getAccessToken($request_token_str) {
  global $consumer, $sig_method;

  $token = new OAuthToken($request_token_str, NULL);

  $token_endpoint = 'https://www.google.com/accounts/OAuthGetAccessToken';
  $request = OAuthRequest::from_consumer_and_token($consumer, $token, 'GET',
                                                   $token_endpoint);
  $request->sign_request($sig_method, $consumer, $token);

  $response = send_signed_request($request->get_normalized_http_method(),
                                  $token_endpoint, $request->to_header(), NULL,
                                  false);

  // Parse out oauth_token (access token) and oauth_token_secret
  preg_match('/oauth_token=(.*)&oauth_token_secret=(.*)/', $response, $matches);
  $access_token = new OAuthToken(urldecode($matches[1]),
                                 urldecode($matches[2]));

  return $access_token;
}
?>

<html>
<head>
<title>Google Hybrid Protocol Demo (OpenID + OAuth)</title>
<script src="http://code.jquery.com/jquery-latest.min.js"></script>
<script type="text/javascript" src="/js/popuplib.js"></script>
<script type="text/javascript">
  var upgradeToken = function() {
    window.location = '<?php echo $_SESSION['redirect_to'] ?>';
  };
  var extensions = <?php echo json_encode($openid_ext); ?>;
  var googleOpener = popupManager.createPopupOpener({
    'realm' : '<?php echo $openid_params['openid.realm'] ?>',
    'opEndpoint' : 'https://www.google.com/accounts/o8/ud',
    'returnToUrl' : '<?php echo $openid_params['openid.return_to'] . '?popup=true' ?>',
    'onCloseHandler' : upgradeToken,
    'shouldEncodeUrls' : true,
    'extensions' : extensions
  });
  $(document).ready(function () {
    jQuery('#LoginWithGoogleLink').click(function() {
      googleOpener.popup(450, 500);
      return false;
    });
  });
</script>
</head>
<body>
<div>
  Sign in with a
  <a href="<?php echo $_SERVER['PHP_SELF'] . '?openid_mode=checkid_setup&openid_identifier=google.com/accounts/o8/id' ?>" id="LoginWithGoogleLink"><img height="16" width="16" align="absmiddle" style="margin-right: 3px;" src="/img/gfavicon.gif" border="0"/><span class="google"><span>G</span><span>o</span><span>o</span><span>g</span><span>l</span><span>e</span> Account</span></a> (popup)
</div>

<?php if(@$_REQUEST['openid_mode'] === 'id_res'): ?>
  <p>
  Welcome: <?php echo "{$_REQUEST['openid_ext1_value_first']} {$_REQUEST['openid_ext1_value_last']} - {$_REQUEST['openid_ext1_value_email']}" ?><br>
  </p>
  <?php
  	$_SESSION['userEmail'] = $_REQUEST['openid_ext1_value_email'];
	$_SESSION['userHandle'] = explode('@',$_SESSION['userEmail']);
	if ( $_SESSION['userHandle'][1] !== $REALM ) {
		echo 'Not in realm: '.$REALM.', please try logging in with a different account';
		$_REQUEST = array();
		session_destroy();
		exit;
	}
	$_SESSION['userHandle'] = $_SESSION['userHandle'][0];
	setcookie("userHandle", $_SESSION['userHandle'], time()+1209600);//2 weeks
  ?>
  <script>
	window.location.href = '/';
  </script>
<?php endif; ?>
</div>
</body>
</html>
