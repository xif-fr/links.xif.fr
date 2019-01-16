<?php

include_once "conf.php";
include_once "core.php";

if (isset($_COOKIE[session_name()])) {
	session_start();
	if (!isset($_SESSION['authed'])) 
		$_SESSION['authed'] = false;
}
$_AUTHED = (isset($_SESSION) && $_SESSION['authed'] == true);
$jsonauth = json_decode(file_get_contents($_CONF['authfilepath']), true);
if (!$jsonauth['public-edit'] && !$_AUTHED) 
	die("no permissions");
unset($jsonauth);

if (isset($_GET['script'])) {

	// Generates order file for ytdl.py script. This file
	//  contains all items that are `yt` type, with their
	//  'files/…' path for video and comments storage.
	// The script will then be executed manually, and will
	//  check if the video is already downloaded. If it
	//  is not, `youtube-dl` utility will download it.
	//  Comments will allways be (re)retrieved using 
	//   the Google API.
	if ($_GET['script'] == 'ytdl') {

		header('Content-Type: text/plain');

		$output = array();
		$cb = function ($id, $depth) use (&$output) {
			$data = Metadata_Get($id);
			if ($data['type'] == 'yt') {
				if (preg_match("/^https?:\/\/(www.youtube.com\/watch\?v=|youtu.be\/)([a-zA-Z0-9_-]{11})/", $data['item']['url'], $_)) {
					$output[] = array(
						'name' => $data['item']['name'],
						'vid' => $_[2],
						'path' => Metadata_GetBasePath($id), 
					);
				} else {
					echo "warning : can't extract vid form '".$data['item']['name']."', url ".$data['item']['url']."\n";
				}
			}
		};
		Metadata_TreeWalk($_CONF['rootid'], null, $cb, 0);

		file_put_contents("ytld_order.json", json_encode($output));
		echo "ytdl.py order file for ".count($output)." items has been written to ./ytld_order.json";
		exit(0);

	}

}

?><!DOCTYPE html>
<html>
	<head lang="fr">
		<meta charset="utf-8">
		<title>Scripts</title>
	</head>
	<body>
		<h1>Scripts</h1>
		<fieldset>
			<legend>YouTube Downloads</legend>
			<form>
				<input name="script" type="hidden" value="ytdl"/>
				Order file for <code>ytdl.py</code> : <button type="submit">Generate</button>
			</form>
		</fieldset>
	</body>
</html>
