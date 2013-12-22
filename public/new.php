<?php
/**
 * Main html generating file
 * @author DarkPark, Urkaine Odessa 2009
 */

set_time_limit(0);

error_reporting(E_ALL ^ E_DEPRECATED);

define('EOL', "\n");
define('app_path', dirname(dirname(__FILE__)));

include '../lib/firephp/fb.php';

date_default_timezone_set('Europe/Kiev');

$db      = 'neo';
$db_name = 'neologic.sqlite';

if ( isset($_REQUEST['db']) and $_REQUEST['db'] == 'ntc' ) {
	$db      = 'ntc';
	$db_name = 'ntcom.sqlite';
}

// db connection
try {
	$dbh = new PDO('sqlite:' . app_path . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . $db_name);
} catch ( PDOException $e ) {
	die('Connection failed: '.$e->getMessage());
}

$sections = $dbh->query('select * from sections where is_active = 1 order by "order"');
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);

?>
<html>
	<head>
		<title>Prices</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<link rel="shortcut icon" href="images/icon.ico"/>
		<link rel="stylesheet" type="text/css" href="style.css"/>
		<script type="text/javascript" src="jquery-1.5.1.min.js"></script>
		<script type="text/javascript" src="jquery.highlight-3.yui.js"></script>
		<!--script type="text/javascript" src="http://tablesorter.com/jquery.tablesorter.min.js"></script>
		<script type="text/javascript" src="http://static.jstree.com/v.1.0rc2/jquery.jstree.js"></script-->
	</head>
<body>
	<!--table style="" id="section-list" class="tablesorter">
		<thead> 
			<tr> 
				<th>name</th> 
				<th>amount</th> 
			</tr> 
		</thead> 
		<tbody>
			<?php foreach ( $sections as $section ) { /*?>
			<tr>
				<td><? echo $section['name'] ?></td>
				<td><? echo $section['items_last'] ?></td>
			</tr>
			<?/**/ } ?>
		</tbody>
	</table-->
	
	<div style="margin: 5px; padding: 5px; width: 300px; height: 90%; box-shadow: 2px 2px 2px #CCCCCC; background-color: #eeeae2">
		<div style="background-color: white; width: 100%; height: 100%">
			<div style="width: 100%; height: 20px; background-color: #eeeae2; float: left">
				<div style="font-weight: bold; padding-top: 0px; width: 70px; height: 20px; background-color: white; float: left; text-align: center; vertical-align: middle">
					sections
				</div>
				<div style="font-weight: bold; color: grey; padding-top: 0px; width: 70px; height: 20px; background-color: #eeeae2; float: left; text-align: center; vertical-align: middle">
					options
				</div>
			</div>
		</div>
	</div>
<script type="text/javascript">
	$(document).ready(function(){ 
		$('table#section-list').tablesorter(); 
	}); 
</script>
</body>
</html>