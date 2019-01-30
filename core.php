<?php

/* ============================= Metadata_* =============================
 * Safe/Valid input is supposed.
 * 'folder' and 'alias' types are handled here.
 * Other types, and the files/ directory, are handled externally
 */

include_once "conf.php";

function Metadata_JSONPath ($id) {
	global $_CONF;
	return $_CONF['metadata-path']."/".$id.".json";
}

/* Walk the tree starting at $rootid, calling back $folderCB / $regularCB (can be null
 *  if wanted) when a folder / regular item is found, with the item id and depth.
 * Simple recursive depth-first search.
 */
function Metadata_TreeWalk ($rootid, $folderCB, $regularCB, $depth) {
	$data = Metadata_Get($rootid);
	if ($data['type'] == 'folder') {
		if ($folderCB !== null)
			$folderCB($rootid, $depth);
		foreach ($data['item']['children'] as $childid) {
			Metadata_TreeWalk($childid, $folderCB, $regularCB, $depth+1);
		}
	} else {
		if ($regularCB !== null)
			$regularCB($rootid, $depth);
	}
}

/* Get/Store item data (JSON file).
 */
function Metadata_Get ($id) {
	global $_CONF;
	$path = Metadata_JSONPath($id);
	__log__( "Metadata_Get : ".$path );
	if (!is_file($path)) 
		die( "Item '".$id."' does not exist in database" );
	$json = json_decode(file_get_contents($path), true);
	// Sanity checks
	if (is_null($json)) 
		die( "Failed to decode JSON file" );
	if (!isset($json['parent']) || !isset($json['refby']) || !is_array($json['refby']) || !isset($json['type']) || !isset($json['item']) || !is_array($json['item']) || !isset($json['public']) || !isset($json['tags'])) 
		die( "Metadata_Get on '".$id."' : missing field(s)" );
	if ($json['type'] == "folder" && (!isset($json['item']['children']) || !isset($json['item']['name'])))
		die( "Metadata_Get : missing folder info field(s)" );
	if ($json['type'] == "alias" && (!isset($json['item']['orig']))) 
		die( "Metadata_Get : missing alias orig field" );
/*	// Check parent
	if ($id != $_CONF['rootid']) {
		$parent_data = Metadata_Get($json['parent']);
		if ($parent_data['type'] != 'folder') 
			die( "Item ".$id." has a non-folder parent" );
	}
*/
	return $json;
}
function Metadata_Store ($id, $data, $checkexist) {
	global $_CONF;
	$path = Metadata_JSONPath($id);
	__log__( "Metadata_Store : ".$path );
	if ($checkexist && !is_file($path)) 
		die( "File does not exist" );
	file_put_contents($path, json_encode($data));
}

/* Check if a name is already used in a folder
 */
function Metadata_CheckNameUsed ($folder_data, $name) {
	__log__( "Checking if name '".$name."' is used in folder '".$folder_data['item']['name']."'…" );
	foreach ($folder_data['item']['children'] as $childid) {
		$child_data = Metadata_Get($childid);
		if (isset($child_data['item']['name']) && $child_data['item']['name'] == $name) 
			die( "Name '".$name."' already used in folder '".$folder_data['item']['name']."'" );
	}
}

/* Get the physical base path of the item, relative to the `files` folder
 * Form : `/foo/bar/baz(/)` (no extension appended, `/` appended if folder)
 */
function Metadata_GetBasePath ($id) {
	global $_CONF;
	if ($id == $_CONF['rootid']) 
		return "/";
	__log__( "Get the path to item '".$id."'…" );
	$data = Metadata_Get($id);
	if (!isset($data['item']['name']))
		die( "Trying to get path of a nameless item" );
	$parent_path = Metadata_GetBasePath($data['parent']);
	$path = $parent_path.$data['item']['name'];
	if ($data['type'] == 'folder') 
		$path .= "/";
	return $path;
}

/* Inverse operation of Metadata_GetBasePath
 */
