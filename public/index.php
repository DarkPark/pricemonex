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
	$ntc = new PDO('sqlite:' . app_path . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'ntcom.sqlite');
} catch ( PDOException $e ) {
	die('Connection failed: '.$e->getMessage());
}

function GetLastUpdateDetails () {
	global $dbh;
	$last_update = $dbh->query('select * from updates order by id desc limit 1');
	$last_update = $last_update->fetch(PDO::FETCH_ASSOC);
	return $last_update;
}

function GetAllItemsCount () {
	global $dbh;
	$result = '';
	$count  = $dbh->query('select count(*) as count from items where is_new = 1');
	$count  = $count->fetch(PDO::FETCH_ASSOC);
	$result = $count['count'];
	$count  = $dbh->query('select count(*) as count from items where id_update = (select max(id) from updates)');
	$count  = $count->fetch(PDO::FETCH_ASSOC);
	$result = $result . '/' . $count['count'];
	$count  = $dbh->query('select count(*) as count from items');
	$count  = $count->fetch(PDO::FETCH_ASSOC);
	$result = $result . '/' . $count['count'];
	return $result;
}

function GetDynamic ( $id_update ) {
	global $dbh;
	$diff_pos = $dbh->query('select count(*) as count, sum(price_diff) as diff from items where price_diff > 0 and id_update = ' . $id_update);
	$diff_pos = $diff_pos->fetch(PDO::FETCH_ASSOC);
	$diff_neg = $dbh->query('select count(*) as count, sum(price_diff) as diff from items where price_diff < 0 and id_update = ' . $id_update);
	$diff_neg = $diff_neg->fetch(PDO::FETCH_ASSOC);
	return array('pos' => $diff_pos, 'neg' => $diff_neg);
}

function GetPriceFallenItems () {
	global $dbh;
	$items = $dbh->query('select items.*, sections.name as sname from items, sections where items.id_section = sections.id and items.id_update = (select id from updates order by id desc limit 1) and items.price_diff < 0 order by sections.id, items.name');
	return $items->fetchAll(PDO::FETCH_ASSOC);
}
function GetNewItems () {
	global $dbh;
	$items = $dbh->query('select items.*, sections.name as sname from items, sections where items.id_section = sections.id and items.id_update = (select id from updates order by id desc limit 1) and items.is_new = 1 order by sections.id, items.name');
	return $items->fetchAll(PDO::FETCH_ASSOC);
}

if ( isset($_GET['action']) and $_GET['action'] == 'get_all_items' ) {
	$items = $dbh->query('select items.*, updates.time from items, updates where updates.id = items.id_update and updates.id = (select max(id) from updates) order by "order", items.name');
	$list  = array();
	if ( $items ) {
		foreach ( $items as $item ) {
			$list[$item['id_section']][] = array(
				'id'       => $item['id'],
				'art'       => $item['art'],
				'name'     => $item['name'],
				'price'    => (float)$item['price'],
				'diff'     => (float)$item['price_diff'],
				'warranty' => $item['warranty'],
				'is_new'   => (int)$item['is_new'],
				'time'     => date('Y-m-d', $item['time'])
			);
		}
	}
	//fb($list);
	echo json_encode($list);
	exit();
}

