<?php

include_once "conf.php";
include_once "core.php";

if (isset($_REQUEST['id'])) {
	if (preg_match("/^".$_CONF['idregexp']."$/", $_REQUEST['id']) === 0) 
		die("invalid redir id");
	$path = Metadata_GetBasePath($_REQUEST['id']);
}

if (isset($_POST['passphrase'])) {

	session_start();
	$_SESSION['authed'] = false;

	$hashed = hash("sha256", $_POST['passphrase']);
	$jsonauth = json_decode(file_get_contents($_CONF['authfilepath']), true);
	foreach ($jsonauth['passphrases'] as $hash) 
		if ($hash == $hashed) 
			$_SESSION['authed'] = true;
	unset($jsonauth);

	session_write_close();
	
	if (!isset($path)) 
		$path = "/";
	header("Location: index.php?path=".$path, true, 303);
	exit();
}

?><!DOCTYPE html>
<html>
	<head lang="fr">
		<meta charset="utf-8">
		<title>Authentification</title>
		<link rel="stylesheet" href="main.css" type="text/css">
	</head>
	<body>
		<header>
			<?php if (isset($path)) { ?>
			<span id="path">
				<a id="homebutton" href="index.php"></a>
				<a href="index.php?path=<?=$path?>"><?=$path?></a>
			</span>
			<?php } ?>
		</header>
		<form id="auth-form" method="post">
			<div class="inputline"><span> <span><label for="passphrase">Passphrase :</label></span> <span><input type="password" name="passphrase" autofocus/></span> </span></div>
			<span class="buttons"> <button type="submit">Authentifier</button> </span>
		</form>
	</body>
</html>
