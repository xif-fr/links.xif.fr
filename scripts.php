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

		file_put_contents("ytdl_order.json", json_encode($output));
		echo "ytdl.py order file for ".count($output)." items has been written to ./ytdl_order.json";
		exit(0);
	}

	if ($_GET['script'] == 'ytdl_exec') {

		header('Content-Type: text/plain');
		set_time_limit(0);

		$proc = popen("python -u ytdl.py 2>&1", 'r');
		while (!feof($proc)) {
			echo fread($proc, 32);
			@ flush();
		}
		pclose($proc);
		exit(0);
	}

	if ($_GET['script'] == 'orphans') {

		header('Content-Type: text/plain');

		$files = scandir($_CONF['metadata-path']);
		foreach ($files as $file) {
			if (preg_match('/([a-f0-9]+)\.json/', $file, $matches)) {
				$id = $matches[1];
				if ($id == "00000000000000000000000000000000") 
					continue;
				$data = Metadata_Get($id);
				if (!is_file( Metadata_JSONPath($data['parent']) )) {
					echo $id." is orphaned :\n";
					var_dump($data);
					echo "-------------\n";
				}
			}
		}

		exit(0);
	}

	if ($_GET['script'] == 'savedweb') {

		header('Content-Type: text/plain');

		if (!isset($_REQUEST['id']) || preg_match("/^".$_CONF['idregexp']."$/", $_REQUEST['id']) === 0) 
			die("invalid id");
		$_ITEM = Metadata_Get($_REQUEST['id']);
		if ($_ITEM['type'] != 'web') 
			die("not web item");
		if ($_ITEM['item']['saved'] !== false) 
			die("already saved");

		$stor_path = $_CONF['altstor-path'].'/'.$_REQUEST['pathname'];
		if (!is_dir($stor_path))
			die("'".$stor_path."' is not a dir or does not exist");
		$stor_path = realpath($stor_path);
		if (strpos($stor_path, $_CONF['altstor-path'].'/') !== 0)
			die("can't go outside of the alt storage");

		$dest_path = $_CONF['files-path'].Metadata_GetBasePath($_REQUEST['id']).".save";

		symlink($stor_path, $dest_path);
		echo "linked ".$stor_path." -> ".$dest_path;

		Metadata_SetInfoKey($_REQUEST['id'], 'saved', true);

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
			<form>
				<input name="script" type="hidden" value="ytdl_exec"/>
				<button type="submit">Execute ytdl.py</button>
			</form>
		</fieldset>
		<br/>
		<fieldset>
			<legend>Saved website</legend>
			<form>
				<input name="script" type="hidden" value="savedweb"/>
				ID : <input name="id" type="text" placeholder="00000000000000000000000000000000"/><br/>
				Path : <tt><?=$_CONF['altstor-path']?>/<input name="pathname" type="text"/></tt><br/>
				<button type="submit">Do it</button>
			</form>
		</fieldset>
		<br/>
		<fieldset>
			<legend>Misc</legend>
			<form>
				<input name="script" type="hidden" value="orphans"/>
				<button type="submit">List orphans</button>
			</form>
		</fieldset>
	</body>
</html>
