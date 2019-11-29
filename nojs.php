<?php

include_once "conf.php";
include_once "core.php";

if (isset($_GET['id'])) {
	if (preg_match("/^".$_CONF['idregexp']."$/", $_GET['id']) === 0) 
		die("invalid folder id");
	$_ID = $_GET['id'];
	$_GET['path'] = Metadata_GetBasePath($_ID);
} else if (isset($_GET['path'])) {
	if (preg_match("/^\/(".$_CONF['nameregexp']."\/)*$/u", $_GET['path']) === 0) 
		die("invalid folder path");
	$_ID = Metadata_Path2ID($_GET['path']);
} else {
	$_ID = $_CONF['rootid'];
}
$_FOLDER = Metadata_Get($_ID);
if ($_FOLDER['type'] != 'folder') 
	die("'".$_ID."' is not a folder");
$_TITLE = $_CONF['titlebase'];
if ($_ID != $_CONF['rootid']) 
	$_TITLE .= " : ".$_FOLDER['item']['descr'];

if (isset($_COOKIE[session_name()])) {
	session_start();
	if (!isset($_SESSION['authed'])) 
		$_SESSION['authed'] = false;
}

$_AUTHED = (isset($_SESSION) && $_SESSION['authed'] == true);

if ($_CONF['private-repository'])
	if (!$_AUTHED)
		header("Location: auth.php");

if (isset($_GET['fnojs']))
	$folder_anchor_page = "nojs.php?fnojs&";
else
	$folder_anchor_page = "index.php?";

?><!DOCTYPE html>
<html>
	<head lang="fr">
		<meta charset="utf-8">
		<title><?=htmlspecialchars($_TITLE)?></title>
		<link rel="stylesheet" href="main.css" type="text/css">
	</head>
	<body showlinks="true">
		<header>
			<?php if (isset($_GET['path'])) { ?>
			<span id="path">
				<a id="homebutton" href="<?=$folder_anchor_page?>"></a>
				<?php
					$comps = explode("/", $_GET['path']);
					$path = "/";
					foreach ($comps as $name) {
						if ($name == "") continue;
						$path = $path.$name."/";
						?> &gt; <a href="?path=<?=$path?>"><?=$name?></a> <?php
					}
				?>
			</span>
			<?php } ?>
		</header>
		<div class="root" id="<?=$_ID?>"><ul>