if ( isset($_GET['action']) and $_GET['action'] == 'get_items' ) {
	$sid    = $_GET['sid'];
	$filter = $_GET['filter'];
	$chknew = $_GET['chknew'];
	$inname = $_GET['inname'];
	$sortby = $_GET['sortby'];
	$where  = '';
	switch ( $filter ) {
		case 'all' :
			break;
		case 'latest' :
			$where = ' and updates.id = (select id from updates order by id desc limit 1)';
			break;
		case 'week' :
			$where = ' and updates.time > ' . (time() - 7*24*60*60);
			break;
		case 'month' :
			$where = ' and updates.time > ' . (time() - 30*24*60*60);
			break;
	}
	if ( $inname ) {
		$inname = strtolower($inname);
		$inname = explode(' ', $inname);
		if ( $inname ) {
			foreach ( $inname as $npart ) {
				$npart = trim($npart);
				if ( $npart ) {
					if ( $npart[0] == '-' ) {
						$npart  = ltrim($npart, '-');
						$where .= " and lower(items.name) not like lower('%$npart%')";
					} else {
						$where .= " and lower(items.name) like lower('%$npart%')";
					}
				}
			}
		}
	}
	if ( $chknew ) {
		$where .= " and items.is_new = $chknew";
	}
	$orderby = ' order by items.name';
	if ( $sortby ) {
		$orderby = ' order by ' . $sortby;
	}
	if ( $sid ) {
		$sql = 'select items.*, updates.time from items, updates where updates.id = items.id_update and items.id_section = ' . $sid . $where . $orderby;
		//fb($sql);
		$items = $dbh->query($sql);
		$list  = array();
		if ( $items ) {
			foreach ( $items as $item ) {
				$list[] = array(
					'id'       => $item['id'],
					'art'       => trim($item['art']),
					'name'     => $item['name'],
					'price'    => (float)$item['price'],
					'diff'     => (float)$item['price_diff'],
					'warranty' => $item['warranty'],
					'is_new'   => (int)$item['is_new'],
					'time'     => date('Y-m-d', $item['time'])
				);
			}
			
			$art_list = array();
			foreach ( $list as $item ) if ( $item['art'] ) $art_list[] = '"' . $item['art'] . '"';
			//fb($art_list);
			$items = $ntc->query('select * from items where art in (' . implode(',', $art_list) . ')');
			$art_list = array();
			if ( $items ) {
				foreach ( $items as $item ) $art_list[$item['art']] = $item;
			}
			foreach ( $list as & $item )
				if ( isset($art_list[$item['art']]) ) $item['ntc'] = $art_list[$item['art']]['price'];
		}
		//fb($list);
		echo json_encode($list);
	}
	exit();
}

if ( isset($_GET['action']) and $_GET['action'] == 'get_item_prices' ) {
	include '../lib/phpmygraph.php';
	
	header("Content-type: image/png");
	
	//Set config directives
    $cfg['zero-line-visible'] = 1;
    $cfg['average-line-visible'] = 0;
    $cfg['value-label-visible'] = 0;
    $cfg['horizontal-divider-visible'] = 0;
    $cfg['box-border-alpha'] = 100;
    
    $cfg['width'] = $_REQUEST['width'] - 5;
    $cfg['height'] = 200; 
	
	$id = strip_tags($_GET['id']);
	if ( $id ) {
		$prices = $dbh->query('select info.price, updates.time from info, updates where updates.id = info.id_update and info.id_item = ' . $id . ' order by updates.time');
		$list = array();
		$data = array();
		foreach ( $prices as $item ) {
			$list[] = array(
				'price' => (float)$item['price'],
				'time'  => date('y\<\b\r\>m\<\b\r\>d', $item['time'])
			);
			$data[date('m.d',$item['time'])] = (float)$item['price'];
		}
		$last = end($data);
		foreach ( $data as & $item ) {
			$item = $item - $last;
			//$item = $last - $item;
		}
		$cfg['title'] = 'delivery: ' . count($data); 
		//Create phpMyGraph instance
		$graph = new phpMyGraph();
		//Parse
		$graph->parseVerticalSimpleColumnGraph($data, $cfg);
		//$graph->parseverticalColumnGraph($data, $cfg);
		
		//echo json_encode($list);
	}
	exit();
}

$sections = $dbh->query('select * from sections where is_active = 1 order by "order"');

?>
<html>
	<head>
		<title>Prices</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<link rel="shortcut icon" href="images/icon.ico"/>
		<link rel="stylesheet" type="text/css" href="style.css"/>
		<script type="text/javascript" src="jquery-1.5.1.min.js"></script>
		<script type="text/javascript" src="jquery.highlight-3.yui.js"></script>
	</head>
