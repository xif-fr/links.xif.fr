<?php

ini_set('html_errors', false);
include_once "conf.php";
include_once "core.php";

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

$regexp_url = "/^https?:\/\//";
$regexp_name = "/^".$_CONF['nameregexp']."$/u";
$regexp_md5id = "/^".$_CONF['idregexp']."$/";

header('Content-Type: text/plain');

if (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], ['list', 'new', 'editdescr', 'delete', 'move', 'toglpriv', 'rename', 'getitem', 'tags', 'filter', 'filterlist', 'tree']))
	die("invalid action");

/* Prepare the item data for sending to frontend.
 * Used in 'list', 'filterlist', 'new[type≠paste]', and 'getitem' requests.
 * `null` if not public and not authenticated.
 */
function Item_FrontendData ($id) {
	global $_CONF, $_AUTHED;
	$_ITEM = Metadata_Get($id);
	if (!$_ITEM['public'] && !$_AUTHED) 
		return null;
	$_INFO = $_ITEM['item'];
	switch ($_ITEM['type']) {
		case 'folder':
			if (!is_dir($_CONF['files-path'].Metadata_GetBasePath($id))) 
				die( $id." : '".Metadata_GetBasePath($id)."' not a dir" );
			$_ITEM['path'] = Metadata_GetBasePath($id);
			break;
		case 'yt':
			$basepath = $_CONF['files-path'].Metadata_GetBasePath($id);
			$_ITEM['saved'] = false;
			if (file_exists($basepath)) {
				if (!is_dir($basepath)) 
					die( $id." : video directory not a directory" );
				if (file_exists($basepath."/metadata.json")) 
					$_ITEM['saved'] = true;
			}
			break;
		case 'doc':
			$_INFO['localurl'] = $_CONF['files-path'].Metadata_GetBasePath($id).".".$_INFO['ext'];
			break;
		case 'web':
			if ($_INFO['saved']) {
				preg_match('/.+\/([^\/]*)$/', $_INFO['url'], $matches);
				$_INFO['localurl'] = $_CONF['files-path'].Metadata_GetBasePath($id).".save/".$matches[1];
			}
			break;
		case 'txt':
			break;
		case 'hr':
			break;
		case 'alias':
			$_INFO['origdata'] = Item_FrontendData($_INFO['orig']);
			if ($_INFO['origdata'] == null) 
				return null;
			break;
		default:
			die("unknown item type");
	}
	unset($_ITEM['refby']);
	unset($_ITEM['item']);
	$_ITEM['id'] = $id;
	return array_merge($_ITEM, $_INFO);
}

if (isset($_REQUEST['folderpath'])) {
	if (preg_match("/^\/(".$_CONF['nameregexp']."\/)*$/u", $_REQUEST['folderpath']) === 0) 
		die("invalid folder path");
	$_FOLDERID = Metadata_Path2ID($_REQUEST['folderpath']);
	$_FOLDER = Metadata_Get($_FOLDERID);
	if ($_FOLDER['type'] != 'folder') 
		die("not a folder");
}

if (isset($_REQUEST['folderid'])) {
	if (preg_match($regexp_md5id, $_REQUEST['folderid']) === 0) 
		die("invalid folder id");
	$_FOLDER = Metadata_Get($_REQUEST['folderid']);
	if ($_FOLDER['type'] != 'folder') 
		die("folderid : not a folder");
	$_FOLDERID = $_REQUEST['folderid'];
}

if (isset($_REQUEST['id'])) {
	if (preg_match($regexp_md5id, $_REQUEST['id']) === 0) 
		die("invalid id");
	$_ITEM = Metadata_Get($_REQUEST['id']);
	$_ID = $_REQUEST['id'];
}

/*------------------------------------ GET SINGLE ITEM ------------------------------------*/

if ($_REQUEST['action'] == 'getitem') {
	if (!isset($_ITEM)) 
		die("undef item");
	$_JSON = Item_FrontendData($_ID);
	header('Content-Type: application/json');
	echo json_encode($_JSON);
	exit();
}

/*------------------------------------ LIST FOLDER ------------------------------------*/

if ($_REQUEST['action'] == 'list') {
	if (!isset($_FOLDER)) 
		die("undef folder");
	$_JSON = [];
	foreach ($_FOLDER['item']['children'] as $id) 
		$_JSON[] = Item_FrontendData($id);
	header('Content-Type: application/json');
	echo json_encode($_JSON);
	exit();
}

/*------------------------------------ BUILD A TREE OF A FOLDER ------------------------------------*/