function Metadata_Path2ID ($path) {
	global $_CONF;
	__log__( "Getting the ID associated with the path '".$path."'…" );
	$components = explode("/", $path);
	array_shift($components);
	if (strlen($components[count($components)-1]) == 0) 
		array_pop($components);
	if (count($components) == 0) 
		return $_CONF['rootid'];
	$id = Metadata_PathComponents2ID($_CONF['rootid'], $components);
	return $id;
}
function Metadata_PathComponents2ID ($folderid, $components) {
	$folder_data = Metadata_Get($folderid);
	foreach ($folder_data['item']['children'] as $childid) {
		$child_data = Metadata_Get($childid);
		if (isset($child_data['item']['name']) && $child_data['item']['name'] == $components[0]) {
			$name = array_shift($components);
			if (count($components) == 0) 
				return $childid;
			if ($child_data['type'] != 'folder') 
				die("Path2ID : '".$name."' not a folder");
			$id =  Metadata_PathComponents2ID($childid, $components);
			return $id;
		}
	}
	die("Path2ID : '".$components[0]."' not found in folder '".$folderid."'");
}

/* Create a new item in the database, in the folder $parent_id (at the back).
 * For type=alias/folder, use Metadata_CreateAlias/Metadata_CreateFolder instead.
 * $item_f is an anonymous function of type ($newid, $newbasepath) -> item_array
 * Name is optionnal, if given, name is set in $item. Return the new id.
 */
function Metadata_CreateItemPreCB ($parent_id, $type, $name, $item_f) {
	__log__( "Creating a new item '".$name."' in folder '".$parent_id."'…" );
	$parent_data = Metadata_Get($parent_id);
	if ($parent_data['type'] != 'folder') 
		die( "Parent ".$id." is not a folder" );
	// Check if name not already used in the folder
	if (!is_null($name)) {
		Metadata_CheckNameUsed($parent_data, $name);
		$path = Metadata_GetBasePath($parent_id).$name;
	} else 
		$path = null;
	// Generate new id
	$i = 0;
	do {
		$id = md5($parent_id.$name.time().$i);
		$i++;
	} while ( file_exists(Metadata_JSONPath($id)) );
	// Fill and store file metadata
	$item = $item_f( $id, $path );
	if (!is_null($name)) 
		$item['name'] = $name;
	$data = [
		'parent' => $parent_id,
		'refby' => [],
		'type' => $type,
		'item' => $item,
		'public' => true,
		'tags' => [],
	];
	Metadata_Store($id, $data, false);
	// Add the file in parent folder (at the back)
	$parent_data['item']['children'][] = $id;
	Metadata_Store($parent_id, $parent_data, true);
	return $id;
}
function Metadata_CreateItem ($parent_id, $type, $item, $name) {
	return Metadata_CreateItemPreCB ($parent_id, $type, $name, function ($newid, $newbasepath) use ($item) { return $item; });
}

/* Create an alias in the tree, of `$orig_id`, in the folder `$parent_id`,
 *  and set the original item as referenced. Return the new id.
 */
function Metadata_CreateAlias ($orig_id, $parent_id) {
	__log__( "Creating a new alias of '".$orig_id."' in folder '".$parent_id."'…" );
	$orig_data = Metadata_Get($orig_id);
	if (!isset($orig_data['item']['name'])) 
		die( "Can't alias a nameless item" );
	$item = [ 
		'orig' => $orig_id,
		'descr' => null
	];
	$id = Metadata_CreateItem($parent_id, 'alias', $item, $orig_data['item']['name']);
	$orig_data['refby'][] = $id;
	Metadata_Store($orig_id, $orig_data, true);
	return $id;
}

/* Create a folder in the tree, in the folder `$parent_id`. Return the new id.
 */
function Metadata_CreateFolder ($parent_id, $name) {
	__log__( "Creating a new folder '".$name."' in folder '".$parent_id."'…" );
	$item = [ 
		'name' => $name,
		'children' => [],
	];
	return Metadata_CreateItem($parent_id, 'folder', $item, $name);
}

/* Move an item in its folder, at position `$newpos` ∈ [0,SzFolder[.
 */
function Metadata_ChangePos ($id, $newpos) {
	global $_CONF;
	if ($id == $_CONF['rootid']) 
		die( "Can't move root item" );
	__log__( "Move the item '".$id."' in its folder, at position ".$newpos );
	$data = Metadata_Get($id);
	$parent_data = Metadata_Get($data['parent']);
	// Check for bounds
	$children =& $parent_data['item']['children'];
	if ($newpos < 0 || $newpos >= count($children)) 
		die( "New position out of bounds" );
	// Move the id in the children array from $pos to $newpos
	$pos = array_search($id, $children);
	$_ = array_splice($children, $pos, 1);
	array_splice($children, $newpos, 0, $_);
	// Store parent's metadata
	Metadata_Store($data['parent'], $parent_data, true);
}

/* Move an item through the tree, at the back of the folder $newparent
 */
