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

if (isset($_COOKIE[session_name()])) {
	session_start();
	if (!isset($_SESSION['authed'])) 
		$_SESSION['authed'] = false;
}

$_AUTHED = (isset($_SESSION) && $_SESSION['authed'] == true);
$jsonauth = json_decode(file_get_contents($_CONF['authfilepath']), true);
$_PUBLICEDIT = $jsonauth['public-edit'];
unset($jsonauth);

if ($_CONF['private-repository'])
	if (!$_AUTHED)
		header("Location: auth.php");

?><!DOCTYPE html>
<html>
	<head lang="fr">
		<meta charset="utf-8">
		<title><?=htmlspecialchars($_TITLE)?></title>
		<link rel="stylesheet" href="main.css" type="text/css">
		<script type="text/javascript" src="https://code.jquery.com/jquery-git.min.js" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="rsrc/jquery-3.3.1.min.js">\x3C/script>')</script>
	</head>
	<body>
		<!-- - - - - - - - - - - - - - - - - - - Root Folder - - - - - - - - - - - - - - - - - - -->
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
				?>
			</span>
			<?php } ?>
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
					});
				</script>
				<button id="recload">Tout dérouler</button>
				<?php if (!$_PUBLICEDIT && !$_AUTHED) {
					?> <span id="authbutton"><a href="auth.php?id=<?=$_ID?>"></a></span> <?php
				} else {
					?> <button id="main-tag-button"><img src="rsrc/tag.png"/></button> <?php
				}
				?>
			</div>
		</header>
		<script type="text/javascript">
			glob_modify = <?=(($_PUBLICEDIT||$_AUTHED)?'true':'false')?>;
			glob_toload = null;
			$(function () {
				var root_id = <?=('"'.$_ID.'"')?>;
				$("#recload").click(function() {
					glob_toload = [];
					$(this).remove();
					$("#"+root_id).empty();
					LoadFolder(root_id);
				});
				LoadFolder(root_id);
			});
		</script>
		<div class="root" id="<?=$_ID?>">
		</div>
		<!-- - - - - - - - - - - - - - - - - - - Template Items - - - - - - - - - - - - - - - - - - -->
		<div hidden>
			<select id="tpl-action-select">
				<option value="nothing"></option>
				<option value="copy">Copier</option> 
				<option value="delete">Supprimer</option>
				<option value="edit">Titre</option>
				<option value="rename">Renommer</option>
				<option value="toglpriv">Protection</option>
				<option value="info">Informations</option>
			</select>
		</div>
		<ul hidden>
			<li class="item item-folder" id="tpl-folder">
				<button class="icon"></button>
				<img class="alias" src="rsrc/alias.png"/>
				<p class="descr"> </p>
				<span class="lock"></span>
				<a class="folder-anchor" href=""></a>
				<span class="folder-name"></span>
			</li>
			<li class="item item-doc" id="tpl-doc">
				<a class="icon" href="" target="_blank"></a>
				<img class="alias" src="rsrc/alias.png"/>
				<p class="descr"> </p>
				<span class="lock"></span>
				<a class="orig" href="" target="_blank"></a>
			</li>
			<li class="item item-web" id="tpl-web">
				<a class="icon" href="" target="_blank"></a>
				<img class="alias" src="rsrc/alias.png"/>
				<p class="descr"> </p>
				<span class="lock"></span>
				<a class="save" href=""></a>
				<a class="orig" href="" target="_blank"></a>
			</li>
			<li class="item item-yt" id="tpl-yt">
				<a class="icon" href="" target="_blank"></a>
				<img class="alias" src="rsrc/alias.png"/>
				<p class="descr"> </p>
				<span class="lock"></span>
				<a class="save" href=""></a>
			</li>
			<li class="item item-txt" id="tpl-txt">
				<span class="icon"></span>
				<img class="alias" src="rsrc/alias.png"/>
				<p class="descr"> </p>
				<span class="lock"></span>
			</li>
			<li class="item item-hr" id="tpl-hr">
				<hr/><span></span>
				<span class="lock"></span>
			</li>
			<li class="item item-new" id="tpl-new">
				<button class="new-item">➕</button>
			</li>
		</ul>
		<!-- - - - - - - - - - - - - - - - - - - Add Item Form - - - - - - - - - - - - - - - - - - -->
		<form id="add-form" hidden>
			<input type="hidden" name="action" value="new"/>
			<input type="hidden" name="folderid" value=""/>
			<div id="add-type-choice">
				<input type="radio" name="type" id="type-folder" value="folder"/> <label for="type-folder">Dossier</label> <br/>
				<input type="radio" name="type" id="type-doc" value="doc"/> <label for="type-doc">Document</label> <br/>
				<input type="radio" name="type" id="type-web" value="web"/> <label for="type-web">Lien</label> <br/>
				<input type="radio" name="type" id="type-yt" value="yt"/> <label for="type-yt">Vidéo Youtube</label> <br/>
				<input type="radio" name="type" id="type-txt" value="txt"/> <label for="type-txt">Note texte</label> <br/>
				<input type="radio" name="type" id="type-hr" value="hr"/> <label for="type-hr">Séparateur</label> <br/>
				<input type="radio" name="type" id="type-paste" value="paste"/> <label for="type-paste">Élem. copié</label>
			</div>
			<script type="text/javascript">
				function FormSetType (type, do_click) {
					if (do_click !== false)
						$("#add-form input#type-"+type).click();
					$('#add-form fieldset')
						.prop('hidden', true);
					$('fieldset#add-form-'+type)
						.prop('hidden', false)
						.find('input:first')
							.focus().click();
				}
				$(function () {
					$('#add-form input[name=type]').click(function() {
						var type = $('#add-form input[name=type]:checked').val();
						FormSetType(type, false);
					});
					$('#add-form-cancel').click(function() {
						$('#add-form')
							.prop('hidden', true)
							.unbind('submit')
							.find("input[autoclear]")
								.val("");
					});
					$('#add-descr-folder, #add-descr-doc, #add-descr-web, #add-descr-yt').on('keyup change', function() {
						var descr = $(this).val();
						descr = descr.replace(/[^a-zA-Z0-9\u00C0-\u02AF\u0391-\u03A9\u03B1-\u03C9]+/g,"-").replace(/(-+)$/,"").toLowerCase();
						$("#"+this.id.replace("descr","name")).val(descr);
					});
					$('#add-file').change(function() {
						var filename = this.files[0].name.replace(/\.[^/.]+$/, "");
						var descrfield = $('#add-descr-doc');
						if (descrfield.val() != "") 
							return; 
						descrfield.val(filename);
						filename = filename.replace(/[^a-zA-Z0-9]+/g, "-").toLowerCase();
						$("#add-name-doc").val(filename);
					});
					$('#add-link-web').on('keyup change', function() {
						var raw = $(this).val();
						var add_descr = $("#add-descr-web");
						var r = /^(https?:\/\/[^ ]+)( (.+))?$/.exec(raw);
						if (r != null) {
							var DoEditDescr = function (is_yt, new_descr, new_url) {
								if (is_yt) {
									FormSetType('yt');
									$("#add-link-yt").val(new_url);
									$("#add-descr-yt").focus().val(new_descr);
								} else {
									if (add_descr.val() != "") 
										return;
									$('#add-link-web').val(new_url);
									$("#add-descr-web").focus().val(new_descr);
								}
							}
							if ((r[3] == "" || r[3] == undefined) && add_descr.val() == "") {
								var rdoc = /\.(pdf|png|jpg|jpeg|gif|tiff|bmp|zip|tar|gz|xz|tgz|djvu|epub)$/.exec(r[1]);
								if (rdoc != null) {
									FormSetType('doc');
									$("#add-link-doc").val(r[1]);
								} else {
									$.ajax({
										url: "urltools.php",
										type: 'POST',
										data: { 'url': r[1] },
										dataType: 'json',
										success: function (data) {
											DoEditDescr( (data['type']=='yt'), data['title'], r[1] );
										},
										error: function (xhr) {
											alert(xhr.responseText);
										}
									});
								}
							} else {
								DoEditDescr( (r[1].search("https://www.youtube.com") != -1), r[3], r[1] );
							}
						}
					});
				});
			</script>
			<fieldset id="add-form-folder" hidden>
				<div class="inputline"><span> <span><label for="add-descr-folder">Titre :</label></span> <span><input type="text" id="add-descr-folder" name="descr-folder" autoclear/></span> </span></div>
				<div class="inputline"><span> <span><label for="add-name-folder">Nom :</label></span> <span><input type="text" id="add-name-folder" name="name-folder" autoclear/></span> </span></div>
			</fieldset>
			<fieldset id="add-form-doc" hidden>
				<div class="inputline"><span> <span><label for="add-link-doc">URL :</label></span> <span><input type="url" id="add-link-doc" name="url-doc" autoclear/></span> <span>et/ou <label for="add-file">fichier :</label> <input type="file" id="add-file" name="file" autoclear/></span> </span></div>
				<div class="inputline"><span> <span><label for="add-descr-web">Description :</label></span> <span><input type="text" id="add-descr-doc" name="descr-doc" autoclear/></span> </span></div>
				<div class="inputline"><span> <span><label for="add-name-doc">Nom :</label></span> <span><input type="text" id="add-name-doc" name="name-doc" autoclear/></span> </span></div>
			</fieldset>
			<fieldset id="add-form-web" hidden>
				<div class="inputline"><span> <span><label for="add-link-web">URL :</label></span> <span><input type="url" id="add-link-web" name="url-web" autoclear/></span> </span></div>
				<div class="inputline"><span> <span><label for="add-descr-web">Description :</label></span> <span><input type="text" id="add-descr-web" name="descr-web" autoclear/></span> </span></div>
				<div class="inputline"><span> <span><label for="add-name-web">Nom :</label></span> <span><input type="text" id="add-name-web" name="name-web" autoclear/></span> </span></div>
			</fieldset>
			<fieldset id="add-form-yt" hidden>
				<div class="inputline"><span> <span><label for="add-link-yt">URL :</label></span> <span><input type="url" id="add-link-yt" name="url-yt" autoclear/></span> </span></div>
				<div class="inputline"><span> <span><label for="add-descr-yt">Description :</label></span> <span><input type="text" id="add-descr-yt" name="descr-yt" autoclear/></span> </span></div>
				<div class="inputline"><span> <span><label for="add-name-yt">Nom :</label></span> <span><input type="text" id="add-name-yt" name="name-yt" autoclear/></span> </span></div>
			</fieldset>
			<fieldset id="add-form-txt" hidden>
				<div class="inputline"><span> <span><label for="add-txt">Note :</label></span> <span><input type="text" id="add-txt" name="txt-note" autoclear/></span> </span></div>
			</fieldset>
			<fieldset id="add-form-hr" hidden>
			</fieldset>
			<fieldset id="add-form-paste" hidden>
				<input type="radio" name="paste-type" id="paste-move" value="move"/> <label for="paste-move">Déplacer ici</label> <span class="spacer"></span>
				<input type="radio" name="paste-type" id="paste-alias" value="alias"/> <label for="paste-alias">Alias</label> <br/>
				<div class="inputline"><span> <span><label for="add-paste-id">ID de l'élément :</label></span> <span><input type="text" id="add-paste-id" name="paste-id" readonly autoclear/></span> </span></div>
				<div class="inputline"><span> <span><label for="add-paste-descr">Titre :</label></span> <span><input type="text" id="add-paste-descr" readonly autoclear class="display-field"/></span> </span></div>
			</fieldset>
			<span class="buttons">
				<span> <input type="checkbox" id="add-form-memory"/> <label for="add-form-memory">Répéter</label> &nbsp;</span>
				<button type="button" id="add-form-cancel">Annuler</button>
				<button type="submit" id="add-form-ok">Ajouter</button>
			</span>
			<progress id="add-progress" hidden></progress>
		</form>
		<!-- - - - - - - - - - - - - - - - - - - Main Script - - - - - - - - - - - - - - - - - - -->
		<script type="text/javascript">

			/**************************** MOVE ITEM ****************************/

			function MoveItem (li, e) {
				li.style.position = 'relative';
				var initY = e.pageY;
				$(document).mousemove(function(e) {
					var dy = e.pageY-initY;
					if (li.previousElementSibling == null) {
						if (dy < 0) 
							return;
					} else {
						var h2 = li.previousElementSibling.offsetHeight/2.;
						if (dy < -h2) {
							li.parentElement.insertBefore(li, li.previousElementSibling);
							initY = e.pageY-h2;
						}
					}
					if (li.nextElementSibling == li.parentElement.getElementsByClassName("item-new")[0]) {
						if (dy > 0) 
							return;
					} else {
						var h2 = li.nextElementSibling.offsetHeight/2.;
						if (dy > h2) {
							li.parentElement.insertBefore(li, li.nextElementSibling.nextElementSibling);
							initY = e.pageY+h2;
						}
					}
					li.style.top = ''+dy+'px';
				})
				.mouseup(function() {
					$(this).off('mousemove mouseup');
					li.style.position = 'static';
					for (var pos = 0, node = li; (node = node.previousElementSibling) != null; pos++);
					$.get( "action.php", {
						'action' : 'move',
						'id' : li.id,
						'pos' : pos,
					}, function (data) {
						if (data != "ok") 
							alert(data);
					});
				});
			}

			/**************************** INSERT A RECEIVED ITEM IN THE TREE ****************************/

			function PrepareItem (item) {
				var is_alias = (item['type'] == 'alias');
				var id = item['id'];
				if (is_alias) {
					if (item['descr'] !== null) 
						item['origdata']['descr'] = item['descr'];
					item = item['origdata'];							/* /!\ $id -> alias id ; item['id'] -> orig id */
				}
				var li = document.getElementById('tpl-'+item['type']).cloneNode(true);
				li.id = id;
				if (!is_alias) 
					$(li).find("img.alias").remove();
				if (item['public']) 
					$(li).find("span.lock").remove();
				if (glob_modify) {
					var select = document.getElementById('tpl-action-select').cloneNode(true);
					if (is_alias) 
						$(select)
							.find("[value=edit],[value=copy]")
								.remove();
					if (item['name'] === undefined) 
						$(select)
							.find("[value=rename],[value=copy]")
								.remove();
					li.appendChild(select);
				}
				var disable_move = false;
				var item_move;
				if (item['type'] == 'hr') 
					item_move = $(li).find("hr");
				else {
					item_move =
					$(li).find(".descr")
						.text( item['descr'] )
						.dblclick(function() {
							if (!activateEdit || !glob_modify) 
								return;
							disable_move = true;
							EditDescription(this, id, function() { disable_move = false; });
						});
				}
				if (glob_modify) {
					item_move.mousedown(function(e) {
						if (e.which != 1 || disable_move) return;
						var timer = setTimeout(function() {
							MoveItem(li, e);
							$(this).off('mouseup blur');
						}, 200);
						$(this).on('mouseup blur', function() {
							clearTimeout(timer);
						});
					});
					$(select).change(function () {
						if (this.value == 'delete') {
							DeleteItem(li, id);
						}
						else if (this.value == 'copy') {
							$("input#add-paste-id")
								.val(id);
							$("input#add-paste-descr")
								.val(item['descr']);
						}
						else if (this.value == 'edit') {
							if (is_alias) return;
							disable_move = true;
							EditDescription(li.getElementsByClassName('descr')[0], id, function() { disable_move = false; });
						}
						else if (this.value == 'rename') {
							if (item['name'] === undefined) return;
							RenameItem(id, item, li);
						}
						else if (this.value == 'toglpriv') {
							ToggleItemProtection(id, li);
						}
						else if (this.value == 'info') {
							alert(JSON.stringify(item, undefined, "   "));
						}
						this.value = 'nothing';
					});
				}
				switch (item['type']) {
					case 'folder':
						$(li).find("a.folder-anchor")
							.attr('href', "?path="+item['path']);
						$(li).find("span.folder-name")
							.text( item['name'] );
						$(li).prop('opened', false);
						if (!is_alias) {
							var but = $(li)
								.find("button.icon").click(function() {
									$(li).prop('opened', function (_,opened) {
										if (opened) 
											li.removeChild(li.getElementsByTagName('ul')[0]);
										else 
											LoadFolder(id);
										return !opened;
									});
								});
							if (glob_toload !== null) 
								glob_toload.push(but);
						}
						break;
					case 'yt':
						$(li).find("a.icon").attr('href', item['url']);
						if (item['saved']) 
							$(li).find("a.save").attr('href', item['localurl']);
						else 
							$(li).find("a.save").remove();
						break;
					case 'doc':
						$(li).find("a.icon").attr('href', item['localurl']);
						if (item['url'] != null) 
							$(li).find("a.orig")
								.attr('href', item['url'])
								.text(item['url']);
						else 
							$(li).find("a.orig").remove();
						if (['png','jpg','jpeg','gif','tiff','bmp'].indexOf(item['ext']) !== -1) 
							$(li).addClass("item-img");
						if (['mp3','m4a','wav','aiff','flac','ogg'].indexOf(item['ext']) !== -1) 
							$(li).addClass("item-audio");
						if (['mp4','mov','webm','avi'].indexOf(item['ext']) !== -1) 
							$(li).addClass("item-video");
						if (['zip','tar','gz','xz','tgz'].indexOf(item['ext']) !== -1) 
							$(li).addClass("item-archive");
						if (['djvu','epub'].indexOf(item['ext']) !== -1) 
							$(li).addClass("item-ebook");
						if (item['ext'] == 'pdf') 
							$(li).addClass("item-pdf");
						break;
					case 'web':
						$(li).find("a.icon")
							.attr('href', item['url']);
						if (item['saved']) 
							$(li).find("a.save")
								.attr('href', item['localurl']);
						else 
							$(li).find("a.save")
								.remove();
						if (item['url'].search("wikipedia.org/") !== -1) {
							$(li).addClass("item-wiki");
							$(li).find("a.orig")
								.remove();
						} else {
							$(li).find("a.orig")
								.attr('href', item['url'])
								.text(item['url']);
						}
						break;
					case 'txt':
						break;
				}
				return li;
			}

			/**************************** LOAD FOLDER ****************************/

			function LoadFolder (folderid) {
				var loading = document.createElement('progress');
				var folder = document.getElementById(folderid);
				folder.appendChild(loading);
				$.getJSON( "action.php?action=list&folderid="+folderid, function (data) {
					folder.removeChild(loading);
					var ul = document.createElement('ul');
					for (var i = 0; i < data.length; i++) {
						if (data[i] === null) 
							continue;
						var li = PrepareItem(data[i]);
						ul.appendChild(li);
					}
					if (glob_modify) {
						var li_new = document.getElementById('tpl-new').cloneNode(true);
						li_new.id = null;
						$(li_new).click(function() {
							AddNewItem(folderid, li_new);
						});
						ul.appendChild(li_new);
					}
					folder.appendChild(ul);
					while (glob_toload !== null && glob_toload.length != 0) 
						glob_toload.shift().click();
				}).fail(function(xhr) {
					alert(xhr.responseText);
				});
			}

			/**************************** RELOAD ITEM ****************************/

			function ReloadItem (id, li) {
				$(li).empty().append( 
					document.createElement('progress')
				);
				$.getJSON( "action.php?action=getitem&id="+id, function (data) {
					var newli = PrepareItem(data);
					li.parentElement.replaceChild(newli, li);
				}).fail(function(xhr) {
					alert(xhr.responseText);
				});
			}

			/**************************** SHOW NEW ITEM FORM AND ADD ITEM ****************************/

			function AddNewItem (folderid, li_new) {
				glob_toload = null;
				$("#add-form")
					.prop('hidden', false);
				$("#add-form input[name=folderid]")
					.val( folderid );
				if ( "" != $("input#add-paste-id").val() ) 
					FormSetType('paste');
				else 
					FormSetType('web');
				$("#add-form").submit(function() {
					$("#add-progress")
						.prop('hidden', false);
					var formdata = new FormData(this);
					$.ajax({
						url: "action.php",
						type: 'POST',
						data: formdata,
						dataType: 'json',
						cache: false, contentType: false, processData: false,
						success: function (data) {
							if (data['deletedid'] !== undefined) 
								$("#"+data['deletedid'])
									.remove();
							var li = PrepareItem(data);
							li_new.parentElement.insertBefore(li, li_new);
							var type = $('#add-form input[name=type]:checked').val();
							$("#add-form")
								.prop('hidden', true)
								.unbind('submit')
								.find("input[autoclear]")
									.val("");
							$("#add-progress")
								.prop('hidden', true);
							var add_form_memory = document.getElementById("add-form-memory").checked;
							if (add_form_memory) {
								AddNewItem(folderid, li_new);
								FormSetType(type);
							}
						},
						error: function (xhr) {
							alert(xhr.responseText);
							$("#add-progress")
								.prop('hidden', true);
						}
					});
					return false;
				});
			}

			/**************************** DELETE ITEM ****************************/

			function DeleteItem (li, id) {
				$.get( "action.php", {
					'action' : 'delete',
					'id' : id,
				}, function (data) {
					if (data == "ok") 
						li.parentElement.removeChild(li);
					else 
						alert(data);
				});
			}

			/**************************** EDIT DESCRIPTION ****************************/

			function EditDescription (field, id, f_after) {
				$(field)
					.prop('contenteditable',true)
					.focus()
					.blur(function() {
						field.innerText = field.innerText;
						$(field)
							.prop('contenteditable',false)
							.off('blur keydown');
						$.post( "action.php", {
							'action': 'editdescr',
							'id': id,
							'descr': field.innerText
						}, function (data) {
							if (data != "ok") 
								alert(data);
						});
						f_after();
					})
					.keydown(function (e) {
						if (e.which == 13) {
							e.preventDefault();
							$(this).blur();
						}
					});
			}

			/**************************** RENAME ITEM ****************************/

			function RenameItem (id, item, li) {
				var new_name = window.prompt("Renommer l'élément '"+item['name']+"' en :", item['name']);
				$.get( "action.php", {
					'action' : 'rename',
					'id' : id,
					'newname' : new_name
				}, function (data) {
					if (data != "ok") 
						alert(data);
					ReloadItem(id, li);
				});
			}

			/**************************** TOGGLE ITEM PROTECTION ****************************/

			function ToggleItemProtection (id, li) {
				$.get( "action.php", {
					'action' : 'toglpriv',
					'id' : id,
				}, function (data) {
					if (data != "ok") 
						alert(data);
					ReloadItem(id, li);
				});
			}

		</script>
		<?php if ($_ID == $_CONF['rootid']) { ?>
		<p id="comments">
			Ce modeste <del>outil de procrastination massive</del> répertoire de liens regroupe les ressources dont nous souhaîtons nous abreuver. Ses éléments ne sont pas destinés à rester ainsi <i>ad vitam æternam</i>; plutôt, chaque catégorie scientifique est amenée à évoluer, avec notre apprentissage, d'une simple <i>todo-list</i> malpropre vers un corpus synthétique et utile de documents. <a href="doc.txt">RTFM</a>.
		</p>
		<?php } ?>
	</body>
</html>
