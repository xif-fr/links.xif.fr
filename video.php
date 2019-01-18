<?php

include_once "conf.php";
include_once "core.php";

if (!isset($_GET['id'])) 
	die("no id provided");
if (preg_match("/^".$_CONF['idregexp']."$/", $_GET['id']) === 0) 
	die("invalid folder id");
$_ID = $_GET['id'];
$_ITEM = Metadata_Get($_ID);
if ($_ITEM['type'] != 'yt') 
	die("'".$_ID."' is not a video");
$_TITLE = $_CONF['titlebase']." : ".$_ITEM['item']['descr'];
$_PATH = Metadata_GetBasePath($_ID);
$metapath = $_CONF['files-path'].$_PATH."/metadata.json";
if (!file_exists($metapath))
	die("video not saved ".$_PATH."/metadata.json");
$_META = json_decode(file_get_contents($metapath), true);

?><!DOCTYPE html>
<html>
	<head lang="fr">
		<meta charset="utf-8">
		<title><?=htmlspecialchars($_TITLE)?></title>
		<link rel="stylesheet" href="main.css" type="text/css">
		<style type="text/css">
			video {
				display: inline-block;
				max-width: 90%;
			}
			#videocont {
				padding-top: 30px;
				width: 100%;
				text-align: center;
			}
			#metacont {
				margin: 30px;
				font-family: sans-serif;
			}
			h1 { font-size: 20pt; }
			pre#descr {
				font-size: 10pt;
				font-family: sans-serif;
				padding-left: 10px;
			}
		</style>
	</head>
	<body>
		<header>
			<span id="path">
				<a id="homebutton" href="index.php"></a>
				<?php
					$comps = explode("/", $_PATH);
					$path = "/";
					foreach ($comps as $name) {
						if ($name == "") continue;
						$path = $path.$name."/";
						?> &gt; <a href="index.php?path=<?=$path?>"><?=$name?></a> <?php
					}
				?>
			</span>
		</header>
		<div id="videocont">
			<video controls src="files/<?=$_PATH?>/video.mp4" poster="<?=htmlspecialchars($_META['info']['thumbnails']['maxres']['url'])?>"></video>
		</div>
		<div id="metacont">
			<h1><?=htmlspecialchars($_META['info']['title'])?></h1>
			<p><?=htmlspecialchars($_META['info']['channelTitle'])?> - <?=explode('T',$_META['info']['publishedAt'])[0]?></p>
			<pre id="descr"><?=htmlspecialchars($_META['info']['description'])?></pre>
			<hr/>
			<pre><?php var_dump($_META['comments']); ?></pre>
		</div>
	</body>
</html>