function Metadata_Move ($id, $newparent) {
	global $_CONF;
	if ($id == $_CONF['rootid']) 
		die( "Can't move root item" );
	__log__( "Move the item '".$id."' at the back of the folder '".$newparent."'" );
	$data = Metadata_Get($id);
	if ($data['parent'] == $newparent) 
		die( "Metadata_Move : can't move the item '".$id."' in its own folder '".$newparent."'" );
	// Check if name not already used in new folder, and add in the new folder
	$new_parent_data = Metadata_Get($newparent);
	if (isset($data['item']['name']))
		Metadata_CheckNameUsed($new_parent_data, $data['item']['name']);
	$new_parent_data['item']['children'][] = $id;
	Metadata_Store($newparent, $new_parent_data, true);
	// Remove from old folder
	$old_parent_data = Metadata_Get($data['parent']);
	$old_parent_data['item']['children'] = array_values(array_diff($old_parent_data['item']['children'], array($id)));
	Metadata_Store($data['parent'], $old_parent_data, true);
	// Update parent id
	$data['parent'] = $newparent;
	Metadata_Store($id, $data, true);
}

/* For non-nameless items : change the item name. ['item']['name'] is updated.
 */
function Metadata_Rename ($id, $newname) {
	global $_CONF;
	if ($id == $_CONF['rootid']) 
		die( "Can't rename root item" );
	__log__( "Rename the item '".$id."' to '".$newname."'" );
	$data = Metadata_Get($id);
	if (!isset($data['item']['name'])) 
		die( "Can't rename nameless item" );
	// Check if name not already used in the folder
	$parent_data = Metadata_Get($data['parent']);
	Metadata_CheckNameUsed($parent_data, $newname);
	// Update name
	$data['item']['name'] = $newname;
	Metadata_Store($id, $data, true);
}

/* Erase an item in the database. die() if still referenced by an another item,
 * or if type=folder and the folder is not empty.
 */
function Metadata_EraseItemPreCB ($id, $cb_f) {
	global $_CONF;
	if ($id == $_CONF['rootid']) 
		die( "Can't erase root item" );
	__log__( "Erasing the path to item '".$id."'…" );
	// Check if referenced
	$data = Metadata_Get($id);
	if (!empty($data['refby'])) 
		die( "Can't erase item ".$id." : still referenced by ".Metadata_GetBasePath($data['refby'][0]) );
	// If folder, check if empty
	if ($data['type'] == 'folder' && !empty($data['item']['children'])) 
		die( "Can't erase folder : not empty" );
	$cb_f($data);
	// Remove from parent item
	$parent_data = Metadata_Get($data['parent']);
	$parent_data['item']['children'] = array_values(array_diff($parent_data['item']['children'], array($id)));
	Metadata_Store($data['parent'], $parent_data, true);
	// If alias, remove reference
	if ($data['type'] == 'alias') {
		$orig_data = Metadata_Get($data['item']['orig']);
		$orig_data['refby'] = array_values(array_diff($orig_data['refby'], array($id)));
		Metadata_Store($data['item']['orig'], $orig_data, true);
	}
	// Erase JSON file
	unlink( Metadata_JSONPath($id) );
}
function Metadata_EraseItem ($id) {
	Metadata_EraseItemPreCB($id, function ($data) {});
}

/* Set an item's info field (data['item'][$key]) in the database.
 */
function Metadata_SetInfoKey ($id, $key, $value) {
	__log__( "Setting info key '".$key."' of item id '".$id."' to '".$value."'…" );
	if ($key == 'name' || $key == 'children' || $key == 'tags' || $key == 'orig') die();
	$data = Metadata_Get($id);
	$data['item'][$key] = $value;
	Metadata_Store($id, $data, true);
}

/* Add/Remove tag to item, Set tags
 */
function Metadata_SetTag ($id, $tag, $set) {
	$data = Metadata_Get($id);
	if ($set) 
		$data['tags'] = array_values(array_merge($data['tags'], array($tag)));
	else 
		$data['tags'] = array_values(array_diff($data['tags'], array($tag)));
	Metadata_Store($id, $data, true);
}
function Metadata_SetTags ($id, $taglist) {
	if (!is_array($taglist)) die();
	$data = Metadata_Get($id);
	$data['tags'] = $taglist;
	Metadata_Store($id, $data, true);
}

/* Set/Unset the 'public' flag of an item
 */
function Metadata_SetPublic ($id, $public) {
	$data = Metadata_Get($id);
	$data['public'] = $public;
	Metadata_Store($id, $data, true);
}
