<?php
// Author: Gabriel Rau

// input variables
$sowserver = $argv[1];
$sowusername = $argv[2];
$sowpassword = $argv[3];
$sowdb = $argv[4];
$reapserver = $argv[5];
$reapusername = $argv[6];
$reappassword = $argv[7];
$reapdb = $argv[8];
$table = $argv[9];
$fieldstring = $argv[10];
$dontsavetables = $argv[11];
$replace = $argv[12];
$emailrecipient = $argv[13];
$fieldArray = explode(" ", $fieldstring);

function notify($table, $db, $server, $email) {
    if (!file_exists("tmp/notify.txt")) {
        $fp = fopen("tmp/notify.txt", "w+");
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            error_log("Reaper encountered an error when copying table $table from $db on $server", 1, "$email");
            fwrite($fp, "[" . date('m-d-y H:i:s') . "] email sent to $email\n");
            fflush($fp);
            sleep(1);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

// name for file with last copied primary key
$filename = "{$sowserver}.{$sowdb}.json";

// data from file for prior run if there was one
$keystoreArray = array();
$previousRowsCopiedArray = array();
if (file_exists($filename)) {
    $previousSowContents = file_get_contents($filename);
    $jsonArray = json_decode($previousSowContents, true);
    if (!$jsonArray) {
        notify($table, $sowdb, $sowserver, $emailrecipient);
        exit(1);
    }
    $keystoreArray = $jsonArray['primaryKeys'];
    $previousRowsCopiedArray = $jsonArray['rows'];
}
$lastPrimaryKeyVal = 0;
$lastNumberOfRowsCopied = 0;
if (array_key_exists("$table", $keystoreArray)) {
    $lastPrimaryKeyVal = $keystoreArray["$table"];
}
if (array_key_exists("$table", $previousRowsCopiedArray)) {
    $lastNumberOfRowsCopied = floatval($previousRowsCopiedArray["$table"]);
}

// mysql connections. one for the sow server and the other for reap server with tmp db
$sowConn = new mysqli($sowserver, $sowusername, $sowpassword,  $sowdb);
$reapConn = new mysqli($reapserver, $reapusername, $reappassword, $reapdb);
if ($sowConn->connect_error) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Connection to $sowdb on $sowserver failed! $sowConn->connect_error\n", 3, "tmp/logs/reaper_out.log");
    notify($table, $sowdb, $sowserver, $emailrecipient);
    exit(1);
}
if ($reapConn->connect_error) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Connection to $reapdb on $reapserver failed! $reapConn->connect_error\n", 3, "tmp/logs/reaper_out.log");
    notify($table, $sowdb, $sowserver, $emailrecipient);
    exit(1);
}

// get the name of the primary key
$getPrimaryKeyColumnNameQuery = "SHOW KEYS FROM $sowdb.$table WHERE Key_name = 'PRIMARY'";
$primaryKey = "";
$primaryKeyResult = $sowConn->query($getPrimaryKeyColumnNameQuery);
if ($primaryKeyResult->num_rows > 0) {
    $row = $primaryKeyResult->fetch_assoc();
    $primaryKey = $row["Column_name"];
}

$statusQuery = "SHOW TABLE STATUS FROM {$sowdb} WHERE name='{$table}'";
$rowsToCopy = 0;
$numOfRows = 0;
$avgRowSize = 0;
$statusResult = $sowConn->query($statusQuery);
if ($statusResult->num_rows > 0) {
    $row = $statusResult->fetch_assoc();
    $numOfRows = floatval($row['Rows']);
    $rowsToCopy = floatval($row['Rows']) - $lastNumberOfRowsCopied;
    if ($rowsToCopy < 0) {
        $rowsToCopy = 0;
    }
    $avgRowSize = floatval($row['Avg_row_length']); //in bytes
}
if ($numOfRows <= 0 || $avgRowSize == 0) {
    $selectLastRowQuery = "SELECT * FROM {$sowdb}.{$table} LIMIT 1";
    $selectLastRowResult = $sowConn->query($selectLastRowQuery);
    if ($selectLastRowResult->num_rows <= 0) {
        file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] $table is empty for $sowdb on $sowserver\n", FILE_APPEND | LOCK_EX);
        $sowConn->close();
        $reapConn->close();
        exit;
    } else {
        $avgRowSize = 1; // prevent division by zero error
    }
}

