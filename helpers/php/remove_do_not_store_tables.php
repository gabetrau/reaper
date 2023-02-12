<?php
// Author: Gabriel Rau

// input variables
$servername = $argv[1];
$username = $argv[2];
$password = $argv[3];
$db = $argv[4];
$tables = explode(" ", $argv[5]);
$emailrecipient = $argv[6];

// mysql connection
$conn = new mysqli( $servername, $username, $password, $db );

if ( $conn->connect_error ) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Connection to $db on $servername failed! $conn->connect_error\n", 3, "tmp/logs/reaper_out.log");
    if (!file_exists("tmp/notify.txt")) {
        error_log("Reaper encountered an error when trying to remove do not store tables from $db on $servername", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    exit(1);
}

if (strcmp($db, "usa_4_0_0") === 0) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Not allowed to run with '$db'\n", 3, "tmp/logs/reaper_out.log");
    if (!file_exists("tmp/notify.txt")) {
        error_log("Reaper encountered an error when trying to remove do not store tables from $db on $servername", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    exit(1);
}

// disable keys constraints
$turnOffForeignKeyConstraintsQuery = "SET FOREIGN_KEY_CHECKS=0";
$turnOnForeignKeyConstraintsQuery = "SET FOREIGN_KEY_CHECKS=1";
$turnOffForeignKeyConstraintsResult = $conn->query($turnOffForeignKeyConstraintsQuery);
if (!$turnOffForeignKeyConstraintsResult) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while disabling key constraints $conn->error\n", 3, "tmp/logs/reaper_out.log");
    if (!file_exists("tmp/notify.txt")) {
        error_log("Reaper encountered an error when trying to remove do not store tables from $db on $servername", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    $conn->close();
    exit(1);
}

// Remove data of any tables that are currently in temporary db
$truncateQuery = "";
$truncateFailed = false;
foreach ($tables as $t) {
    $truncateQuery .= "truncate " . $t . ";";
}
$dropTablesResult = $conn->multi_query($truncateQuery);
do {
    if ($result = $conn->store_result()) {
        if (!$result) {
            $truncateFailed = true;
            break;
        }
    }
} while ($conn->next_result());

$turnOnForeignKeyConstraintsResult = $conn->query($turnOnForeignKeyConstraintsQuery);
if (!$turnOnForeignKeyConstraintsResult) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $conn->error\n", 3, "tmp/logs/reaper_out.log");
}

if ($truncateFailed) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while truncating '$db' on '$servername' - $statusMultiQuery\n", 3, "tmp/logs/reaper_out.log");
    if (!file_exists("tmp/notify.txt")) {
        error_log("Reaper encountered an error when trying to remove do not store tables from $db on $servername", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    $conn->close();
    exit(1);
}

// close connection
$conn->close();
file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] Removed tables without ids in $db on $servername\n", FILE_APPEND | LOCK_EX);
?>
