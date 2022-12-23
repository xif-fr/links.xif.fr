<?php

if (isset($_COOKIE[session_name()])) {
	session_start();
	if (!isset($_SESSION['authed'])) 
		$_SESSION['authed'] = false;
}

$_AUTHED = (isset($_SESSION) && $_SESSION['authed'] == true);
$jsonauth = json_decode(file_get_contents($_CONF['authfilepath']), true);
$_PUBLICEDIT = $jsonauth['public-edit'];
unset($jsonauth);

if ($_CONF['private-repository'])
	if (!$_AUTHED)
		header("Location: auth.php");

?>