<?php
$regularCB = function ($id, $depth) {
	global $_CONF, $folder_anchor_page;
	$_ITEM = Metadata_Get($id);
	$_INFO = $_ITEM['item'];
	$is_alias = ($_ITEM['type'] == 'alias');
	if ($is_alias) {
		$descr = $_INFO['descr'];
		$id = $_INFO['orig'];
		$_ITEM = Metadata_Get($id);
		if (!is_null($_INFO['descr'])) 
			$_ITEM['item']['descr'] = $_INFO['descr'];
		$_INFO = $_ITEM['item'];
	}
	if (!$_ITEM['public'] && !$_AUTHED) 
		return;
	switch ($_ITEM['type']) {
		case 'folder':
			?>
<li class="item item-folder" id="<?=$id?>">
<span class="icon"></span>
<img class="alias" src="rsrc/alias.png"/>
<p class="descr"><?=htmlspecialchars($_INFO['descr'])?></p>
<a class="folder-anchor" href="<?=$folder_anchor_page?>path=<?=Metadata_GetBasePath($id)?>"></a>
<span class="folder-name"><?=$_INFO['name']?></span>
			<?php
			break;
		case 'yt':
			?>
<li class="item item-yt" id="<?=$id?>">
<a class="icon" href="<?=htmlspecialchars($_INFO['url'])?>" target="_blank"></a>
<?php if ($is_alias) { ?> <img class="alias" src="rsrc/alias.png"/> <?php } ?>
<p class="descr"><?=htmlspecialchars($_INFO['descr'])?></p>
<?php
$basepath = $_CONF['files-path'].Metadata_GetBasePath($id);
if (file_exists($basepath."/metadata.json")) {
	?> <a class="save" href="video.php?id=<?=$id?>"></a> <?php
}
			break;
		case 'doc':
			$localurl = $_CONF['files-path'].Metadata_GetBasePath($id).".".$_INFO['ext'];
			$addclass = 'item-doc';
			if (in_array($_INFO['ext'], ['png','jpg','jpeg','gif','tiff','bmp']))
				$addclass = 'item-img';
			if (in_array($_INFO['ext'], ['mp3','m4a','wav','aiff','flac','ogg', 'opus']))
				$addclass = 'item-audio';
			if (in_array($_INFO['ext'], ['mp4','mov','webm','avi']))
				$addclass = 'item-video';
			if (in_array($_INFO['ext'], ['zip','tar','gz','xz','tgz']))
				$addclass = 'item-archive';
			if (in_array($_INFO['ext'], ['djvu','epub']))
				$addclass = 'item-ebook';
			if ($_INFO['ext'] == 'pdf') 
				$addclass = 'item-pdf';
			if (in_array($_INFO['ext'], ['txt','rtf']))
				$addclass = 'item-txt';
			if (in_array($_INFO['ext'], ['html','htm','xhtml']))
				$addclass = 'item-html';
			?>
<li class="item item-doc <?=$addclass?>" id="<?=$id?>">
<a class="icon" href="<?=$localurl?>" target="_blank"></a>
<?php if ($is_alias) { ?> <img class="alias" src="rsrc/alias.png"/> <?php } ?>
<p class="descr"><?=htmlspecialchars($_INFO['descr'])?></p>
<?php if (!is_null($_INFO['url'])) { ?>
	<a class="orig" href="<?=htmlspecialchars($_INFO['url'])?>" target="_blank"><?=htmlspecialchars($_INFO['url'])?></a>
<?php }
			break;
		case 'web':
			?>
<li class="item item-web <?php if (strpos($_INFO['url'], "wikipedia.org") !== false) echo "item-wiki"; ?>" id="<?=$id?>">
<a class="icon" href="<?=htmlspecialchars($_INFO['url'])?>" target="_blank"></a>
<?php if ($is_alias) { ?> <img class="alias" src="rsrc/alias.png"/> <?php } ?>
<p class="descr"><?=htmlspecialchars($_INFO['descr'])?></p>
<?php if ($_INFO['saved']) {
	preg_match('/.+\/([^\/]*)$/', $_INFO['url'], $matches);
	$localurl = $_CONF['files-path'].Metadata_GetBasePath($id).".save/".$matches[1];
	?><a class="save" href="<?=htmlspecialchars($localurl)?>"></a><?php
} ?>
<a class="orig" href="<?=htmlspecialchars($_INFO['url'])?>" target="_blank"><?=htmlspecialchars($_INFO['url'])?></a>
			<?php
			break;
		case 'txt':
			?>
<li class="item item-txt" id="<?=$id?>">
<span class="icon"></span>
<?php if ($is_alias) { ?> <img class="alias" src="rsrc/alias.png"/> <?php } ?>
<p class="descr"><?=htmlspecialchars($_INFO['descr'])?></p>
			<?php
			break;
		case 'hr':
			?> <li class="item item-hr"> <hr/> <span></span> <?php
			break;
		default:
			die("invalid item type");
	}
	?></li><?php
};
$folderCB = function ($id, $depth, $_ITEM, $pre) {
	global $folder_anchor_page;
	if ($depth == 0)
		return true;
	if (!$_ITEM['public'] && !$_AUTHED) 
		return false;
	if ($pre) {?>
<li class="item item-folder"><details>
	<summary>
		<span class="icon"></span>
		<p class="descr"><?=htmlspecialchars($_ITEM['item']['descr'])?></p>
		<a class="folder-anchor" href="<?=$folder_anchor_page?>path=<?=Metadata_GetBasePath($id)?>"></a>
		<span class="folder-name"><?=$_ITEM['item']['name']?></span>
	</summary>
	<ul>
	<?php } else { ?>
	</ul>
</details></li>
	<?php }
	return true;
};
Metadata_TreeWalk($_ID, $folderCB, $regularCB, 0);
?>
		</ul></div>
	</body>
</html>
