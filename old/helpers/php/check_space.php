<?php
// Author: Gabriel Rau

// input variables
$servername = $argv[1];
$username = $argv[2];
$password = $argv[3];
$db = $argv[4];
$tables = explode("\n", $argv[5]);
$copyall = $argv[6];
$dontsavetables = $argv[7];
$emailrecipient= $argv[8];

// mysql connection
$conn = new mysqli( $servername, $username, $password, $db );
$totalKB = 0;

// look for file from previous run
$keystoreArray = array();
$previousRowsCopiedArray = array();
if (file_exists("$servername.$db.json")) {
    $previousReapContents = file_get_contents("$servername.$db.json");
    $jsonArray = json_decode($previousReapContents, true);
    if (!$jsonArray) {
        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Json decode for file '$servername.$db.json' failed. " . strval($jsonArray) .  "\n", 3, "tmp/logs/reaper_out.log");
        if (!file_exists("tmp/notify.txt")) {
            error_log("Reaper encountered an error when checking space required", 1, "$emailrecipient");
            file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
        }
        exit(1);
    }
    $keystoreArray = $jsonArray['primaryKeys'];
    $previousRowsCopiedArray = $jsonArray['rows'];
}

// used to save info for every table that is needed for calculating space
class tableInfo {
    public $name;
    public $previousLastId;
    public $previousRowsCopied;
    
    function __construct($name) {
        $this->name = $name;
    }
    
    public $dataLength;
    public $indexLength;
    public $rowNums;
    public $avgRowLength;
    
    public $indexCount;
    public $primaryKey;
    public $lastId;
}

// multi query strings  that will be executed for all tables
$statusMultiQuery = "";
$indexesMultiQuery = "";
$primaryKeysMultiQuery = "";
// array for the tableInfo objects
$tableInfosArray = array();
// Build multi queries
foreach ($tables as $table) {
    $t = new tableInfo($table);
    if (array_key_exists($table, $keystoreArray)) {
        $t->previousLastId = $keystoreArray["$table"];
    } else {
        $t->previousLastId = 0;
    }
    if (array_key_exists($table, $previousRowsCopiedArray)) {
        $t->previousRowsCopied = $previousRowsCopiedArray["$table"];
    } else {
        $t->previousRowsCopied = 0;
    }
    array_push($tableInfosArray, $t);
    $statusMultiQuery .= "SHOW TABLE STATUS WHERE name='{$table}';";
    $indexesMultiQuery .= "SELECT database_name, table_name, index_name, stat_value*@@innodb_page_size AS length FROM mysql.innodb_index_stats WHERE database_name='{$db}' AND table_name='{$table}' AND stat_name='size';";
    $primaryKeysMultiQuery .= "SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY';";
}

// run the status queries for all the tables to gather info
$x = 0;
$conn->multi_query($statusMultiQuery);
do {
    if ($result = $conn->store_result()) {
        if ($row = $result->fetch_assoc()) {
            $tableInfosArray[$x]->dataLength = $row['Data_length'];
            $tableInfosArray[$x]->indexLength = $row['Index_length'];
            $tableInfosArray[$x]->rowNums = $row['Rows'];
            $tableInfosArray[$x]->avRowLength = $row['Avg_row_length'];
            $x++;
        } else {
            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error during status checks on '$servername' using '$db' - $statusMultiQuery\n", 3, "tmp/logs/reaper_out.log");
            if (!file_exists("tmp/notify.txt")) {
                error_log("Reaper encountered an error when checking space required", 1, "$emailrecipient");
                file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
            }
            $conn->close();
            exit(1);
        }
    }
} while ($conn->next_result());

// If copy all is true we have all the info we need to calculate space
if (strcmp($copyall, "true") === 0) {
    for ($i = 0; $i < count($tableInfosArray); $i++) {
        $totalKB += round(($tableInfosArray[$i]->dataLength + $tableInfosArray[$i]->indexLength) / 1024, 2);
    }
    file_put_contents("tmp/size.txt", $totalKB);
    file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] Space requirements for $db on $servername is $totalKB KBs\n", FILE_APPEND | LOCK_EX);
    $conn->close();
    exit;
}

