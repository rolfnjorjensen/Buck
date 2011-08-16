<?php
require_once('init.php');

echo '<pre>';
require_once('secret.php');

require_once('Zend/Loader/Autoloader.php');
Zend_Loader_Autoloader::getInstance();
$client = Zend_Gdata_ClientLogin::getHttpClient($email, $password, Zend_Gdata_Gapps::AUTH_SERVICE_NAME);
$service = new Zend_Gdata_Gapps($client, $domain);

$feed = $service->retrieveAllUsers();

foreach ($feed as $user) {
    echo "  * " . $user->login->username . ' (' . $user->name->givenName .
        ' ' . $user->name->familyName . ")\n";
	var_dump($es->add('member',$user->login->username,
		json_encode(
			array(
				'handle' => $user->login->username,
				'name' => $user->name->givenName.' '.$user->name->familyName,
				'level' => 1
			)
		)
	));
}
