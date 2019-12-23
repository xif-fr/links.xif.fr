<?php

include_once "conf.php";
include_once "core.php";

if (isset($_COOKIE[session_name()])) {
	session_start();
	if (!isset($_SESSION['authed'])) 
		$_SESSION['authed'] = false;
}
$_AUTHED = (isset($_SESSION) && $_SESSION['authed'] == true);
if (!$_AUTHED)
	header("Location: auth.php");

?><!DOCTYPE html>
<html>
	<head lang="fr">
		<meta charset="utf-8">
		<title><?=$_CONF['titlebase']?> : Youtube channels</title>
		<link rel="stylesheet" href="main.css" type="text/css">
	</head>
	<body showlinks="true">
		<header>
			<span id="path">
				<a id="homebutton" href="index.php"></a>
			</span>
		</header>
		<div class="root"><ul>
		<?php
		$_CHANNELS = array();
		$cb = function ($id, $depth) {
			global $_CONF, $_CHANNELS;
			$_ITEM = Metadata_Get($id);
			$_ITEM['id'] = $id;
			if ($_ITEM['type'] == 'yt') {
				$metapath = $_CONF['files-path'].Metadata_GetBasePath($id)."/metadata.json";
				if (file_exists($metapath)) {
					$_META = json_decode(file_get_contents($metapath), true);
					$channel = $_META['info']['channelTitle'];
					$_ITEM['item']['pubdate'] = explode('T',$_META['info']['publishedAt'])[0];
					$_ITEM['item']['saved'] = true;
				} else {
					$_ITEM['item']['pubdate'] = "?";
					$channel = "??";
					$_ITEM['item']['saved'] = false;
				}
				if (!isset($_CHANNELS[$channel])) {
					$_CHANNELS[$channel] = array();
				}
				$_CHANNELS[$channel][] = $_ITEM;
			}
		};
		Metadata_TreeWalk($_CONF['rootid'], null, $cb, 0);

		uasort($_CHANNELS, function ($a, $b) { return -(count($a) <=> count($b)); });

		foreach ($_CHANNELS as $channel => $_VIDEOS) {
		?><li>
			<p class="descr"><?=htmlspecialchars($channel)?></p>
			<ul> <?php

			usort($_VIDEOS, function ($a, $b) { return strnatcmp($b['item']['pubdate'], $a['item']['pubdate']); });

			foreach ($_VIDEOS as $_ITEM) {
				$_INFO = $_ITEM['item'];
				?>
				<li class="item item-yt" id="<?=$_ITEM['id']?>">
				<a class="icon" href="<?=htmlspecialchars($_INFO['url'])?>" target="_blank"></a>
				<p class="descr"><?=$_INFO['pubdate']?> | <?=htmlspecialchars($_INFO['descr'])?></p>
				<?php if ($_INFO['saved']) {
					?> <a class="save" href="video.php?id=<?=$_ITEM['id']?>"></a> <?php
				}
			}
			?> </ul> <?php
		}
		?>
		</ul></div>
	</body>
</html>
