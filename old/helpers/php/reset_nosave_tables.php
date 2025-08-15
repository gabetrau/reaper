<?php
// Author: Gabriel Rau

// input variables
$servername = $argv[1];
$db = $argv[2];
$tables = explode(" ", $argv[3]);
$emailrecipient = $argv[4];

// name of possible file from prior run
$filename = "{$servername}.{$db}.json";
// detect if json file was changed at all for loggings
$beenChanged=false;

if (file_exists($filename)) {
    $jsonStr = file_get_contents($filename);
    $jsonArray = json_decode($jsonStr, true);
    if (!$jsonArray) {
        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Json decode for file '$servername.$db.json' failed. " . strval($jsonArray) .  "\n", 3, "tmp/logs/reaper_out.log");
        if (!file_exists("tmp/notify.txt")) {
            error_log("Reaper encountered an error when trying to remove nosave tables from json file", 1, "$emailrecipient");
            file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
        }
        exit(1);
    }
    foreach ($tables as $t) {
        if (array_key_exists($t, $jsonArray['primaryKeys'])) {
            $jsonArray['primaryKeys']["$t"] = 0;
        }
        if (array_key_exists($t, $jsonArray['rows'])) {
            if (!$beenChanged) {
                $beenChanged=true;
            }
            $jsonArray['rows']["$t"] = 0;
        }
        if (file_put_contents($filename, json_encode($jsonArray), LOCK_EX) === false) {
            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Json decode for file '$servername.$db.json' failed. " . strval($jsonArray) .  "\n", 3, "tmp/logs/reaper_out.log");
            if (!file_exists("tmp/notify.txt")) {
                error_log("Reaper encountered an error when trying to remove nosave tables from json file", 1, "$emailrecipient");
                file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
            }
            exit(1);
        }
    }
}

if ($beenChanged) {
    file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] Changed '$servername.$db.json' to reset tables specified in config\n", FILE_APPEND | LOCK_EX);
}

?>