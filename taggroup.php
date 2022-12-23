<?php

include_once "conf.php";
include_once "core.php";

include "indexauthedcheck.php";

if (!isset($_GET['tag']))
	die("undef tag");

$_TAGS = json_decode(file_get_contents("tags.json"), true);
if (!isset($_TAGS[$_GET['tag']])) 
	die("tag '".$tag."' is not registered");

$_ID = $_CONF['rootid'];

?><!DOCTYPE html>
<html>
	<head lang="fr">
		<meta charset="utf-8">
		<title><?=$_CONF['titlebase']?> : Filter by tag</title>
		<link rel="stylesheet" href="main.css" type="text/css">
		<script type="text/javascript" src="rsrc/jquery-3.3.1.min.js" crossorigin="anonymous"></script>
	</head>
	<body showlinks="true">
		<header>
			<span id="path">
				<a id="homebutton" href="index.php"></a>
			</span>
			<?php $_CONF['enablebatchmove'] = false; include "indexheaderright.php"; ?>
		</header>

		<?php include "indexitemtemplates.html"; ?>
		<?php include "indextagform.html"; ?>
		<script type="text/javascript" src="indexmain.js"></script>

		<h2 class="taggroup">Tag <?=$_GET['tag']?></h2>
		<div class="root" id="tagroot"></div>

		<script type="text/javascript">
			glob_modify = <?=(($_PUBLICEDIT||$_AUTHED)?'true':'false')?>;
			$(function () {
				LoadTagGroup("tagroot", "<?=$_CONF['rootid']?>", "<?=$_GET['tag']?>", false, glob_modify);
			});
		</script>
	</body>
</html>