if ($_REQUEST['action'] == 'tree') {
	if (!isset($_FOLDER)) 
		die("undef folder");
	function BuildTreeWalk ($id) {
		$data = Metadata_Get($id);
		if ($data['type'] != 'folder') 
			return null;
		$_ITEM = array(
			'id' => $id,
			'name' => $data['item']['name'],
			'descr' => $data['item']['descr'],
			'children' => []
		);
		foreach ($data['item']['children'] as $childid) {
			$child = BuildTreeWalk($childid);
			if ($child !== null)
				$_ITEM['children'][] = $child;
		}
		return $_ITEM;
	}
	$_JSON = BuildTreeWalk($_FOLDERID);
	header('Content-Type: application/json');
	echo json_encode($_JSON);
	exit();
}	

/*------------------------------------ FILTER / SEARCH IN A FOLDER ------------------------------------*/

if ($_REQUEST['action'] == 'filterlist') {
	if (!isset($_FOLDER)) 
		die("undef folder");
	if (!isset($_REQUEST['rec']) || !isset($_REQUEST['type']))
		die("undef rec or type");
	$recursive = (int)$_REQUEST['rec'];
	$_ITEMS = array();
	switch ($_REQUEST['type']) {

		// Item which are tagged with $_REQUEST['tag']
		case 'tag': {
			if (!isset($_REQUEST['tag']))
				die("undef tag to filter");

			$cb = function ($id, $depth, $data, $pre) use (&$_ITEMS) {
				if ($pre === false)
					return;
				$data = Metadata_Get($id);
				if (in_array($_REQUEST['tag'], $data['tags'])) 
					$_ITEMS[] = $id;
			};
			if ($_REQUEST['rec'])
				Metadata_TreeWalk($_FOLDERID, $cb, $cb, 0);
			else 
				foreach ($_FOLDER['item']['children'] as $id)
					$cb($id, 0);

		} break;
		default:
			die("unknown filter type");
	}
	$_JSON = [];
	foreach ($_ITEMS as $id) 
		$_JSON[] = Item_FrontendData($id);
	header('Content-Type: application/json');
	echo json_encode($_JSON);
	exit();
}

/*------------------------------------ BELOW ARE EDIT ACTIONS ------------------------------------*/

if (!$_PUBLICEDIT && !$_AUTHED) 
	die("no permission to modify item");

function Item_MoveFilesPost ($_ID, $_ORIG, $BasePathPre) {
	global $_CONF;
	switch ($_ORIG['type']) {
		case 'web':
			if ($_ORIG['item']['saved']) {
				$oldpath = $_CONF['files-path'].$BasePathPre.".save";
				$newpath = $_CONF['files-path'].Metadata_GetBasePath($_ID).".save";
				$r = rename($oldpath, $newpath);
				if ($r == false)
					die("failed to move '.save' directory");
			}
			break;
		case 'yt': {
			$oldpath = $_CONF['files-path'].$BasePathPre;
			if (file_exists($oldpath)) {
				$newpath = $_CONF['files-path'].Metadata_GetBasePath($_ID);
				$r = rename($oldpath, $newpath);
				if ($r == false)
					die("failed to move video directory");
			}
		} break;
		case 'doc':
			$oldpath = $_CONF['files-path'].$BasePathPre.".".$_ORIG['item']['ext'];
			$newpath = $_CONF['files-path'].Metadata_GetBasePath($_ID).".".$_ORIG['item']['ext'];
			$r = rename($oldpath, $newpath);
			if ($r == false) 
				die("failed to move saved document");
			break;
		case 'folder':
			$oldpath = $_CONF['files-path'].$BasePathPre;
			$newpath = $_CONF['files-path'].Metadata_GetBasePath($_ID);
			$r = rename($oldpath, $newpath);
			if ($r == false) 
				die("failed to move folder's directory");
			break;
		case 'txt': break;
		case 'hr': break;
		case 'alias': break;
		default:
			die("unknown item type");
	}
}

/*------------------------------------ EDIT ITEM DESCRIPTION ------------------------------------*/

if ($_REQUEST['action'] == 'editdescr') {
	if (!isset($_ITEM)) 
		die("undef item");
	if (!isset($_REQUEST['descr']))
		die("undef new descr");
	if (!in_array($_ITEM['type'], ['alias','folder','yt','web','doc','txt'])) 
		die("description-less item type");
	Metadata_SetInfoKey($_ID, 'descr', $_REQUEST['descr']);
	echo "ok";
	exit();
}

/*------------------------------------ NEW ITEM ------------------------------------*/