// Get the number of indexes for all of the tables and save to table info array
$x = 0;
$conn->multi_query($indexesMultiQuery);
do {
    if ($result = $conn->store_result()) {
        $numOfIndexes = $result->num_rows;
        $tableInfosArray[$x]->indexCount = $numOfIndexes;
        $x++;
    } else {
        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while retrieving indexes on '$servername' using '$db' - $indexesMultiQuery\n", 3, "tmp/logs/reaper_out.log");
        if (!file_exists("tmp/notify.txt")) {
            error_log("Reaper encountered an error when checking space required", 1, "$emailrecipient");
            file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
        }
        $conn->close();
        exit(1);
    }
} while ($conn->next_result());

// Get the names of each tables primary key and save to table info array
$x = 0;
$conn->multi_query($primaryKeysMultiQuery);
do {
    if ($result = $conn->store_result()) {
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $tableInfosArray[$x]->primaryKey = $row["Column_name"];
        } else {
            $tableInfosArray[$x]->primaryKey = "";
        }
        $x++;
    } else {
        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error when getting names of primary keys on '$servername' using '$db' - $primaryKeysMultiQuery\n", 3, "tmp/logs/reaper_out.log");
        error_log("Reaper encountered an error when checking space required", 1, "$emailrecipient");
        $conn->close();
        exit(1);
    }
} while ($conn->next_result());

// build multi query string. Needs primary key name
$lastIdsMultiQuery = "";
foreach ($tableInfosArray as $t) {
    if (empty($t->primaryKey)) {
        $lastIdsMultiQuery .= "SELECT * FROM $t->name LIMIT 1;";
    } else {
        $lastIdsMultiQuery .= "SELECT $t->primaryKey as keyVal FROM $t->name ORDER BY $t->primaryKey DESC LIMIT 1;";
    }
}

// get the id of the last record for each of the tables and save to table info array
$x = 0;
$conn->multi_query($lastIdsMultiQuery);
do {
    if ($result = $conn->store_result()) {
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (array_key_exists("keyVal", $row)) {
                $tableInfosArray[$x]->lastId = $row['keyVal'];
            } else {
                $tableInfosArray[$x]->lastId = 0;
            }
            $x++;
        }
    } else {
        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error when getting the last row of each table on '$servername' using '$db' - $lastIdsMultiQuery\n", 3, "tmp/logs/reaper_out.log");
        if (!file_exists("tmp/notify.txt")) {
            error_log("Reaper encountered an error when checking space required", 1, "$emailrecipient");
            file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
        }
        $conn->close();
        exit(1);
    }
} while ($conn->next_result());

// Logic to calculate space based on the table info gathered
foreach ($tableInfosArray as $t) {
    $tableSizeInKB = round(($t->indexLength + $t->dataLength) / 1024, 2);
    if (str_contains("$dontsavetables", "$t->name")) {
        $totalKB += $tableSizeInKB;
    } else {
        if (empty($t->primaryKey)) {
            if ($t->rowNums == 0 || $t->rowNums <= $t->previousRowsCopied) {
                continue;
            }
            $totalKB += $tableSizeInKB;
        } else {
            if ($t->lastId > $t->previousLastId && $t->rowNums > 0) {
                $rowsToCopy = $t->rowNums - $t->previousRowsCopied;
                if ($rowsToCopy > 0) {
                    $totalKB += $tableSizeInKB * $rowsToCopy / $t->rowNums + ($t->indexCount - 1) * 16;
                } else {
                    $totalKB += $tableSizeInKB * .2;
                }
            }
        }
    }
}

$myfile = fopen("tmp/size.txt", "c+");
if (!$myfile) {
    $conn->close();
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Could not open tmp/size.txt file to save value $totalKB KBs\n", 3, "tmp/logs/reaper_out.log");
    if (!file_exists("tmp/notify.txt")) {
        error_log("Reaper encountered an error when checking space required", 1, "$emailrecipient");
        file_put_contents("tmp/notify.txt", "[" . date('m-d-y H:i:s') . "] email sent to $emailrecipient\n", LOCK_EX);
    }
    exit(1);
}
fwrite($myfile, $totalKB);
fflush($myfile);
fclose($myfile);

$totalKB=round($totalKB);
file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] Space requirements for $db on $servername is $totalKB KBs\n", FILE_APPEND | LOCK_EX);
//close connection
$conn->close();
?>
