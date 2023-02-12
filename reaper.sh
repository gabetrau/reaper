#!/bin/bash
# Author: Gabriel Rau
clear

# Config file
source config.sh
CREATE_TABLES=true

close() {
    if [ $? -eq 7 ]; then
        printf "\n" && tput hpa 0 && tput el
        echo -e "\r\n\t${PURPLE}Process Cancelled, Goodbye!\n${NORMAL}"
        printf "[$(date +%m-%d-%y) $(date +%T)] Process Cancelled\n" >> tmp/logs/reaper_out.log
    fi
    rm tmp/resource.sql > /dev/null 2>&1
    rm tmp/notify.txt > /dev/null 2>&1
    tput cnorm
    exit $?
}
trap "pkill -P $$ > /dev/null 2>&1; exit 7" INT TERM ERR
trap close exit

# Spinner logic
# http://fitnr.com/showing-a-bash-spinner.html
spinstr='.o0o'
spindelay=0.70
spintemp=""
spinner() {
    local pid=$1
    while [ "$(ps a | awk '{print $1}' | grep $pid)" ]; do
        spintemp=${spinstr#?}
        printf "\r${PURPLE}\t[%c]${NORMAL} " "$spinstr"
        spinstr=$spintemp${spinstr%"$spintemp"}
        sleep $spindelay
    done
}
tput civis
# Title
echo -e "\n\t${PURPLE}--- REAPER: A Tool For Exporting Data From One MYSQL Server to Another ---${NORMAL}\n"
echo -e "\n${LIGHT_RED}WARNING: This tool can and will overwrite tuples and drop tables. If configured incorrectly, things can go very wrong.\nYou reap what you sow!${NORMAL}\n"

# Configuration Check
[ -z "$TABLES" ] && echo -e "Tables variable is empty!\n" && exit

[ -z "$SOW_HOST" ] && echo -e "Sow host variable is empty, nothing to sow!\n" && exit
[ -z "$SOW_DB" ] && echo -e "Sow DB variable is empty, nothing to sow!\n" && exit
[ -z "$REAP_HOST" ] && echo -e "Reap host variable is empty, nothing to reap!\n" && exit
[ -z "$REAP_DB" ] && echo -e "Reap DB variable is empty, nothing to reap!\n" && exit

date_now=$(date)
echo -e "\n\nREAPER EXECUTION $date_now\n---------------------------------------------------" >> tmp/logs/reaper_out.log
echo -e "Use the following command to stop process: kill -TERM $$\n" >> tmp/logs/reaper_out.log
# Authenticate
source helpers/scripts/authenticate.sh

# Check for json file from prev run
source helpers/scripts/previousrun.sh

# Check amount of space data takes up
source helpers/scripts/spacerequirements.sh
echo -e "\n"

echo -e "Copying data from ${SOW_DB} on ${SOW_HOST} to ${REAP_DB} on ${REAP_HOST}:\n"
printf "[$(date +%m-%d-%y) $(date +%T)] Preparing ${REAP_DB} on ${REAP_HOST}\n" >> tmp/logs/reaper_out.log
# Clean
if [ ! -z "$DO_NOT_STORE_KEY_TABLES" ]; then
    php helpers/php/remove_do_not_store_tables.php ${REAP_HOST} ${REAP_USERNAME} ${REAP_PASSWORD} ${REAP_DB} "${DO_NOT_STORE_KEY_TABLES}" "${EMAIL_LOG_RECIPIENT}" >> tmp/logs/reaper_out.log 2>&1
fi
if $CREATE_TABLES; then
    source helpers/scripts/clean.sh
fi

# Sow Tables
source helpers/scripts/sowtables.sh

php helpers/php/reset_nosave_tables.php "${SOW_HOST}" "${SOW_DB}" "${DO_NOT_STORE_KEY_TABLES}" "${EMAIL_LOG_RECIPIENT}"

tput cnorm
echo -e "\n${PURPLE}Process Complete${NORMAL}\n\n"
printf "[$(date +%m-%d-%y) $(date +%T)] Process Complete\n" >> tmp/logs/reaper_out.log