<body>
<!-- flooble sidebar menu start -->
<script type="text/javascript">
// Floating Sidebar Menu Script from Flooble.com
// For more information, visit 
//	http://www.flooble.com/scripts/sidebar.php
// Copyright 2003 Animus Pactum Consulting inc.
//---------------------------------------------------------
    var ie = false;
    var open = true;
    var oldwidth = -1;
    if (document.all) { ie = true; }

    function getObj(id) {
        if (ie) { return document.all[id]; }
        else {    return document.getElementById(id);    }
    }
    
    function toggleSidebar() {
        var sidebar = getObj('sidebarcontents');
        var menu = getObj('sidebarmenu');
        //var arrow = getObj('sidearrow');
        if (open) {
        	var sidec = getObj('sidebar');            
            var h = sidec.scrollHeight;
            if (oldwidth < 0) { 
            	oldwidth = sidebar.scrollWidth;
            }
            sidebar.style.display = 'none';
            td = getObj('sidebartd');
            td.style.width = 0;         
            //arrow.innerHTML = '>';
            //alert(h + ' - ' + sidec.scrollHeight);
            sidec.style.height = h;
            open = false;
        } else {
            sidebar.style.display = 'block';
            //sidebar.style.width = oldwidth;
            //arrow.innerHTML = '<';
            open = true;
        }
        //getObj('focuser').focus();
        
    }    
    
    function setSidebarTop() {
        //alert('hoy');
        var sidec = getObj('sidebar');
        sidec.style.top = 10 + document.body.scrollTop;
        //setTimeout('setSidebarTop()', 10);
    }
    
    //setTimeout('setSidebarTop();', 2000);
    
</script>
<table border="0" cellspacing="0" cellpadding="3" id="sidebar" bgcolor="#FFFFFF"
	   style="border-top: 1px solid #000000; border-bottom: 1px solid #000000;
            position:absolute; z-index:100; left:0px; top:5px;
            font-family:Verdana;
	-moz-box-shadow: 2px 2px 2px #ccc; 
	-webkit-box-shadow: 2px 2px 2px #ccc;
	box-shadow: 2px 2px 2px #ccc;">
	<tr style="height:100px">
		<td valign="top" id="sidebartd"> 
			<div id="sidebarcontents" style="padding: 5px;">
				<table cellpadding="0" cellspacing="5" style="width:100%">
					<tr>
						<td align="center"><b>Фильтрация</b></td>
					</tr>
					<tr>
						<td>
							<span style="color:#aaa">поступление:</span><br>
							<select class="control" id="filter" onchange="UpdateItemList($('div.section#' + 1))">
								<option value="all">Все</option>
								<option value="latest" selected>Самые свежие</option>
								<option value="week">За неделю</option>
								<option value="month">За месяц</option>
							</select>
						</td>
					</tr>
					<tr>
						<td>
							<span style="color:#aaa">запрос:</span><br>
							<input id="filter" type="edit" class="control" onKeyPress="return OnFilterAllEditEnter(this,event)"/>
						</td>
					</tr>
				</table>
			</div>
		</td>
		<td valign="center" align="center" bgcolor="#000000" id="menucontainer" style="width:15px; cursor:pointer; color:#FFFFFF;" onClick="toggleSidebar();">
			.<br>.<br>.
			<!--a href="javascript:void(0);" id="sidebarmenu" onClick="toggleSidebar();" style="color:#FFFFFF; text-decoration:none;font-weight:bold;  font-family:Helvetica;">&blacksquare;<br>&blacksquare;<br>&blacksquare;</a><br>
			<a href="javascript:void(0);" style="color: #000000; heigh:1px;" id="focuser"></a-->
		</td>
    </tr>
</table>
<script type="text/javascript">
	toggleSidebar();
