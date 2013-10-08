#!/usr/bin/php
<?PHP

#
# Author:  Holger Rasch <rasch@bytemine.net>
#          Felix Kronlage <kronlage@bytemine.net>
#
# http://www.bytemine.net/
#
# 
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
# 
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
# 2. The name of the author may not be used to endorse or promote products
#    derived from this software without specific prior written permission.
# 
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL
# THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
# EXEMPLARY, OR CONSEQUENTIAL  DAMAGES (INCLUDING, BUT NOT LIMITED TO,
# PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
# OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
# WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
# OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
# ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
#



####
# Purpose: to remove items from ZCP stores that are older than the configured limit.
# Definition of 'older': The script evaluates PR_CREATION_TIME which is the point in time, when
# the item was created ON THE ZCP system.
#
# The script was written alongside with the documentation released by Zarafa here:
# https://community.zarafa.com/php-ext/reference.html
#
####

require "/usr/share/php/mapi/mapi.util.php";
require "/usr/share/php/mapi/mapiguid.php";
require "/usr/share/php/mapi/mapidefs.php";
require "/usr/share/php/mapi/mapicode.php";
require "/usr/share/php/mapi/mapitags.php";

include "purge.cfg.php";

function fail ($txt) {
  echo "ERROR:".$txt."\n";
  exit(1);
}

function debug_print($handle, $string, $level_output) {
	global $debug, $debug_level;

	if($level_output <= $debug_level) {
		if(($string != "") && ($debug)) {
			if($handle)
				fprintf($handle, '%s: %s', date('Y-m-d G:i:s', mktime()), $string);
			else
				printf('%s: %s', date('Y-m-d G:i:s', mktime()), $string);
		}
	}
}

function discover_old_items($session, $id, $userid, $username, $limit) {

	# calculate offset
	$cut = mktime() - $limit;

	$store = mapi_openmsgstore($session, $id);
	$user = mapi_msgstore_createentryid($store, $username);
	$store = mapi_openmsgstore($session, $user);

	if(!$store) {
		print "\n\n----> Failed to open store for user '$username'!\n";
		print "----> Please check wether the user has a store associated!\n\n";
		return;
	}

	$root = mapi_msgstore_openentry($store, null);
	$storeprops = mapi_getprops($store, array(PR_DISPLAY_NAME));

	# descending into the user store
	print "\n===============================================================\n";
	print "Store: " . $storeprops[PR_DISPLAY_NAME];
	print "\n---------------------------------------------------------------\n";
	print_and_purge_hier ($session, $store, $root, $username, $cut);
	print "---------------------------------------------------------------\n\n";

}

