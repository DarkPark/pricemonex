<?php

set_time_limit(0);

error_reporting(E_ALL ^ E_DEPRECATED);

define('EOL', "\n");
define('app_path', dirname(__FILE__));

include 'lib/firephp/fb.php';

//$mbox_uri  = "{imap.pochta.ru/imap/ssl/novalidate-cert}INBOX.&BBAEQARFBDgEMg-.Neologic";
$mbox_uri  = "{imap.pochta.ru/imap/ssl/novalidate-cert}&BBAEQARFBDgEMg-/Neologic";
$mbox_user = 'darkpark@pisem.net';
$mbox_pass = 'u9g785pkeb3x';

date_default_timezone_set('Europe/Kiev');

header('Content-Type:text/plain');

$time_start = time();
$log_buffer = '';
$log_file   = app_path . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'neologic.' . date('Ym') . '.log';

$list_files      = array();
$list_sections   = array();
$list_items      = array();
$list_items_prev = array();

$sections_added = 0;
$items_added    = 0;
$items_updated  = 0;

// db connection
try {
	$dbh = new PDO('sqlite:' . app_path . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'neologic.sqlite');
} catch ( PDOException $e ) {
	die('Connection failed: '.$e->getMessage());
}

$dbh->beginTransaction();

$dbh->query('PRAGMA encoding = "UTF-8"');

// get old updates
$buffer  = $dbh->query('select id, id_message from updates');
$buffer  = $buffer->fetchAll(PDO::FETCH_ASSOC);
$updates = array();
foreach ( $buffer as $update ) {
    $updates[$update['id_message']] = $update['id'];
}

$dbh->query(sprintf('insert into checks (time_start) values (%s)', $time_start));
$check_id = $dbh->lastInsertId();
$updates_count = 0;

// check mail
if ( ($mbox = imap_open($mbox_uri, $mbox_user, $mbox_pass, OP_READONLY)) ) {
	//print_r(imap_list($mbox, '{imap.pochta.ru/imap/ssl/novalidate-cert}', '*'));
	// iterate messages
    for ( $i = 1; $i <= imap_num_msg($mbox); ++$i ) {
    //for ( $i = 1; $i <= 2; ++$i ) {
        $header   = imap_header($mbox, $i);
        $msg_id   = trim($header->message_id, '<>');
        $msg_time = $header->udate;

        // if not an old one
        if ( !isset($updates[$msg_id]) ) {
			$structure = imap_fetchstructure($mbox, $i);
			if ( $structure && isset($structure->parts[1]) && $structure->parts[1]->subtype == 'OCTET-STREAM' ) {
				$filename = $structure->parts[1]->parameters[0]->value;
				// need to make sure it's a price
				if ( stripos($filename, 'price') !== false ) {
					$updates_count++;
					mlog("File: $filename");
					mlog("\tid :: " . $msg_id);

					$dbh->query(sprintf("insert into updates (id_check, id_message, file, time) values (%s, '%s', '%s', %s)", $check_id, $msg_id, $filename, $msg_time));
					//$dbh->query(sprintf("insert into updates (id_check, id_message, file, time) values (%s, '%s', '%s', %s)", $check_id, time(), $filename, $msg_time));
					$update_id = $dbh->lastInsertId();

					$filename = app_path . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $filename;
					$body = imap_fetchbody($mbox, $i, 2);
					if ( file_put_contents($filename, imap_base64($body)) === false ) {
						mlog("\tsaved :: fail");
					} else {
						mlog("\tsaved :: ok");
						if ( ($xlsfile = ezip($filename)) ) {
							mlog("\textracted :: ok");
							unlink($filename);
						} else {
							mlog("\textracted :: fail");
						}

						// fill xls files query
						if ( isset($xlsfile) && is_file($xlsfile) ) {
							$list_files[] = array(
								'file' => $xlsfile,
								'uid'  => $update_id
							);
						}
					}
				}
			}
		}
    }

    imap_close($mbox);
} else {
    echo "Can't connect: " . imap_last_error() . EOL;
}

if ( $list_files ) {
	PrepareDictionaries();
	mlog();

	foreach ( $list_files as $file_data ) {
		$filename  = $file_data['file'];
		$update_id = $file_data['uid'];
		$xls_data  = GetXlsData($filename);

		$list_items_prev = array();
		$buffer = $dbh->query('select id from items where id_update = ' . ($update_id - 1));
		$buffer = $buffer->fetchAll(PDO::FETCH_ASSOC);
		if ( $buffer ) {
			foreach ( $buffer as $value ) {
				$list_items_prev[$value['id']] = true;
			}
		}

		mlog('File: ' . basename($filename));
		if ( $xls_data ) {
			mlog("\tparsed :: ok");

			$sections_added = 0;
			$items_added    = 0;
			$items_updated  = 0;

			// reset all is_new flags
			$dbh->query('update items set is_new = 0');

			foreach ( $xls_data as $section_name => $section_items ) {
				$section_id = get_section_id($section_name);
				if ( $section_items ) {
					foreach ( $section_items as $item_id => $item_data ) {
						$item_id = get_item_id($item_id, $section_id, $update_id, $item_data['art'], $item_data['name'], $item_data['price'], $item_data['warranty']);
						$dbh->query(sprintf("insert into info (id_update, id_item, price) values (%s, %s, %s)", $update_id, $item_id, $item_data['price']));
					}
				}
			}

			mlog("\tsections added :: $sections_added");
			mlog("\titems added    :: $items_added");
			mlog("\titems updated  :: $items_updated");

			$dbh->query('update sections set is_active = 0');
			$section_order = array_keys($xls_data);
			foreach ( $section_order as $order => $section_name ) {
				$dbh->query(sprintf('update sections set is_active = 1, id_update = %s, "order" = %s where id = %s', $update_id, $order, get_section_id($section_name)));
			}
		} else {
			mlog("\tparsed :: fail");
		}

		rename($filename, app_path . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'xls' . DIRECTORY_SEPARATOR . 'neologic.xls');
	}
}

