<?PHP

$mysqlhost="localhost";
$mysqlport="3306";
$mysqluser="root";
$mysqlpass="";
$mysqldb="zarafa";

$days = 365;

$debug = true;
$debug_level = 1;
$log_path = "/var/log/zarafa_discover_and_purge_old";

$delete = false;

# add users to limit
# example:
# $limit_user = array('fred');
$limit_user = array('');

# blacklisted_stores - add users you want to skip
$blacklisted_users = array('Everyone', 'SYSTEM');

# limit the types of items to delete
# Possible values:
# IPM.Contact
# IPM.Appointment
# IPM.Note (which is mails)
# IPM.Schedule.Meeting.Request
# IPM.Schedule.Meeting.Resp.Pos
# IPM.TaskRequest
# IPM.StickyNote
#
# and likely more
$delete_types = array("IPM.Note");

# PR_CREATION_TIME or
# PR_MESSAGE_DELIVERY_TIME
#$delete_field = PR_CREATION_TIME;
$delete_field = PR_MESSAGE_DELIVERY_TIME;

# number of items per delete call
$delete_limit = 1000;

$delete_really = false;

?>
