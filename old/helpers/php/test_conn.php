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
        error_log("Reaper encountered an error when trying to connect to $db on $servername", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    exit(1);
} else {
    file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] Successfully connected to $db on $servername\n", FILE_APPEND | LOCK_EX);
}
//close connection
$conn->close();
?>
