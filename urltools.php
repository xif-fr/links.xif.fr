<?php

header('Content-Type: text/plain');
$json = [];

if (!isset($_REQUEST['url'])) die("no url");
$r = preg_match("/^https?:\/\/([^\/]+)(\/.*)?$/", $_REQUEST['url'], $_);
if ($r !== 1) die("invalid url");
$url = $_REQUEST['url'];
$domain = $_[1];
$json['domain'] = $domain;

function null_error_handler ($errno, $errstr, $errfile, $errline) {
}

if (preg_match("/^https?:\/\/(www.youtube.com\/watch\?v=|youtu.be\/)([a-zA-Z0-9_-]{11})/", $url, $_)) {
	$json['type'] = 'yt';
	$vid = $_[2];
	parse_str(file_get_contents("https://www.youtube.com/get_video_info?video_id=".$vid), $_);
	if (!isset($_['title']) || !isset($_['author'])) 
		die("no title/author field in yt video info");
	$json['title'] = $_['title']." - ".$_['author'];

} else {

	$json['type'] = 'web';
	$dom = new DOMDocument();
	set_error_handler('null_error_handler');
	libxml_use_internal_errors(true);
	$r = $dom->loadHTMLFile($url, LIBXML_NOWARNING);
	if (!$r) die("failed to get/parse web page : ".libxml_get_last_error()->message);
	libxml_clear_errors();
	restore_error_handler();
	$_ = $dom->getElementsByTagName("title");
	if ($_->length == 0) die("no title found");
	$title = $_->item(0)->textContent;
	$json['title'] = $title;

	if (preg_match("/^([a-z]+).wikipedia.org$/", $domain, $_) === 1) {
		$json['type'] = 'wiki';
		if ($_[1] == "fr") {
			$title = str_replace(" — Wikipédia", "", $title);
		} elseif ($_[1] == "en") {
			$title = str_replace(" - Wikipedia", "", $title);
		} else {}
		$json['title'] = $title;
	}
}

header('Content-Type: application/json');
echo json_encode($json);
