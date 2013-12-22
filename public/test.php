<?php

set_time_limit(0);

error_reporting(E_ALL ^ E_DEPRECATED);

define('EOL', "\n");
define('app_path', dirname(dirname(__FILE__)));

include '../lib/firephp/fb.php';

date_default_timezone_set('Europe/Kiev');

header('Content-Type: text/plain; charset=utf-8');

// db connection
try {
	$neo = new PDO('sqlite:' . app_path . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'neologic.sqlite');
	$ntc = new PDO('sqlite:' . app_path . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'ntcom.sqlite');
} catch ( PDOException $e ) {
	die('Connection failed: '.$e->getMessage());
}

$items = $neo->query('select * from items');
$items = $items->fetchAll();
$neo_items = array();
foreach ( $items as $item ) {
	$neo_items[trim($item['art'])] = $item;
}

$ntc_items = $ntc->query('select * from items');
$ntc_items = $ntc_items->fetchAll();

$count = 0;
foreach ( $ntc_items as $item ) {
	$art = trim($item['art']);
	if ( $art && isset($neo_items[$art]) ) {
		echo "{$item['price']}\t{$item['name']}\n{$neo_items[$art]['price']}\t{$neo_items[$art]['name']}\n\n";
		$count++;
	}
}
echo "count: $count";

?>