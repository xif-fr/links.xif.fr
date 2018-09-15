<?php

/* ============================= Metadata_* =============================
 * Safe/Valid input is supposed.
 * 'folder' and 'alias' types are handled here.
 * Other types, and the files/ directory, are handled externally
 */

$_CONF['log'] = false;
function __log__ ($msg) {
	global $_CONF;
	if ($_CONF['log']) 
		echo $msg."\n";
}

$_CONF['metadata-path'] = "metadata";
$_CONF['idregexp'] = "[0-9a-f]{32}";
$_CONF['nameregexp'] = "[a-zA-Z0-9\x{00C0}-\x{02AF}\x{0391}-\x{03A9}\x{03B1}-\x{03C9}-]+";
$_CONF['rootid'] = "00000000000000000000000000000000";
$_CONF['authfilepath'] = "[somewhere private]/auth.json";
$_CONF['files-path'] = "files";
$_CONF['titlebase'] = "My super link repository !";
$_CONF['urlrewrite'] = false;
$_CONF['private-repository'] = false;
