printf "\t${PURPLE}[%c]${NORMAL} dropping tables in '${REAP_DB}'" "$spinstr"
php helpers/php/clean_reap_db.php ${REAP_HOST} ${REAP_USERNAME} ${REAP_PASSWORD} ${REAP_DB} ${EMAIL_LOG_RECIPIENT} || echo -e "\r\n${LIGHT_RED}ERROR: A problem occurred while trying to drop temporary tables in '${REAP_DB}'${NORMAL}\n\n" &
spinner $!
sleep .08
tput hpa 0 && tput el && printf "\t${PURPLE}[%c]${NORMAL} Copying table definitions" "$spinstr"
printf "[$(date +%m-%d-%y) $(date +%T)] Copying table definitions from ${SOW_DB} on ${SOW_HOST}\n" >> tmp/logs/reaper_out.log
ignore_table_array=($EXCLUDE_TABLES_FROM_INITIAL_DUMP)
ignore_table_array=("${ignore_table_array[@]/#/--ignore-table=$SOW_DB.}")
ignore_table_flags_string=$(printf "%s " "${ignore_table_array[@]}")
mysqldump -u ${SOW_USERNAME} -p${SOW_PASSWORD} -h ${SOW_HOST} ${SOW_DB} --no-create-db --no-data --ignore-error=1227,1356 ${ignore_table_flags_string}--result-file=tmp/clean.sql --column-statistics=0 >> tmp/logs/reaper_out.log 2>&1 &
spinner $!
sleep .08
tput hpa 0 && tput el && printf "\t${PURPLE}[%c]${NORMAL} Updating ${REAP_DB} on ${REAP_HOST}" "$spinstr"
printf "[$(date +%m-%d-%y) $(date +%T)] Updating table definitions on reap server\n" >> tmp/logs/reaper_out.log
mysql -u ${REAP_USERNAME} -p${REAP_PASSWORD} -h ${REAP_HOST} -f ${REAP_DB} < tmp/clean.sql >> tmp/logs/reaper_out.log 2>&1 &
spinner $!
sleep .08
rm tmp/clean.sql > /dev/null 2>&1
tput hpa 0 && tput el && echo -e "${PURPLE}\r\treap db '${REAP_DB}' on '${REAP_HOST}' cleaned and added table definitions\n${NORMAL}"