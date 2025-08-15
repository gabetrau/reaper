# REAPER
### Author: Gabriel Rau

## Description
This is a tool/script for copying data from a mysql/mariadb prod env to a testing environment. You can configure it to obfuscate sensitive data



## Install php

macOS using homebrew
```
brew install https php
brew link php
```

Linux
```
sudo apt-get install php-mysql
sudo apt install php7.4-cli
```

## Configuration

All configuration can be done in 'config.sh'.

The following variables are for the mysql database that you want to pull data from. The username and password are optional, if you do not provide them in the config file, it will prompt you when you run the script.

```
SOW_HOST="<hostname or IP address>"
SOW_DB="<db name where data is located>"
SOW_USERNAME=""
SOW_PASSWORD=""
```
<br />

Subsequently, these variables are for the database you want to write to.

```
REAP_HOST="<hostname or IP address>"
REAP_DB="<db with tables that will receive data>"
REAP_USERNAME=""
REAP_PASSWORD=""
```

<br />

Next, there is a file called 'tables.txt' used to mark tables that need to be copied to the reap db.

Below is a command to quickly get a list of tables and output them to a text file.
```
sudo mysql -u <username> -p -h <IP or hostname> <db name> -e "show tables" > tables.txt
```
NOTE: Make sure to view the file and remove any lines that are not table names.

<br />

To identify which columns need to be changed, you have to add a variable that follows the naming scheme 'TABLE_NAME_COLUMNS'. See the examples below.

<br />

```
# Columns that need to be obfuscated
SECURITY_USER_COLUMNS="cell_phone work_phone email_address slack_id"
CLIENT_COMPANY_COLUMNS="company_fax company_work_phone company_email_address main_contact_work_phone main_contact_email_address billing_contact_email_address billing_contact_work_phone fee_approval_email quick_books_id main_contact_work_phone"
CLIENT_COMPANY_FILE_COLUMNS="s3file_id"
```

<br />

Once you have finished initializing your variables to fit your needs, you are ready to run the program. 


## Run program

make sure that 'reaper.sh' is executable, then run the program.

<br />

```
./reaper.sh
```

<br />

You will need to provide a username/password for both mysql servers. (either in the config file or when you run the program)

After each run, the last primary key that you copied from each table will be saved to a json file. The name of the json file is as follows <SOW_HOST>.<SOW_DB>.json

This will allow for subsequent runs of reaper to start the process at the last recorded primary key so that you do not have to recopy the entire table.

If you want to recopy specific tables each run, add the table name to this variable. Warning, these tables will be truncated in the reap db each time the program is run.
DO_NOT_STORE_KEY_TABLES="table1 table2 table3"

This script works best as a cronjob where it copies new data every time it runs. There is a logfile that gets created in the tmp/logs directory called reaper_out.log

If the script identifies an error, you can configure it to send an email to a specified email address using the variable
EMAIL_LOG_RECIPIENT=""