// get the last id of the table being copied and check to see if it has already been copied
$lastId = 0;
if (strcmp($replace, "true") === 0) {
    $replace = "REPLACE";
} else {
    $replace = "INSERT IGNORE";
}
$reapInsertQuery = "$replace INTO $table(";
$reapColumnsInfo = array();
if (!empty($primaryKey)) {
    $selectLastIdQuery = "SELECT * FROM {$sowdb}.{$table} ORDER BY $primaryKey DESC LIMIT 1";
    $selectLastIdResult = $sowConn->query($selectLastIdQuery);
    if ($selectLastIdResult->num_rows > 0) {
        $reapColumnsInfo = $selectLastIdResult->fetch_fields();
        $row = $selectLastIdResult->fetch_assoc();
        $reapInsertColumns = array_keys($row);
        $reapInsertQuery .= implode(",", $reapInsertColumns) . ") VALUES ";
        $lastId = floatval($row[$primaryKey]);
    }
    if ($lastId <= $lastPrimaryKeyVal) {
        file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] $table already copied from $sowdb on $sowserver\n", FILE_APPEND | LOCK_EX);
        $sowConn->close();
        $reapConn->close();
        exit;
    }
} else {
    $selectLastRowQuery = "SELECT * FROM {$sowdb}.{$table} LIMIT 1";
    $selectLastRowResult = $sowConn->query($selectLastRowQuery);
    if ($selectLastRowResult->num_rows > 0) {
        $reapColumnsInfo = $selectLastRowResult->fetch_fields();
        $row = $selectLastRowResult->fetch_assoc();
        $reapInsertColumns = array_keys($row);
        $reapInsertQuery .= implode(",", $reapInsertColumns) . ") VALUES ";
    }
}

// check the current size of tmp db on reap server to 
$dbSizeInGB = 0;
$dbSizeQuery = "SELECT table_schema 'db_name', ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 2) 'db_size_gb' FROM information_schema.tables WHERE table_schema='$reapdb'";
$dbSizeResult = $reapConn->query($dbSizeQuery);
if ($dbSizeResult->num_rows > 0) {
    $row = $dbSizeResult->fetch_assoc();
    $dbSizeInGB = floatval($row['db_size_gb']);
} else {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Could not determine size of $reapdb\n", 3, "tmp/logs/reaper_out.log");
    notify($table, $sowdb, $sowserver, $emailrecipient);
    $sowConn->close();
    $reapConn->close();
    exit(1);
}