$dbh->query('update sections set items_total = (select count(id) from items where items.id_section = sections.id)');
$dbh->query('update sections set items_last = (select count(id) from items where items.id_section = sections.id and items.id_update = sections.id_update)');
$dbh->query('update sections set items_new = (select count(id) from items where items.id_section = sections.id and items.is_new = 1 and items.id_update = sections.id_update)');

$dbh->query('update sections set sum_inc = (select sum(price_diff) from items where items.id_section = sections.id and items.price_diff > 0 and items.id_update = sections.id_update)');
$dbh->query('update sections set sum_dec = (select sum(price_diff) from items where items.id_section = sections.id and items.price_diff < 0 and items.id_update = sections.id_update)');

$dbh->query(sprintf('update checks set duration = %s, updates = %s where id = %s', time()-$time_start, $updates_count, $check_id));

$dbh->commit();

/**
 * Get the existing section id or creates a new one
 * @global PDO $dbh
 * @global array $list_sections
 * @param string $sname
 * @return int
 */
function get_section_id ( $sname ) {
	global $dbh, $list_sections, $sections_added;

	if ( isset($list_sections[$sname]) ) {
		return $list_sections[$sname];
	} else {
		$dbh->query(sprintf("insert into sections (name, id_update) values ('%s', 0)", $sname));
		$id = $dbh->lastInsertId();
		if ( $id ) {
			$list_sections[$sname] = $id;
			$sections_added++;
			return $id;
		} else {
			print_r($dbh->errorInfo());
		}
	}
}

function get_item_id ( $id, $sid, $uid, $art, $name, $price, $warranty ) {
	global $dbh, $list_items, $list_items_prev, $items_added, $items_updated;

	if ( !isset($list_items[$id]) ) {
		$dbh->query(sprintf("insert into items (id, id_section, id_update, art, name, price, price_diff, warranty, is_new) values (%s, %s, %s, '%s', '%s', %s, 0, %s, 1)", $id, $sid, $uid, $art, $name, $price, $warranty));
		$items_added++;
		$list_items[$id] = true;
	} else {
		$is_new = isset($list_items_prev[$id]) ? 0 : 1;
		$dbh->query(sprintf("update items set id_section = %s, art = '%s', name = '%s', price = %s, price_diff = '%s' - price, id_update = %s, warranty = '%s', is_new = %s where id = '%s'", $sid, $art, $name, $price, $price, $uid, $warranty, $is_new, $id));
		$items_updated++;
	}
	return $id;
}

function PrepareDictionaries () {
	global $dbh, $list_sections, $list_items;

	$buffer = $dbh->query('select id, name from sections');
	$buffer = $buffer->fetchAll(PDO::FETCH_ASSOC);
	foreach ( $buffer as $value ) {
		$list_sections[$value['name']] = $value['id'];
	}

	$buffer = $dbh->query('select id from items');
	$buffer = $buffer->fetchAll(PDO::FETCH_ASSOC);
	foreach ( $buffer as $value ) {
		$list_items[$value['id']] = true;
	}
}

function ezip ( $filename ) {
    $zip = zip_open($filename);
    if ( is_resource($zip) ) {
        $zip_entry = zip_read($zip);
        if ( zip_entry_open($zip, $zip_entry, "r") ) {
            $data = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
			$name = zip_entry_name($zip_entry);
            $name = dirname($filename) . DIRECTORY_SEPARATOR . microtime(true) . '.' .  $name;
            if ( file_put_contents($name, $data) !== false ) {
                return $name;
            }
        }
        zip_close($zip);
    }
}

function & GetXlsData ( $filename ) {
	include_once 'lib/excel/reader.php';

	// ExcelFile($filename, $encoding);
	$xls      = new Spreadsheet_Excel_Reader();
	$xls_data = array();

	// Set output Encoding.
	$xls->setOutputEncoding('UTF-8');

	error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED );

	$xls->read($filename);

	for ( $i = 6; $i <= $xls->sheets[0]['numRows']; $i++ ) {
		$row = $xls->sheets[0]['cells'][$i];
		if ( $row[1] && $row[2] && $row[4] ) {
			$xls_data[trim($row[1])][trim($row[2])] = array(
				'art'      => trim($row[3]),
				'name'     => trim($row[4]),
				'price'    => trim($row[5]),
				'warranty' => trim($row[8]),
			);
		}
	}

	return $xls_data;
}

function mlog ( $line = '' ) {
	global $log_buffer;

	$line = $line . EOL;
	echo $line;
	$log_buffer .= $line;
}

// add all logs to file
$log_buffer =
	date('Y.m.d H:i:s', $time_start) . ' - check started' . EOL .
	$log_buffer .
	date('Y.m.d H:i:s') . ' - check ended' . EOL . EOL . EOL;
file_put_contents($log_file, $log_buffer, FILE_APPEND);

?>
