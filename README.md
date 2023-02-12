# REAPER
### Author: Gabriel Rau

## Description
This is a tool that can be used to transfer data from one mysql server to another. It is also able to obfuscate data such as emails, phone numbers, etc.

Jira Issue
TVS-681: Create tool for importing client company data from prod to uat


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

To pull data, we have to create copies of the tables on the server we are pulling from.
Therefore, as a prerequisite, we need a database for the temporary tables to be stored that is empty.

The following variables are for the mysql database that you want to pull data from.

```
SOW_HOST="<hostname or IP address>"
SOW_DB="<db name where data is located>"
```
<br />

Subsequently, these variables are for the database you want to write to.

```
REAP_HOST="<hostname or IP address>"
REAP_DB="<db with tables that will receive data>"
```

<br />

Next, there is a file called 'tables.txt' used to mark tables that need to be copied to the temporary db.

If the table that needs to be copied has any fields that need to be obfuscated such as emails, phone numbers, etc,
then they need to be added to the variable corresponding to the table name (<TABLE_NAME>_COLUMNS) in the 'config.sh' file.

Below is a command to quickly get a list of tables and output them to a text file.
```
sudo mysql -u <username> -p -h <IP or hostname> <db name> -e "show tables" > tables.txt
```
NOTE: Make sure to view the file and remove the first few lines that are not table names.

<br />

To identify which columns need to be updated, you have to add a variable that follows the naming scheme 'TABLE_NAME_COLUMNS'. See the examples below.

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

After each run, the last primary key that you copied from each table will be saved to a json file. The name of the json file is follows the naming convention <SOW_HOST>.<SOW_DB>.json