function print_and_purge_hier ($sess, $store, $folder, $username, $cut) {
	global $delete_field, $delete_types, $delete, $debug, $debug_level, $delete_limit, $log_path;
	
	$log = fopen("$log_path/$username.txt", "w") or fail("Error opening user log\n");
	$store_log = fopen("$log_path/store-log.txt", "w") or fail("Error opening store log\n");

	$store_counter = 0;
	$store_counter_delete = 0;

	$storeprops = mapi_getprops($store, array(PR_DISPLAY_NAME));

	# get the hierarchietable in order to be able to walk the store in a convenient way
	$table = mapi_folder_gethierarchytable($folder, CONVENIENT_DEPTH);
	if (! $table)
		return;
	
	# get all rows
	$rows = mapi_table_queryallrows($table, Array(PR_DEPTH, PR_ENTRYID, PR_CONTAINER_CLASS, PR_DISPLAY_NAME));

	# descend into folder
	foreach ($rows as $row) {
		$folder_counter = 0;
		$folder_counter_delete = 0;
		$purge_elements_container = array();

		if (isset($row[PR_CONTAINER_CLASS])) {
			debug_print($log, $row[PR_DEPTH]." [".$row[PR_CONTAINER_CLASS]."] ".$row[PR_DISPLAY_NAME]."\n", 2);
		} else {
			debug_print($log, $row[PR_DEPTH]." [] ".$row[PR_DISPLAY_NAME]."\n", 2);
		}
		
		# get the folder table
		$folder = mapi_msgstore_openentry($store, $row[PR_ENTRYID]);
		$table = mapi_folder_getcontentstable($folder);
		if (! $table)
			continue;

		# query the folder for all elements
		$rows_folder = mapi_table_queryallrows($table, Array(PR_ENTRYID, PR_MESSAGE_CLASS, PR_MESSAGE_SIZE, PR_SUBJECT, PR_CREATION_TIME, PR_MESSAGE_DELIVERY_TIME));
		if (! $rows_folder)
			continue;
		
		$rows_folder_count= count($rows_folder);

		# logic for assuring that we only delete a configured number of elements per call
		if($rows_folder_count >= $delete_limit) {
			debug_print("", sprintf("Folder '%s' has %s Elements.\n", $row[PR_DISPLAY_NAME], $rows_folder_count), 1);

			$i = count($rows_folder) / $delete_limit;
			$j = 0;

			while($j <= $i) {
				$purge_elements_container[$j]= array();
				$j++;
				debug_print($log, sprintf("purge_elements_counter: %s\n", count($purge_elements_container)), 2);
			}
		} else {
			debug_print("", sprintf("Folder '%s' has %s Elements.\n", $row[PR_DISPLAY_NAME], $rows_folder_count), 1);
			$purge_elements_container[0]= array();
		}

		$i = 0;
		$j = 0;

		# walking our array of elements from within the folder
		foreach ($rows_folder as $row_folder) {
			$folder_counter++;
			$store_counter++;
			#only proceed if delete_filed contains a value
			if(isset($row_folder[$delete_field])) {
				$cts = $row_folder[$delete_field];
				
				# IF the element is older than our configured expiration time 
				# AND it belongs to the type of elements we want to delete....
				if (($cut > $cts) && (in_array($row_folder[PR_MESSAGE_CLASS], $delete_types))) {
					debug_print($log, "  D ------------------> ", 1);
					$folder_counter_delete++;
					$store_counter_delete++;

					# ...place it in the purge_elements container
					array_push($purge_elements_container[$i], $row_folder[PR_ENTRYID]);
					$j++;
					if(($debug) && ($debug_level > 2))
						echo ".";

					if($j >= $delete_limit) {
						$i++;
						$j = 0;
					}
					if (isset($row_folder[PR_SUBJECT])) {
						debug_print($log, " [".$row_folder[PR_MESSAGE_CLASS]."] ".date('Y-m-d G:i:s', $cts)." - '".$row_folder[PR_SUBJECT]."'\n", 1);
					} else {
						debug_print($log, " [".$row_folder[PR_MESSAGE_CLASS]."] ".date('Y-m-d G:i:s', $cts)."'\n", 1);
					}
				}	
				if (isset($row_folder[PR_SUBJECT])) {
					debug_print($log, " [".$row_folder[PR_MESSAGE_CLASS]."] ".date('Y-m-d G:i:s', $cts)." - '".$row_folder[PR_SUBJECT]."'\n", 2);
				} else {
					debug_print($log, " [".$row_folder[PR_MESSAGE_CLASS]."] ".date('Y-m-d G:i:s', $cts)."'\n", 2);
				}
			}
		}

		debug_print($log, "Folder: ". $row[PR_DISPLAY_NAME] ."\tItems: ". $folder_counter ."\tDelete Items: ". $folder_counter_delete ."\n", 1);

		# now it's time to walk the elements we've looked up for deletion
		foreach($purge_elements_container as $purge_elements) {
			// skip the arrays that are empty
			if(count($purge_elements) > 0) {
				if($delete) {
					debug_print("", sprintf("Calling mapi_folder_deletemessages for %s elements in Folder '%s'\n", count($purge_elements), $row[PR_DISPLAY_NAME]), 1);
				} else {
					debug_print("", sprintf("Would call mapi_folder_deletemessages for %s elements in Folder '%s'\n", count($purge_elements), $row[PR_DISPLAY_NAME]), 1);
				}

				if($delete) {
					# and the actual delete call
					$delete_return = mapi_folder_deletemessages($folder, $purge_elements);
			
					if($delete_return == false) {
						printf("Error while purging messages from folder: '%s'\n", $row[PR_DISPLAY_NAME]);
						fail("...");
						exit;
					}
				}
			}
		}
	}

	debug_print($log, "Store: ".  $storeprops[PR_DISPLAY_NAME] ."\tItems: ". $store_counter ."\tDelete Items: ". $store_counter_delete ."\n", 1);
	debug_print($store_log, "Store: ".  $storeprops[PR_DISPLAY_NAME] ."\tItems: ". $store_counter ."\tDelete Items: ". $store_counter_delete ."\n", 1);
	debug_print("", "Store: ".  $storeprops[PR_DISPLAY_NAME] ."\tItems: ". $store_counter ."\tDelete Items: ". $store_counter_delete ."\n", 0);

	fclose($log);
	fclose($store_log);
}


if(($delete) && (!$delete_really)) {
	echo "Purge mode is turned on. Please make sure, you're aware of the consequences.\n";
	echo "If so, flip the 'delete_really' setting to true.\n\n";

	fail("...");
}


$limit = 86400 * $days;

// init zarafa connection
$session = mapi_logon_zarafa('SYSTEM', '', 'file:///var/run/zarafa');
if (mapi_last_hresult())
	fail ('failed to connect to zarafa');

$table = mapi_getmsgstorestable($session);
if (mapi_last_hresult())
	fail ('failed to initialize');

$rows = mapi_table_queryallrows($table, array(PR_ENTRYID, PR_DEFAULT_STORE));
if (mapi_last_hresult())
	fail ('failed to initialize');

$id = false;
foreach ($rows as $row) {
	if (isset ($row[PR_DEFAULT_STORE]) && $row[PR_DEFAULT_STORE]) {
		$id = $row[PR_ENTRYID];
		break;
	}
}

if (! $id)
	fail ('failed to initialize');

// init mysql connection
$link = mysql_connect("$mysqlhost:$mysqlport", "$mysqluser", "$mysqlpass");

if (!$link)
	fail('Could not connect: ' . mysql_error());

mysql_select_db($mysqldb, $link);

// handle log directory
if (empty($log_path)) {
	$log_path = getcwd();
	} else {
	if (!is_dir($log_path)) {
			if (!mkdir($log_path, 0, true)) {
				fail('Failed to create log directory ...');
			}         
	}
}
printf("Log path: %s\n", $log_path);

// working on the stores
$query="SELECT user_id,user_name FROM stores;";  

$result = mysql_query($query, $link) or die(mysql_error());

while($row = mysql_fetch_array($result)) {

	$userid= $row[0];
	$username= $row[1];

	printf("Next user is '%s'.\n", $username);

	if(! in_array(strtolower($username), array_map('strtolower',$blacklisted_users)) ) {
		if(count($limit_user) > 0) {	
			if (in_array(strtolower($username), array_map('strtolower', $limit_user))) {
				printf("Descending into user: %s\n", $username);
				discover_old_items($session, $id, $userid, $username, $limit);
			}
		} else {
			printf("Descending into user: %s\n", $username);
			discover_old_items($session, $id, $userid, $username, $limit);
		}
	} else {
		printf("User '%s' is blacklisted via config file. Skipping.\n", $username);
	}
}

mysql_close($link);

?>