if ($_REQUEST['action'] == 'new') {
	if (!isset($_FOLDER)) 
		die("undef folder");
	if (!isset($_REQUEST['type']) || !in_array($_REQUEST['type'], ['txt','yt','web','doc','folder','hr','paste']))
		die("invalid item addition type");
	$_DELETEID = null;

	switch ($_REQUEST['type']) {

		case 'txt' :
			if (!isset($_REQUEST['txt-note'])) 
				die("invalid form");
			$_ITEM = [
				'descr' => $_REQUEST['txt-note']
			];
			$_ID = Metadata_CreateItem($_FOLDERID, 'txt', $_ITEM, null);
			break;

		case 'hr':
			$_ID = Metadata_CreateItem($_FOLDERID, 'hr', [], null);
			break;

		case 'yt' :
			if (!isset($_REQUEST['name-yt']) || preg_match($regexp_name, $_REQUEST['name-yt']) === 0 || !isset($_REQUEST['descr-yt']) || !isset($_REQUEST['url-yt']) || preg_match($regexp_url, $_REQUEST['url-yt']) === 0)
				die("invalid form");
			$_ITEM = [
				'descr' => $_REQUEST['descr-yt'],
				'url' => $_REQUEST['url-yt'],
			];
			$_ID = Metadata_CreateItem($_FOLDERID, 'yt', $_ITEM, $_REQUEST['name-yt']);
			break;

		case 'web' :
			if (!isset($_REQUEST['name-web']) || preg_match($regexp_name, $_REQUEST['name-web']) === 0 || !isset($_REQUEST['descr-web']) || !isset($_REQUEST['url-web']) || preg_match($regexp_url, $_REQUEST['url-web']) === 0)
				die("invalid form");
			$_ITEM = [
				'descr' => $_REQUEST['descr-web'],
				'url' => $_REQUEST['url-web'],
				'saved' => false
			];
			$_ID = Metadata_CreateItem($_FOLDERID, 'web', $_ITEM, $_REQUEST['name-web']);
			break;

		case 'doc' :
			if (!isset($_REQUEST['name-doc']) || preg_match($regexp_name, $_REQUEST['name-doc']) === 0 || !isset($_REQUEST['descr-doc'])) 
				die("invalid form");
			if (isset($_REQUEST['url-doc']) && $_REQUEST['url-doc'] != "") {
				if (preg_match($regexp_url, $_REQUEST['url-doc']) === 0) die("invalid form");
				$url = $_REQUEST['url-doc'];
			} else {
				$url = null;
			}
			$_ID = Metadata_CreateItemPreCB($_FOLDERID, 'doc', $_REQUEST['name-doc'], function ($newid, $newbasepath) use ($url, $_CONF) {
				$_ITEM = [
					'descr' => $_REQUEST['descr-doc'],
					'ext' => null,
					'url' => $url
				];
				if (isset($_FILES['file']) && $_FILES['file']['name'] != "") {
					$_ITEM['ext'] = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
					$filepath = $_CONF['files-path'].$newbasepath.".".$_ITEM['ext'];
					$r = move_uploaded_file($_FILES['file']['tmp_name'], $filepath);
					if (!$r) 
						die("failed to move uploaded file");
				} else {
					if (is_null($url)) 
						die("missing both file and url");
					$_ITEM['ext'] = pathinfo(parse_url($_ITEM['url'],PHP_URL_PATH), PATHINFO_EXTENSION);
					$filepath = $_CONF['files-path'].$newbasepath.".".$_ITEM['ext'];
					$r = @copy($url, $filepath);
					if (!$r) 
						die("failed to download file");
				}
				return $_ITEM;
			});
			break;

		case 'folder' :
			if (!isset($_REQUEST['name-folder']) || preg_match($regexp_name, $_REQUEST['name-folder']) === 0 || !isset($_REQUEST['descr-folder'])) 
				die("invalid form");
			$_ID = Metadata_CreateFolder($_FOLDERID, $_REQUEST['name-folder']);
			$newpath = $_CONF['files-path'].Metadata_GetBasePath($_ID);
			$r = mkdir($newpath);
			if ($r == false) 
				die("failed to create folder directory");
			Metadata_SetInfoKey($_ID, 'descr', $_REQUEST['descr-folder']);
			break;

		/*------------------------------------ ALIAS/MOVE ITEM ------------------------------------*/

		case 'paste':
			if (!isset($_REQUEST['paste-id']) || preg_match($regexp_md5id, $_REQUEST['paste-id']) === 0)
				die("invalid paste id");
			if (!isset($_REQUEST['paste-type']) || !in_array($_REQUEST['paste-type'], ['move','alias'])) 
				die("invalid paste type");
			$_ORIG = Metadata_Get($_REQUEST['paste-id']);

			if ($_REQUEST['paste-type'] == 'move') {
				$_ID = $_REQUEST['paste-id'];
				if (isset($_ORIG['item']['name'])) 
					$BasePathPre = Metadata_GetBasePath($_ID);
				Metadata_Move($_ID, $_FOLDERID);
				if (isset($_ORIG['item']['name'])) 
					Item_MoveFilesPost($_ID, $_ORIG, $BasePathPre);
				$_DELETEID = $_ID;
			}

			if ($_REQUEST['paste-type'] == 'alias') {
				$_ID = Metadata_CreateAlias($_REQUEST['paste-id'], $_FOLDERID);
			}
			break;
	}

	header('Content-Type: application/json');
	$_JSON = Item_FrontendData($_ID);
	if (!is_null($_DELETEID)) 
		$_JSON['deletedid'] = $_DELETEID;
	echo json_encode($_JSON);
	exit();
}