// disable keys constraints
$turnOffForeignKeyConstraintsQuery = "SET FOREIGN_KEY_CHECKS=0";
$turnOnForeignKeyConstraintsQuery = "SET FOREIGN_KEY_CHECKS=1";
$turnOffForeignKeyConstraintsResult = $reapConn->query($turnOffForeignKeyConstraintsQuery);
if (!$turnOffForeignKeyConstraintsResult) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while disabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
    notify($table, $sowdb, $sowserver, $emailrecipient);
    $reapConn->close();
    $sowConn->close();
    exit(1);
}
// if there are more than 16 MB of records, split the insert statement into multiple for performance and so mysql doesn't crash
$portion = round(16 * 1024 * 1024 / $avgRowSize, 0);
$offset = $lastPrimaryKeyVal;
$numOfInsertedRows = (float) 0;
$reapCombinedInsertQuery = "";
$numericTypes = array(16,1,2,9,3,8,4,5,246);
file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] $table started copying\n", FILE_APPEND | LOCK_EX);
if (empty($primaryKey)) {
    if ($rowsToCopy > 3000000) {
        error_log("[" . date('m-d-y H:i:s') . "] ERROR: $table does not have a primary key and has too many rows to copy\n", 3, "tmp/logs/reaper_out.log");
        notify($table, $sowdb, $sowserver, $emailrecipient);
        $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
        if (!$turnOnForeignKeyConstraintsResult) {
            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
        }
        $reapConn->close();
        $sowConn->close();
        exit(1);
    }
    $copyDataQuery = "SELECT * FROM $sowdb.$table";
    $copyDataResult = $sowConn->query($copyDataQuery);
    if ($copyDataResult->num_rows > 0) {
        $reapCombinedInsertQuery = $reapInsertQuery;
        $insertRowCounter = 0;
        while ($copyDataRow = $copyDataResult->fetch_assoc()) {
            $reapCombinedInsertQuery .= "(";
            $rowVals = array_values($copyDataRow);
            for ($v=0; $v<count($rowVals); $v++) {
                if (in_array($reapColumnsInfo[$v]->name, $fieldArray)) {
                    if ( strpos( $reapColumnsInfo[$v]->name, "phn" ) !== FALSE || strpos( $reapColumnsInfo[$v]->name, "phone" ) !== FALSE ) {
                        $rowVals[$v]="3333333333";
                    } elseif ( strpos( $reapColumnsInfo[$v]->name, "email" ) !== FALSE ) {
                        $rowVals[$v]="usappraisals.dev@gmail.com";
                    } else {
                        $rowVals[$v]=NULL;
                    }
                }
                if (is_null($rowVals[$v])) {
                    $reapCombinedInsertQuery .= "NULL,";
                } else {
                    if (in_array($reapColumnsInfo[$v]->type, $numericTypes)) {
                        $reapCombinedInsertQuery .= "$rowVals[$v],";
                    } else {
                        $reapCombinedInsertQuery .= sprintf("'%s',", $reapConn->real_escape_string($rowVals[$v]));
                    }
                }                        
            }
            $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
            $reapCombinedInsertQuery .= "),";
            $insertRowCounter++;
            if ($insertRowCounter >= 900) {
                $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
                try {
                    if ($reapCombinedInsertResult = $reapConn->query($reapCombinedInsertQuery)) {
                        $numOfInsertedRows += $reapConn->affected_rows;
                    } else {
                        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while trying to insert data for $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                        notify($table, $sowdb, $sowserver, $emailrecipient);
                        $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                        if (!$turnOnForeignKeyConstraintsResult) {
                            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                        }
                        $reapConn->close();
                        $sowConn->close();
                        exit(1);
                    }
                } catch (Throwable $e) {
                    error_log("[" . date('m-d-y H:i:s') . "] ERROR: PHP mysqli error while trying to insert data for $table - $e\n", 3, "tmp/logs/reaper_out.log");
                    notify($table, $sowdb, $sowserver, $emailrecipient);
                    $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                    if (!$turnOnForeignKeyConstraintsResult) {
                        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                    }
                    $reapConn->close();
                    $sowConn->close();
                    exit(1);
                }
                $insertRowCounter = 0;
                $reapCombinedInsertQuery = $reapInsertQuery;
            }
        }
        if ($insertRowCounter != 0) {
            $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
            try {
                if ($reapCombinedInsertResult = $reapConn->query($reapCombinedInsertQuery)) {
                    $numOfInsertedRows += $reapConn->affected_rows;
                } else {
                    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while trying to insert data for $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                    notify($table, $sowdb, $sowserver, $emailrecipient);
                    $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                    if (!$turnOnForeignKeyConstraintsResult) {
                        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                    }
                    $reapConn->close();
                    $sowConn->close();
                    exit(1);
                }
            } catch (Throwable $e) {
                error_log("[" . date('m-d-y H:i:s') . "] ERROR: PHP mysqli error while trying to insert data for $table - $e\n", 3, "tmp/logs/reaper_out.log");
                notify($table, $sowdb, $sowserver, $emailrecipient);
                $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                if (!$turnOnForeignKeyConstraintsResult) {
                    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                }
                $reapConn->close();
                $sowConn->close();
                exit(1);
            }
        }
    }
} else {
    if ($rowsToCopy < $portion) {
        $copyDataQuery = "SELECT * FROM $sowdb.$table WHERE {$primaryKey} > $lastPrimaryKeyVal";
        $copyDataResult = $sowConn->query($copyDataQuery);
        if ($copyDataResult->num_rows > 0) {
            $reapCombinedInsertQuery = $reapInsertQuery;
            $insertRowCounter = 0;
            while ($copyDataRow = $copyDataResult->fetch_assoc()) {
                $reapCombinedInsertQuery .= "(";
                $rowVals = array_values($copyDataRow);
                for ($v=0; $v<count($rowVals); $v++) {
                    if (in_array($reapColumnsInfo[$v]->name, $fieldArray)) {
                        if ( strpos( $reapColumnsInfo[$v]->name, "phn" ) !== FALSE || strpos( $reapColumnsInfo[$v]->name, "phone" ) !== FALSE ) {
                            $rowVals[$v]="3333333333";
                        } elseif ( strpos( $reapColumnsInfo[$v]->name, "email" ) !== FALSE ) {
                            $rowVals[$v]="usappraisals.dev@gmail.com";
                        } else {
                            $rowVals[$v]=NULL;
                        }
                    }
                    if (is_null($rowVals[$v])) {
                        $reapCombinedInsertQuery .= "NULL,";
                    } else {
                        if (in_array($reapColumnsInfo[$v]->type, $numericTypes)) {
                            $reapCombinedInsertQuery .= "$rowVals[$v],";
                        } else {
                            $reapCombinedInsertQuery .= sprintf("'%s',", $reapConn->real_escape_string($rowVals[$v]));
                        }
                    }                        
                }
                $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
                $reapCombinedInsertQuery .= "),";
                $insertRowCounter++;
                if ($insertRowCounter >= 900) {
                    $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
                    try {
                        if ($reapCombinedInsertResult = $reapConn->query($reapCombinedInsertQuery)) {
                            $numOfInsertedRows += $reapConn->affected_rows;
                        } else {
                            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while trying to insert data for $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                            notify($table, $sowdb, $sowserver, $emailrecipient);
                            $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                            if (!$turnOnForeignKeyConstraintsResult) {
                                error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                            }
                            $reapConn->close();
                            $sowConn->close();
                            exit(1);
                        }
                    } catch (Throwable $e) {
                        error_log("[" . date('m-d-y H:i:s') . "] ERROR: PHP mysqli error while trying to insert data for $table - $e\n", 3, "tmp/logs/reaper_out.log");
                        notify($table, $sowdb, $sowserver, $emailrecipient);
                        $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                        if (!$turnOnForeignKeyConstraintsResult) {
                            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                        }
                        $reapConn->close();
                        $sowConn->close();
                        exit(1);
                    }
                    $insertRowCounter = 0;
                    $reapCombinedInsertQuery = $reapInsertQuery;
                }
            }
            if ($insertRowCounter != 0) {
                $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
                try {
                    if ($reapCombinedInsertResult = $reapConn->query($reapCombinedInsertQuery)) {
                        $numOfInsertedRows += $reapConn->affected_rows;
                    } else {
                        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while trying to insert data for $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                        notify($table, $sowdb, $sowserver, $emailrecipient);
                        $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                        if (!$turnOnForeignKeyConstraintsResult) {
                            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                        }
                        $reapConn->close();
                        $sowConn->close();
                        exit(1);
                    }
                } catch (Throwable $e) {
                    error_log("[" . date('m-d-y H:i:s') . "] ERROR: PHP mysqli error while trying to insert data for $table - $e\n", 3, "tmp/logs/reaper_out.log");
                    notify($table, $sowdb, $sowserver, $emailrecipient);
                    $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                    if (!$turnOnForeignKeyConstraintsResult) {
                        error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                    }
                    $reapConn->close();
                    $sowConn->close();
                    exit(1);
                }
            }
        }
    } else {
        $sizeStmt = $reapConn->prepare($dbSizeQuery);
        $copyDataQuery = "SELECT * FROM $sowdb.$table WHERE {$primaryKey} > ? AND {$primaryKey} <= ?";
        $copyStmt = $sowConn->prepare($copyDataQuery);
        $copyStmt->bind_param("dd", $off, $newOff);
        $off = $offset;
        while ($off < $lastId) {
            $sizeStmt->execute();
            $sizeResult = $sizeStmt->get_result();
            $sizeRow = $sizeResult->fetch_assoc();
            $tempSizeInGB = floatval($sizeRow['db_size_gb']);
            $newOff = $off + $portion;
            $copyStmt->execute();
            $copyDataResult = $copyStmt->get_result();
            if ($copyDataResult->num_rows > 0) {
                $reapCombinedInsertQuery = $reapInsertQuery;
                $insertRowCounter = 0;
                while ($copyDataRow = $copyDataResult->fetch_assoc()) {
                    $reapCombinedInsertQuery .= "(";
                    $rowVals = array_values($copyDataRow);
                    for ($v=0; $v<count($rowVals); $v++) {
                        if (in_array($reapColumnsInfo[$v]->name, $fieldArray)) {
                            if ( strpos( $reapColumnsInfo[$v]->name, "phn" ) !== FALSE || strpos( $reapColumnsInfo[$v]->name, "phone" ) !== FALSE ) {
                                $rowVals[$v]="3333333333";
                            } elseif ( strpos( $reapColumnsInfo[$v]->name, "email" ) !== FALSE ) {
                                $rowVals[$v]="usappraisals.dev@gmail.com";
                            } else {
                                $rowVals[$v]=NULL;
                            }
                        }
                        if (is_null($rowVals[$v])) {
                            $reapCombinedInsertQuery .= "NULL,";
                        } else {
                            if (in_array($reapColumnsInfo[$v]->type, $numericTypes)) {
                                $reapCombinedInsertQuery .= "$rowVals[$v],";
                            } else {
                                $reapCombinedInsertQuery .= sprintf("'%s',", $reapConn->real_escape_string($rowVals[$v]));
                            }
                        }                        
                    }
                    $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
                    $reapCombinedInsertQuery .= "),";
                    $insertRowCounter++;
                    if ($insertRowCounter >= 900) {
                        $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
                        try {
                            if ($reapCombinedInsertResult = $reapConn->query($reapCombinedInsertQuery)) {
                                $numOfInsertedRows += $reapConn->affected_rows;
                            } else {
                                error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while trying to insert data for $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                                notify($table, $sowdb, $sowserver, $emailrecipient);
                                $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                                if (!$turnOnForeignKeyConstraintsResult) {
                                    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                                }
                                $sizeStmt->close();
                                $copyStmt->close();
                                $reapConn->close();
                                $sowConn->close();
                                exit(1);
                            }
                        } catch (Throwable $e) {
                            error_log("[" . date('m-d-y H:i:s') . "] ERROR: PHP mysqli error while trying to insert data for $table - $e()\n", 3, "tmp/logs/reaper_out.log");
                            notify($table, $sowdb, $sowserver, $emailrecipient);
                            $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                            if (!$turnOnForeignKeyConstraintsResult) {
                                error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                            }
                            $sizeStmt->close();
                            $copyStmt->close();
                            $reapConn->close();
                            $sowConn->close();
                            exit(1);
                        }
                        $insertRowCounter = 0;
                        $reapCombinedInsertQuery = $reapInsertQuery;
                    }
                }
                if ($insertRowCounter != 0) {
                    $reapCombinedInsertQuery = rtrim($reapCombinedInsertQuery, ",");
                    try {
                        if ($reapCombinedInsertResult = $reapConn->query($reapCombinedInsertQuery)) {
                            $numOfInsertedRows += $reapConn->affected_rows;
                        } else {
                            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while trying to insert data for $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                            notify($table, $sowdb, $sowserver, $emailrecipient);
                            $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                            if (!$turnOnForeignKeyConstraintsResult) {
                                error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                            }
                            $sizeStmt->close();
                            $copyStmt->close();
                            $reapConn->close();
                            $sowConn->close();
                            exit(1);
                        }
                    } catch (Throwable $e) {
                        error_log("[" . date('m-d-y H:i:s') . "] ERROR: PHP mysqli error while trying to insert data for $table - $e\n", 3, "tmp/logs/reaper_out.log");
                        notify($table, $sowdb, $sowserver, $emailrecipient);
                        $turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
                        if (!$turnOnForeignKeyConstraintsResult) {
                            error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
                        }
                        $sizeStmt->close();
                        $copyStmt->close();
                        $reapConn->close();
                        $sowConn->close();
                        exit(1);
                    }
                }
            }
            $off = $newOff;
        }        
        if ($off > $lastId) {
            $offset = $lastId;
        } else {
            $offset = $off;
        }
        $sizeStmt->close();
        $copyStmt->close();
    }
}

