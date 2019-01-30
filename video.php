<?php

include_once "conf.php";
include_once "core.php";

date_default_timezone_set('Zulu');

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
				font-size: 11pt;
				font-family: sans-serif;
				padding-left: 10px;
			}
			#ytcomments {
				font-size: 11pt;
				padding-top: 10px;
			}
			div.comment {
				margin: 5px;
				padding: 5px;
				padding-left: 15px;
				background-color: rgba(190, 190, 255, 0.2);
				border-radius: 4px;
			}
			p.comment-author {
				margin: 0px;
				padding: 0px;
				margin-bottom: 5px;
			}
			p.comment-text {
				margin: 0px;
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
		<?php
			$posterurl = "";
			if (isset($_META['info']['thumbnails'])) {
				if (isset($_META['info']['thumbnails']['maxres'])) 
					$posterurl = $_META['info']['thumbnails']['maxres']['url'];
				else
					$posterurl = array_values($_META['info']['thumbnails'])[0]['url'];
			}
			$title = htmlspecialchars($_META['info']['title']);
			$channel = htmlspecialchars($_META['info']['channelTitle']);
			$pubdate = explode('T',$_META['info']['publishedAt'])[0];
			$descr = htmlspecialchars($_META['info']['description']);
		?>
		<div id="videocont">
			<video controls src="files/<?=$_PATH?>/video.mp4" poster="<?=$posterurl?>"></video>
		</div>
		<div id="metacont">
			<h1><?=$title?></h1>
			<p><?=$channel?> - <?=$pubdate?></p>
			<pre id="descr"><?=$descr?></pre>
			<hr/>
			<div id="ytcomments"><?php
				foreach ($_META['comments'] as $comment) {
					$date = explode('T',$comment['date'])[0];
					?><div class="comment">
						<p class="comment-author"><?=$comment['author']?> - <?=$date?></p>
						<p class="comment-text"><?=$comment['html']?></p>
						<?php
						uasort($comment['replies'], function ($a, $b) {
							return strtotime($a['date']) - strtotime($b['date']);
						});
						foreach ($comment['replies'] as $reply) {
							$date = explode('T',$reply['date'])[0];
							?><div class="comment">
								<p class="comment-author"><?=$reply['author']?> - <?=$date?></p>
								<p class="comment-text"><?=$reply['html']?></p>
							</div><?php
						}
						?>
					</div><?php
				}
			?></div>
		</div>
	</body>
</html>