</script>
<!-- flooble sidebar menu end -->
	<div style="margin: 10px 10px 10px 20px">
		<a class="<?php echo $db == 'neo' ? 'switch-active' : 'switch' ?>" href="index.php?db=neo">Neologic</a> &nbsp;
		<a class="<?php echo $db == 'ntc' ? 'switch-active' : 'switch' ?>" href="index.php?db=ntc">NTCom</a>
	</div>
	
	<div class="section">
		<div class="section-title">
			<table style="width:100%">
				<tr>
					<td>
						<table style="width:100%; cursor:pointer" id="info">
							<tr>
								<td style="width:120px"><span style="color:#aaa">всего товаров:</span></td>
								<td><?php echo GetAllItemsCount(); ?> (<?php $last_update = GetLastUpdateDetails(); echo date('Y-m-d @ H:i', $last_update['time']); ?>)</td>
							</tr>
							<tr>
								<td><span style="color:#aaa">общая динамика:</span></td>
								<td><?php
									$dynamic = GetDynamic($last_update['id']);
									echo '<span style="color:red;">+' . $dynamic['pos']['diff'] . '</span>(' . $dynamic['pos']['count'] . ') / ';
									echo '<span style="color:blue;">' . $dynamic['neg']['diff'] . '</span>(' . $dynamic['neg']['count'] . ')';
								?></td>
							</tr>
						</table>
					</td>
					<td align=right>
						<?php
							if ( $db == 'neo' ) {
								echo '<a href="xls/neologic.xls" title="Neologic"><img border="0" src="images/file_xls_32.png"/></a>';
							} else {
								echo '<a href="xls/ntcom.xls" title="NTCom"><img border="0" src="images/file_xls_32.png"/></a>';
							}
						?>
					</td>
				</tr>
			</table>
		</div>
		<?php
			$items_low = GetPriceFallenItems();
			$items_new = GetNewItems();
			//print_r($items);
			
			?>
			<div class="section-body hidden">

				<div class="tab-box"> 
					<a href="javascript:;" class="tabLink activeLink" id="cont-1">уценка</a> 
					<a href="javascript:;" class="tabLink " id="cont-2">поступление</a> 
				</div>

				<div class="tabcontent paddingAll" id="cont-1-1"> 
					<table border="0" cellpadding="0" cellspacing="0" class="item-list maxw">
						<tr class="list-header">
							<td width="50" align="center">номер</td>
							<td width="50" align="center">артикул</td>
							<td width="50" align="center">категория</td>
							<td align="center">наименование</td>
							<td width="50" align="center">уценка</td>
							<td width="50" align="center">цена</td>
							<td width="50" align="center">гарантия</td>
						</tr>
						<?php
							foreach ( $items_low as $item ) {
								//print_r($item);
								?>
								<tr class="list-item" id="<?php echo $item['id'] ?>" id_section="<?php echo $item['id_section'] ?>">
									<td align="right" id="id_item"><?php echo $item['id'] ?></td>
									<td id="id_item"><?php echo $item['art'] ?></td>
									<td class="itname" nowrap><?php echo $item['sname'] ?></td>
									<td class="itname"><?php echo $item['name'] ?></td>
									<td nowrap align="right" ><?php echo $item['price_diff'] ?></td>
									<td nowrap align="right" ><?php echo $item['price'] ?></td>
									<td align="right"><?php echo $item['warranty'] ?></td>
								</tr>
								<?php
							}
						?>
					</table>
				</div>

				<div class="tabcontent paddingAll hide" id="cont-2-1"> 
					<table border="0" cellpadding="0" cellspacing="0" class="item-list maxw">
						<tr class="list-header">
							<td width="50" align="center">номер</td>
							<td width="50" align="center">артикул</td>
							<td width="50" align="center">категория</td>
							<td align="center">наименование</td>
							<td width="50" align="center">уценка</td>
							<td width="50" align="center">цена</td>
							<td width="50" align="center">гарантия</td>
						</tr>
						<?php
							foreach ( $items_new as $item ) {
								//print_r($item);
								?>
								<tr class="list-item" id="<?php echo $item['id'] ?>" id_section="<?php echo $item['id_section'] ?>">
									<td align="right" id="id_item"><?php echo $item['id'] ?></td>
									<td id="id_item"><?php echo $item['art'] ?></td>
									<td class="itname" nowrap><?php echo $item['sname'] ?></td>
									<td class="itname"><?php echo $item['name'] ?></td>
									<td nowrap align="right" ><?php echo ($item['price_diff'] < 0 ? $item['price_diff'] : '') ?></td>
									<td nowrap align="right" ><?php echo $item['price'] ?></td>
									<td align="right"><?php echo $item['warranty'] ?></td>
								</tr>
								<?php
							}
						?>
					</table>
				</div> 

			</div>
			<script type="text/javascript">
				$('.section-body table tr').mouseover(function(){
					$(this).addClass('trover');
				});
				$('.section-body table tr').mouseout(function(){
					$(this).removeClass('trover');
				});
				$('.section-body table tr').bind('dblclick', function() {
					//console.info($(this).attr('id_section'));
				});
				//$('div.header').click(function() {
