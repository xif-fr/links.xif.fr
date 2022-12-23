<?php

include_once "conf.php";
include_once "core.php";

include "indexauthedcheck.php";

if (!isset($_GET['tag']))
	die("undef tag");

$_TAGS = json_decode(file_get_contents("tags.json"), true);
if (!isset($_TAGS[$_GET['tag']])) 
	die("tag '".$tag."' is not registered");

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
			<div id="header-right">
				<span class="option"> <input type="checkbox" id="option-edit" checked/> <label for="option-edit">Édition</label> </span>
				<span class="option"> <input type="checkbox" id="option-links"/> <label for="option-links">Afficher URLs</label> </span>
				<script type="text/javascript">
					function setCookie (key, value) {
						document.cookie = key+'='+value+';expires=Tue, 19 Jan 2038 03:14:07 UTC';
					}
					function getCookie (key) {
						var keyValue = document.cookie.match('(^|;) ?'+key+'=([^;]*)(;|$)');
						return keyValue ? keyValue[2] : null;
					}
					disable_ajax_error = false;
					$(window).on("beforeunload", function(e) {
						disable_ajax_error = true;
					});
					activateEdit = true;
					$(function () {
						var val = (getCookie("showlinks") !== "false");
						$("#option-links")
							.prop('checked', val);
						$("body").attr('showlinks', val);
						$("#option-links").click(function () {
							setCookie("showlinks", this.checked);
							$("body").attr('showlinks', this.checked);
						});
						activateEdit = (getCookie("activateEdit") !== "false");
						$("#option-edit")
							.prop('checked', activateEdit);
						$("#option-edit").click(function () {
							setCookie("activateEdit", this.checked);
							activateEdit = this.checked;
						});
					});
				</script>
				<?php if (!$_PUBLICEDIT && !$_AUTHED) {
					?> <span id="authbutton"><a href="auth.php?id=<?=$_ID?>"></a></span> <?php
				} else {
					?> 
					<button id="batch-move-button">Batch move</button>
					<button id="main-tag-button"><img src="rsrc/tag.png"/></button>
					<?php
				}
				?>
			</div>
		</header>

		<?php include "indexitemtemplates.html"; ?>
		<?php include "indextagform.html"; ?>
		<script type="text/javascript" src="indexmain.js"></script>

		<h2 class="taggroup">Tag <?=$_GET['tag']?></h2>
		<div class="root" id="tagroot"></div>

		<script type="text/javascript">
			$(function () {
				function LoadTagGroup (tagrootid, tagname) {
					var container = document.getElementById(tagrootid);
					LoadItemsGroup( "action.php?action=filterlist&folderid=<?=$_CONF['rootid']?>&rec=1&type=tag&tag="+tagname, container, function (data) {
						var i = data.length, temp, randi;
						while (0 !== i) {
							randi = Math.floor(Math.random() * i); i -= 1;
							temp = data[i]; data[i] = data[randi]; data[randi] = temp;
						}
					}, function () {
						var tagroot = $(document.getElementById(tagrootid));
						tagroot.find("li > .tag").remove();
						tagroot.find("li > select > option").filter('[value="todo"],[value="copy"],[value="rename"],[value="delete"]').remove();
					} );
				}
				LoadTagGroup("tagroot", "<?=$_GET['tag']?>");
			});
		</script>
	</body>
</html>
