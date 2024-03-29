--TEST--
PostgreSQL notice function
--EXTENSIONS--
pgsql
--SKIPIF--
<?php

include("inc/skipif.inc");

_skip_lc_messages($conn);

?>
--FILE--
<?php
include 'inc/config.inc';
include 'inc/lcmess.inc';

ini_set('pgsql.log_notice', TRUE);
ini_set('pgsql.ignore_notice', FALSE);

$db = pg_connect($conn_str);

_set_lc_messages($db);

$res = pg_query($db, 'SET client_min_messages TO NOTICE;');
var_dump($res);

// Get empty notice
var_dump(pg_last_notice($db));
var_dump(pg_last_notice($db, PGSQL_NOTICE_ALL));

pg_query($db, "BEGIN;");
pg_query($db, "BEGIN;");
pg_query($db, "BEGIN;");
pg_query($db, "BEGIN;");

// Get notices
var_dump(pg_last_notice($db));
var_dump(pg_last_notice($db, PGSQL_NOTICE_ALL));

// Clear and get notices
var_dump(pg_last_notice($db, PGSQL_NOTICE_CLEAR));
var_dump(pg_last_notice($db, PGSQL_NOTICE_LAST));
var_dump(pg_last_notice($db, PGSQL_NOTICE_ALL));

// Invalid option
try {
    var_dump(pg_last_notice($db, 99));
} catch (\ValueError $e) {
    echo $e->getMessage() . \PHP_EOL;
}
?>
--EXPECTF--
object(PgSql\Result)#%d (0) {
}
string(0) ""
array(0) {
}

Notice: pg_query(): %s already a transaction in progress in %s on line %d

Notice: pg_query(): %s already a transaction in progress in %s on line %d

Notice: pg_query(): %s already a transaction in progress in %s on line %d
string(52) "WARNING:  there is already a transaction in progress"
array(3) {
  [0]=>
  string(52) "WARNING:  there is already a transaction in progress"
  [1]=>
  string(52) "WARNING:  there is already a transaction in progress"
  [2]=>
  string(52) "WARNING:  there is already a transaction in progress"
}
bool(true)
string(0) ""
array(0) {
}
pg_last_notice(): Argument #2 ($mode) must be one of PGSQL_NOTICE_LAST, PGSQL_NOTICE_ALL, or PGSQL_NOTICE_CLEAR