/*------------------------------------ DELETE ITEM ------------------------------------*/

if ($_REQUEST['action'] == 'delete') {
	if (!isset($_ITEM)) 
		die("undef item");

	switch ($_ITEM['type']) {
		case 'web':
			if ($_ITEM['item']['saved']) 
				die("item ".$_ID." requires manual removing");
			Metadata_EraseItem($_ID);
			break;
		case 'yt':
			$basepath = $_CONF['files-path'].Metadata_GetBasePath($_ID);
			Metadata_EraseItem($_ID);
			if (file_exists($basepath)) {
				@unlink($basepath."/video.mp4");
				@unlink($basepath."/metadata.json");
				$r = rmdir($basepath);
				if ($r == false) 
					die("failed to delete video directory");
			}
			break;
		case 'doc':
			$filepath = $_CONF['files-path'].Metadata_GetBasePath($_ID).".".$_ITEM['item']['ext'];
			Metadata_EraseItem($_ID);
			$r = unlink($filepath);
			if ($r == false) 
				die("failed to delete saved file");
			break;
		case 'folder':
			Metadata_EraseItemPreCB($_ID, function ($data) use ($_ID, $_CONF) {
				$path = $_CONF['files-path'].Metadata_GetBasePath($_ID);
				$r = rmdir($path);
				if ($r == false) 
					die("failed to delete folder directory");
			});
			break;
		case 'txt':
			Metadata_EraseItem($_ID);
			break;
		case 'hr':
			Metadata_EraseItem($_ID);
			break;
		case 'alias':
			Metadata_EraseItem($_ID);
			break;
		default:
			die("unknown item type");
	}
	echo "ok";
	exit();
}

/*------------------------------------ CHANGE ITEM POSITION ------------------------------------*/

if ($_REQUEST['action'] == 'move') {
	if (!isset($_ITEM)) 
		die("undef item");
	if (!isset($_REQUEST['pos']) || !ctype_digit($_REQUEST['pos']))
		die("undef or invalid position");
	$pos = intval($_REQUEST['pos']);
	Metadata_ChangePos($_ID, $pos);
	echo "ok";
	exit();
}

/*------------------------------------ TOGGLE ITEM PUBLICNESS ------------------------------------*/

if ($_REQUEST['action'] == 'toglpriv') {
	if (!isset($_ITEM)) 
		die("undef item");
	Metadata_SetPublic($_ID, !$_ITEM['public']);
	echo "ok";
	exit();
}

/*------------------------------------ RENAME ITEM ------------------------------------*/

if ($_REQUEST['action'] == 'rename') {
	if (!isset($_ITEM)) 
		die("undef item");
	if (!isset($_ITEM['item']['name'])) 
		die("can't rename a nameless item");
	if (!isset($_REQUEST['newname']) || preg_match($regexp_name, $_REQUEST['newname']) === 0)
		die("invalid new name");
	$BasePathPre = Metadata_GetBasePath($_ID);
	Metadata_Rename($_ID, $_REQUEST['newname']);
	Item_MoveFilesPost($_ID, $_ITEM, $BasePathPre);
	echo "ok";
	exit();
}

/*------------------------------------ EDIT ITEM TAGS ------------------------------------*/

if ($_REQUEST['action'] == 'tags') {
	if (!isset($_ITEM)) 
		die("undef item");
	if ($_ITEM['type'] == 'hr') 
		die("can't tag hr");
	if (!isset($_REQUEST['taglist']))
		die("undef tag list");
	$_TAGS = json_decode(file_get_contents("tags.json"), true);
	$taglist = explode(',', $_REQUEST['taglist']);
	$cleantaglist = array();
	foreach ($taglist as $tag) {
		if ($tag == "") 
			continue;
		if (!isset($_TAGS[$tag])) 
			die("tag '".$tag."' is not registered");
		else
			$cleantaglist[] = $tag;
	}
	Metadata_SetTags($_ID, $cleantaglist);
	echo "ok";
	exit();
}
