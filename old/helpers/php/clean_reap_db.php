<?php
// Author: Gabriel Rau

// input variables
$servername = $argv[1];
$username = $argv[2];
$password = $argv[3];
$db = $argv[4];
$emailrecipient = $argv[5];

// mysql connection
$conn = new mysqli( $servername, $username, $password, $db );

if ( $conn->connect_error ) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Connection to $db on $servername failed! $conn->connect_error\n", 3, "tmp/logs/reaper_out.log");
    if (!file_exists("tmp/notify.txt")) {
        error_log("Reaper encountered an error when trying to clean $db on $servername", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    exit(1);
}

if (strcmp($db, "usa_4_0_0") === 0) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Not allowed to run with '$db'\n", 3, "tmp/logs/reaper_out.log");
    if (!file_exists("tmp/notify.txt")) {
        error_log("Reaper encountered an error when trying to clean $db on $servername", 1, "$emailrecipient");
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
        error_log("Reaper encountered an error when trying to clean $db on $servername", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    $conn->close();
    exit(1);
}

// Remove any tables that are currently in temporary db
$showTablesQuery = "SHOW TABLES";
$showTablesResult = $conn->query($showTablesQuery);
if ($showTablesResult->num_rows <= 0) {
    $turnOnForeignKeyConstraintsResult = $conn->query($turnOnForeignKeyConstraintsQuery);
    if (!$turnOnForeignKeyConstraintsResult) {
        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $conn->error\n", 3, "tmp/logs/reaper_out.log");
    }
    $conn->close();
    exit();
}

$dropTablesQuery = "DROP TABLE IF EXISTS ";
foreach ($showTablesResult as $row) {
    $dropTablesQuery .= $row["Tables_in_$db"] . ", ";
}
$dropTablesQuery = rtrim($dropTablesQuery, ", ");
$dropTablesResult = $conn->query($dropTablesQuery);

if ($dropTablesResult === FALSE) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: could not clean '$db'. $dropTablesQuery\n", 3, "tmp/logs/reaper_out.log");
    if (!file_exists("tmp/notify.txt")) {
        error_log("Reaper encountered an error when trying to clean $db on $servername", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    $turnOnForeignKeyConstraintsResult = $conn->query($turnOnForeignKeyConstraintsQuery);
    if (!$turnOnForeignKeyConstraintsResult) {
        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $conn->error\n", 3, "tmp/logs/reaper_out.log");
    }
    $conn->close();
    exit(1);
}

// close connection
$turnOnForeignKeyConstraintsResult = $conn->query($turnOnForeignKeyConstraintsQuery);
if (!$turnOnForeignKeyConstraintsResult) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $conn->error\n", 3, "tmp/logs/reaper_out.log");
}
$conn->close();
file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] Cleaned $db on $servername\n", FILE_APPEND | LOCK_EX);
?>