$turnOnForeignKeyConstraintsResult = $reapConn->query($turnOnForeignKeyConstraintsQuery);
if (!$turnOnForeignKeyConstraintsResult) {
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Mysql error while enabling key constraints $table - $reapConn->error\n", 3, "tmp/logs/reaper_out.log");
    notify($table, $sowdb, $sowserver, $emailrecipient);
    $reapConn->close();
    $sowConn->close();
    exit(1);
}

$fstream = fopen("$filename", "ac+");
if (flock($fstream, LOCK_EX)) {
    $jsonOutput;
    $jsonStr = stream_get_contents($fstream);
    if (empty($jsonStr)) {
        $tempArray;
        if (!empty($primaryKey)) {
            $tempArray = array(
                "rows" => array("$table" => $numOfInsertedRows), 
                "primaryKeys" => array("$table" => $lastId)
            );
        } else {
            $tempArray = array(
                "rows" => array("$table" => $numOfInsertedRows), 
                "primaryKeys" => array()
            );
        }
        $jsonOutput = json_encode($tempArray);
    } else {
        $tempArray = json_decode($jsonStr, true);
        if (array_key_exists($table, $tempArray["rows"])) {
            $tempArray["rows"]["$table"] += $numOfInsertedRows;
        } else {
            $tempArray["rows"]["$table"] = $numOfInsertedRows;
        }
        if (!empty($primaryKey)) {
            $tempArray["primaryKeys"]["$table"] = $lastId;
        }
        $jsonOutput = json_encode($tempArray);
    }
    ftruncate($fstream, 0);
    fwrite($fstream, $jsonOutput);
    fflush($fstream);
    flock($fstream, LOCK_UN);
} else {
    $sowConn->close();
    $reapConn->close();
    fclose($fstream);
    notify($table, $sowdb, $sowserver, $emailrecipient);
    error_log("[" . date('m-d-y H:i:s') . "] ERROR: Unable to obtain lock for $filename after copying $table\n", 3, "tmp/logs/reaper_out.log");
    exit(1);
}
fclose($fstream);

//close connection
$sowConn->close();
$reapConn->close();
file_put_contents("tmp/logs/reaper_out.log", "[" . date('m-d-y H:i:s') . "] $table finished copying\n", FILE_APPEND | LOCK_EX);

?>
