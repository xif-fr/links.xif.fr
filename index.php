<?php

include_once "conf.php";
include_once "core.php";

if (!is_writable($_CONF['metadata-path'])) 
	die("metadata folder is not writable");
if (!is_writable($_CONF['files-path']))
	die("files folder is not writable");

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

include "indexauthedcheck.php";

?><!DOCTYPE html>
<html>
	<head lang="fr">
		<meta charset="utf-8">
		<title><?=htmlspecialchars($_TITLE)?></title>
		<link rel="stylesheet" href="main.css" type="text/css">
<!--	<script type="text/javascript" src="https://code.jquery.com/jquery-git.min.js" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="rsrc/jquery-3.3.1.min.js">\x3C/script>')</script>
-->
		<script type="text/javascript" src="rsrc/jquery-3.3.1.min.js" crossorigin="anonymous"></script>
	</head>
	<body>

		<!-- - - - - - - - - - - - - - - - - - - Root Folder and Header - - - - - - - - - - - - - - - - - - -->
		<header>
			<?php if (isset($_GET['path'])) { ?>
			<span id="path">
				<a id="homebutton" href="index.php"></a>
				<?php
					$comps = explode("/", $_GET['path']);
					$path = "/";
					foreach ($comps as $name) {
						if ($name == "") continue;
						$path = $path.$name."/";
						?> &gt; <a href="?path=<?=$path?>"><?=$name?></a> <?php
					}
				?> |
			<?php }
			$nojs_path_arg = isset($_GET['path']) ? ("?path=".$_GET['path']) : "";
			?>
			</span>
			<a id="nojs_button" href="nojs.php<?=$nojs_path_arg?>">NoJS</a>
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
						$("#main-tag-button").click(function () {
							//////////////////////////////////////////////////////////////////////////////
						});
						$("#batch-move-button").click(function () {
							$('#batchm-form').prop('hidden', false);
							document.getElementById("batch-move-button").disabled = true;
							BatchMoveNextItem();
						});
					});
				</script>
				<button id="recload">Tout dérouler</button>
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
		<script type="text/javascript">
			glob_modify = <?=(($_PUBLICEDIT||$_AUTHED)?'true':'false')?>;
			glob_toload = null;
			root_id = <?=('"'.$_ID.'"')?>;
			$(function () {
				$("#recload").click(function() {
					glob_toload = [];
					$(this).remove();
					$(document.getElementById(root_id)).empty();
					LoadFolder(root_id);
				});
				LoadFolder(root_id);
			});
		</script>
		<div class="root" id="<?=$_ID?>">
		</div>

		<!-- - - - - - - - - - - - - - - - - - - Template Items - - - - - - - - - - - - - - - - - - -->
		<?php include "indexitemtemplates.html"; ?>

		<!-- - - - - - - - - - - - - - - - - - - Add Item Form - - - - - - - - - - - - - - - - - - -->
		<?php include "indexadditemform.html"; ?>

		<!-- - - - - - - - - - - - - - - - - - - Tags and Tag editing form - - - - - - - - - - - - - - - - - - -->
		<?php include "indextagform.html"; ?>

		<!-- - - - - - - - - - - - - - - - - - - Main Script - - - - - - - - - - - - - - - - - - -->
		<script type="text/javascript" src="indexmain.js"></script>

		<!-- - - - - - - - - - - - - - - - - - - Batch move form - - - - - - - - - - - - - - - - - - -->
		<?php include "indexbatchmoveform.html"; ?>

		<!-- - - - - - - - - - - - - - - - - - - Main page footer - - - - - - - - - - - - - - - - - - -->

		<?php if ($_ID == $_CONF['rootid']) { ?>
		<p id="comments">
			Ce modeste <del>outil de procrastination massive</del> répertoire de liens regroupe les ressources dont nous souhaîtons nous abreuver. Ses éléments ne sont pas destinés à rester ainsi <i>ad vitam æternam</i>; plutôt, chaque catégorie scientifique est amenée à évoluer, avec notre apprentissage, d'une simple <i>todo-list</i> malpropre vers un corpus synthétique et utile de documents. <a href="doc.txt">RTFM</a>.
		</p>
		<h2 class="taggroup">À traiter</h2>
		<div class="root" id="atraiterroot"></div>
		<h2 class="taggroup">Ça peut servir</h2>
		<div class="root" id="toolsroot"></div>
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
				LoadTagGroup("toolsroot", "tool");
				LoadTagGroup("atraiterroot", "à traiter");
			});
		</script>
		<?php } ?>
	</body>
</html>