//					$('div.header').bind('click', function() {
//						header = this;
//						$('div.section-body', header).toggleClass('hidden');
//					});
			</script>
	</div>
	<?php
		if ( $sections ) {
			$snum = 1;
			foreach ( $sections as $section ) {
				?>
					<div class="section" id="<?php echo $section['id'] ?>">
						<div class="section-title">
							<table class="maxw">
								<tr>
									<?php $snum++; ?>
									<td width="25" align="right" style="color:#aaa" id="items-count"><?php echo $section['items_last'] ?></td>
									<td id="name">&nbsp;<span style="color:#aaa"> :: </span>&nbsp;<b><?php echo $section['name'] ?></b> <span style="color:#aaa" id="items-count-new"><?php echo $section['items_new'] ? '+'.$section['items_new'] : '' ?></span></td>
									<td width="100" align="right"><span style="color:#aaa">товаров всего:</span></td>
									<td width="30" align="right" id="items-count-total"><?php echo $section['items_total'] ?></td>
									<td width="80" align="right"><span style="color:#aaa">последних:</span></td>
									<td width="30" align="right" id="items-count-last"><?php echo $section['items_last'] ?></td>
									<td width="80" align="right" nowrap><span style="color:#aaa">динамика:</span></td>
									<td width="50" align="right" id="diff-value" nowrap><?php
										$text = '';
										if ( $section['sum_inc'] == intval($section['sum_inc']) ) $section['sum_inc'] = intval($section['sum_inc']);
										if ( $section['sum_dec'] == intval($section['sum_dec']) ) $section['sum_dec'] = intval($section['sum_dec']);
										if ( $section['sum_inc'] ) $text = '<span style="color:red;">+' . $section['sum_inc'] . '</span>';
										if ( $section['sum_dec'] ) $text = $text . ($section['sum_inc'] && $section['sum_dec'] ? '/' : '') . '<span style="color:navy;">' . $section['sum_dec'] . '</span>';
										echo $text;
									?></td>
									<td width="110" align="right">
										<select style="background-color: white" class="control" id="filter" onchange="UpdateItemList($('div.section#' + <?php echo $section['id'] ?>))">
											<option value="all">Все</option>
											<option value="latest" selected>Самые свежие</option>
											<option value="week">За неделю</option>
											<option value="month">За месяц</option>
										</select>
									</td>
									<td width="60" align="right"><span style="color:#aaa">новые:</span></td>
									<td width="30" align="center" id="items-show-new">
										<input id="chknew" type="checkbox" onchange="UpdateItemList($('div.section#' + <?php echo $section['id'] ?>)); $('div.section-body', $('div.section#' + <?php echo $section['id'] ?>)).toggleClass('hidden', false);"/>
									</td>
									<td width="60" align="right"><span style="color:#aaa">фильтр:</span></td>
									<td width="110" align="right">
										<input id="filter" type="edit" class="control" style="width:150px" onKeyPress="return OnFilterEditEnter(this,event, <?php echo $section['id'] ?>)"/>
									</td>
									<td width="60" align="right">
										<div id="reset" class="divbtn" style="width:50px;" onclick="return OnFilterReset(this,event, <?php echo $section['id'] ?>)">сброс</div>
									</td>
								</tr>
							</table>
						</div>
						<div class="section-loading hidden">
							<table>
								<tr>
									<td><img src="images/ajax-loader-small.gif" alt="loading"></td>
									<td>&nbsp;<span style="color:#aaa">загрузка данных ...</span></td>
								</tr>
							</table>
						</div>
						<div class="section-body hidden">
							<table border="0" cellpadding="0" cellspacing="0" class="item-list maxw">
								<tr class="list-header">
									<td width="11" align="center"><img src="images/minus.gif" onclick="return onItemDetailsClose(this, <?php echo $section['id'] ?>)" style="cursor: pointer;"></td>
									<td width="40" align="center"><a href="#" class="tbltitle" onclick="return onTblTitleClick(this, <?php echo $section['id'] ?>, 'items.id')">номер</a></td>
									<td width="40" align="center"><a href="#" class="tbltitle" onclick="return onTblTitleClick(this, <?php echo $section['id'] ?>, 'items.art')">артикул</a></td>
									<td align="center"><a href="#" class="tbltitle bold" onclick="return onTblTitleClick(this, <?php echo $section['id'] ?>, 'items.name')">наименование</a></td>
									<td width="50" align="center"><a href="#" class="tbltitle" onclick="return onTblTitleClick(this, <?php echo $section['id'] ?>, 'updates.time')">дата</a></td>
									<td width="50" align="center"><a href="#" class="tbltitle" onclick="return onTblTitleClick(this, <?php echo $section['id'] ?>, 'items.price')">цена</a></td>
									<td width="50" align="center">ntc</td>
									<td width="50" align="center"><a href="#" class="tbltitle" onclick="return onTblTitleClick(this, <?php echo $section['id'] ?>, 'items.warranty')">гарантия</a></td>
								</tr>
							</table>
						</div>
					</div>
				<?php
			}
		}
	?>
	<script type="text/javascript">
		last_update = '<?php echo date('Y-m-d', $last_update['time']); ?>';

		$.ajaxSetup({async: false});
		$('div.section').data('sortby', 'items.name');

		$('.section-title').click(function(info) {
			//console.info(this);
			//console.info(a);
			//console.info(info.target);
			if ( info.target.nodeName != 'INPUT' ) {
				section = this.parentNode;

				if ( $(section).data('updated') ) {

				} else {
					//$('div.section-loading', section).toggleClass('hidden');
					UpdateItemList(section);
					//$('div.section-loading', section).toggleClass('hidden');
				}
				$('div.section-body', section).toggleClass('hidden');
			}
		});
		
		$('.section-title').hover(
			function () { $(this).addClass("section-title-hover"); },
			function () { $(this).removeClass("section-title-hover"); }
		);
		
