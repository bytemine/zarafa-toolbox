zarafa_discover_and_purge_old.php
==================================

This script is a cleaner-script for your zarafa stores.
You can configure a variable of days and items older than this limit will be purged
from your zarafa stores.

How the script works
====================

It selects all users from the corresponding database table. For each user it tries
to open the store and walks the hierarchy of folders. For users without a corresponding
store, it will output a warning.

The configuration has a safety setting that should prevent you from accidently deleting too
much.

Currently the script will 'soft-delete' the items. 


Configuration settings explained
=================================

Without the 'debug' setting, the output will be very limited. Depending on 'debug_level'
the output will be more verbose. Currently the levels 1 and 2 are used throughout the script.

$delete = false;
$delete_really = false;


If you want to limit the users chosen, you can add them to the '$limit_user' array. If this
is empty, the script will walk over all users.

If you only want to blacklist a few users, add them to the 'blacklisted_users' array.

Depending on what items you want to purge, you can add these to the 'delete_types':

# limit the types of items to delete
$delete_types = array("IPM.Note");

