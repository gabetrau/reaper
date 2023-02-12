# Sowing Temporary Tables
length=${#TABLES_ARRAY[@]}
t=0
while [ $t -lt $length ];
do
	t_upper=$(echo "${TABLES_ARRAY[$t]}" | awk '{print toupper($0)}')
	tput hpa 0 && tput el && printf "\r\t${PURPLE}[%c]${NORMAL} copying ${TABLES_ARRAY[$t]}" "$spinstr"
	if [ $(jobs -rp | wc -l | tr -d " ") -gt 7 ]; then
		php helpers/php/copy_table.php ${SOW_HOST} ${SOW_USERNAME} ${SOW_PASSWORD} ${SOW_DB} ${REAP_HOST} ${REAP_USERNAME} ${REAP_PASSWORD} ${REAP_DB} "${TABLES_ARRAY[$t]}" "$(eval "echo \${${t_upper}_COLUMNS}")" "${DO_NOT_STORE_KEY_TABLES}" "${REPLACE}" "${EMAIL_LOG_RECIPIENT}" >> tmp/logs/reaper_out.log 2>&1
    else
		tmpcolnames="${t_upper}_COLUMNS"
		php helpers/php/copy_table.php ${SOW_HOST} ${SOW_USERNAME} ${SOW_PASSWORD} ${SOW_DB} ${REAP_HOST} ${REAP_USERNAME} ${REAP_PASSWORD} ${REAP_DB} "${TABLES_ARRAY[$t]}" "${!tmpcolnames}" "${DO_NOT_STORE_KEY_TABLES}" "${REPLACE}" "${EMAIL_LOG_RECIPIENT}" >> tmp/logs/reaper_out.log 2>&1 &
	fi
	let t++
    if [ $t -eq $length ]; then
		tput hpa 0 && tput el && printf "\r\t${PURPLE}[%c]${NORMAL} waiting for background processes to finish" "$spinstr"
		wait
	fi
done &

trap "pkill -P $! > /dev/null 2>&1; kill $! > /dev/null 2>&1; pkill -P $$ > /dev/null 2>&1; exit 7" INT TERM ERR
spinner $! && trap "pkill -P $$ > /dev/null 2>&1; exit 7" INT TERM ERR
tput hpa 0 && tput el && echo -e "${PURPLE}\r\tfinished copying tables to temporary db '${REAP_DB}' on '${REAP_HOST}'\n${NORMAL}"