//		$('div.header table#info').dblclick(function(obj) {
//			$('div.section-body', $('div.header')).toggleClass('hidden');
//		});

		$('select.control').bind('click', function(event) {
			event.stopPropagation();
		});

		$('input.control').bind('click', function(event) {
			event.stopPropagation();
		});

		$('div#reset').bind('click', function(event) {
			event.stopPropagation();
		});

		function onTblTitleClick ( obj, sectid, sortby ) {
			//console.info(obj);
			section = $('div.section#' + sectid);
			$('a', obj.parentNode.parentNode).removeClass('bold');
			$(obj).addClass('bold');
			$(section).data('sortby', sortby);
			UpdateItemList(section);
			return false;
		}

		function OnFilterEditEnter ( obj, event, sectid ) {
			if ( event && event.keyCode == 13 ) {
				section = $('div.section#' + sectid);
				UpdateItemList(section);
				$('div.section-body', section).toggleClass('hidden', false);
				return false;
			}
			return true;
		}
		
		function OnFilterAllEditEnter ( obj, event ) {
			if ( event && event.keyCode == 13 ) {
//				$.getJSON('index.php?action=get_all_items', function(data) {
//					//console.info(data);
//				});
//				return;
				//console.info(obj);
				//console.info(obj.value);
				sections = $('div.section');
				$.each(sections, function(index, item) {
					//console.info(item.id);
					section = $('div.section#' + item.id);
					$('input#filter', section).val(obj.value);
					UpdateItemList($('div.section#' + item.id));
					found = $('span#items-count', section).text();
					if ( found == 0 ) {
						section.hide();
					} else {
						section.show();
						$('.section-title', section).dblclick();
					}
				});
				return false;
			}
			return true;
		}

		function OnFilterReset( obj, event, sectid) {
			section = $('div.section#' + sectid);
			$('input#chknew',  section).attr('checked', false);
			$('input#filter',  section).val('');
			$('select#filter', section).val('latest');
			UpdateItemList(section);
		}

		function onItemDetailsClose ( img, sid ) {
			list = $('div.section#' + sid + ' table.item-list td.plus img');
			$.each(list, function(index, item){
				//console.info(item);
				//onItemDetailsClick(item);
				tr = $(item.parentNode.parentNode);
				id = item.id;
				if ( item.open ) {
					item.open = false;
					item.src = 'images/plus.gif';
					tr.removeClass('trselect');
					$('#'+id+'-data', tr.parentNode).remove();
				}
			});
		}

		function onItemDetailsClick ( img ) {
			tr = $(img.parentNode.parentNode);
			id = img.id;
			if ( img.open ) {
				img.open = false;
				img.src = 'images/plus.gif';
				tr.removeClass('trselect');
				$('#'+id+'-data', tr.parentNode).remove();
			} else {
				img.open = true;
				img.src = 'images/minus.gif';
				tr.addClass('trselect');
				url = 'index.php?db=<?php echo $db; ?>&action=get_item_prices&id=' + id + '&width=' + tr.width() ;
				//tr.after('<tr class="list-item" id="'+id+'-data"><td style="padding:1px" colspan="7"><div class="itinline">' + $('div.section-loading').html() + '</div></td></tr>');
				tr.after('<tr class="list-item" id="'+id+'-data"><td style="padding:1px; background-color:#fff" colspan="8"><img src="' + url + '"/></td></tr>');
//				$.getJSON('index.php?db=<?php echo $db; ?>&action=get_item_prices&id=' + id, function(data) {
//					prices = '';
//					$.each(data, function(index, item) {
//						prices = prices + '<td align="center">' + item.time + '</td>';
//					});
//					prices = '<tr>' + prices + '</tr><tr>';
//					$.each(data, function(index, item) {
//						prices = prices + '<td align="center">' + item.price + '</td>';
//					});
//					prices = '<table border="0" cellpadding="0" cellspacing="0" class="item-list">' + prices + '</tr></table>';
//					//console.info($('#'+id+'-data td', tr.parentNode));
//					$('#'+id+'-data td', tr.parentNode).html(prices);
//				});
			}
		}

		function UpdateItemList ( section ) {
			$sid = $(section).attr('id');
			if ( $sid ) {
				tblist  = $('.item-list', section);
				tbitems = $('.list-item', tblist);
				chknew  = $('input#chknew', section).attr('checked') ? 1 : 0;
				inname  = $('input#filter', section).val();
				sortby  = $(section).data('sortby');
				tbitems.remove();
				diff_neg = 0;
				diff_pos = 0;
				$('div.section-title #items-count', section).html('<img src="images/ajax-loader-small.gif" alt="loading">');
				$.getJSON('index.php?db=<?php echo $db; ?>&action=get_items&sid=' + $sid + '&filter=' + $('select#filter', section).val() + '&chknew=' + chknew + '&inname=' + inname + '&sortby=' + sortby, function(data) {
					$('div.section-title #items-count', section).html(data.length);
					$.each(data, function(index, item) {
						//console.info(item);
						if ( item.is_new == 1 ) {
							item.id = '<b>' + item.id + '</b>';
						}
						price = item.price;
						if ( last_update == item.time ) {
							item.price = '<b>' + item.price + '</b>';
						}
						pstyle = '';
						if ( item.diff < 0 ) {
							diff_neg = diff_neg + item.diff;
							pstyle = 'color:blue;';
						}
						if ( item.diff > 0 ) {
							diff_pos = diff_pos + item.diff;
							item.diff = '+' + item.diff;
							pstyle = 'color:red;';
						}
						item.ntc = (item.ntc ? (item.ntc < price ? '<span style="color:navy;">' + item.ntc + '</span>' : (item.ntc == price ? '<span style="color:grey;">' + item.ntc + '</span>' : '<span style="color:red;">' + item.ntc + '</span>')) : '');
						$(tblist).append('<tr class="list-item" id="'+item.id+'"><td align="center" class="plus"><img id="'+item.id+'" style="cursor:pointer;" onclick="return onItemDetailsClick(this)" src="images/plus.gif"/></td><td align="right" id="id_item">'+item.id+'</td><td style="white-space:nowrap;">'+item.art+'</td><td class="itname">'+item.name+'</td><td>'+item.time+'</td><td nowrap align="right" style="'+pstyle+'" title="'+item.diff+'$">'+item.price+'</td><td align="right">'+item.ntc+'</td><td align="right">'+item.warranty+'</td></tr>');
					});
					if ( inname ) {
						$.each(inname.split(' '), function(index, item) {
							$('td.itname', tblist).highlight(item);
						});
					}
					if ( diff_pos ) {
						diff_pos = '<span style="color:red;">+' + diff_pos + '</span>';
					} else diff_pos = '';
					if ( diff_neg ) {
						diff_neg = '<span style="color:blue;">' + diff_neg + '</span>';
					} else diff_neg = '';
					$('div.section-title td#diff-value', section).html(diff_pos + ((diff_pos && diff_neg) ? '/' : '') + diff_neg);

					$(section).data('updated', true);

					$('.section-body table tr', section).mouseover(function(){
						$(this).addClass('trover');
					});
					$('.section-body table tr', section).mouseout(function(){
						$(this).removeClass('trover');
					});

//					$('.section-body table tr', section).bind('dblclick', function() {
//						trow = this;
//						$(trow).toggleClass('trselect');
//						if ( $(trow).hasClass('trselect') ) {
//							$('td.itname', trow).append('<div class="itinline">' + $('div.section-loading').html() + '</div>');
//							id_item = this.id;
//							if ( id_item ) {
//								prices  = '';
//								$.getJSON('index.php?db=<?php echo $db; ?>&action=get_item_prices&id=' + id_item, function(data) {
//									$.each(data, function(index, item) {
//										prices = prices + '<td align="center">' + item.time + '</td>';
//									});
//									prices = '<tr>' + prices + '</tr><tr>';
//									$.each(data, function(index, item) {
//										prices = prices + '<td align="center">' + item.price + '</td>';
//									});
//									prices = '<table border="0" cellpadding="0" cellspacing="0" class="item-list">' + prices + '</tr></table>';
//									//console.info(data);
//									$('div.itinline', trow).html(prices);
//								});
//							}
//						} else {
//							$('div.itinline', trow).remove();
//						}
//					});
				});
				//$('div.section-body', section).toggleClass('hidden', false);
			}
		}
		
		$(document).ready(function() {
			$(".tabLink").each(function(){
				$(this).click(function(){
					tabeId = $(this).attr('id');
					$(".tabLink").removeClass("activeLink");
					$(this).addClass("activeLink");
					$(".tabcontent").addClass("hide");
					$("#"+tabeId+"-1").removeClass("hide")   
					return false;	  
				});
			});  
		});

	</script>
</body>
</html>
