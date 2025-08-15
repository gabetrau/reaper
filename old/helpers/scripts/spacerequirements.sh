printf "\n"
printf "\t${PURPLE}[%c] ${NORMAL}Calculating space required on reap server" "$spinstr"

php helpers/php/check_space.php ${SOW_HOST} ${SOW_USERNAME} ${SOW_PASSWORD} ${SOW_DB} "${TABLES}" "${COPY_ALL}" "${DO_NOT_STORE_KEY_TABLES}" "${EMAIL_LOG_RECIPIENT}" & 
spinner $! && tput hpa 0 && tput el
SPACE_NEEDED=$(cat tmp/size.txt)
rm tmp/size.txt
BYTE_LABEL="MB"
if (( $(echo "$SPACE_NEEDED >= 1000" | bc) )); then
    SPACE_NEEDED=$(echo "$SPACE_NEEDED / 1024" | bc -l)
    if (( $(echo "$SPACE_NEEDED >= 1000" | bc) )); then
        SPACE_NEEDED=$(echo "$SPACE_NEEDED / 1024" | bc -l)
        BYTE_LABEL="GB"
    fi
    SPACE_NEEDED=$(echo "scale=2; $SPACE_NEEDED / 1" | bc)
else
    SPACE_NEEDED="<1"
fi


tput hpa 0 && tput el && echo -e "${LIGHT_RED}\r\tATTENTION! This will copy approximately $SPACE_NEEDED $BYTE_LABEL(s) of data from '${SOW_DB}' database on '${SOW_HOST}' to '${REAP_DB}' on '${REAP_HOST}'${NORMAL}"
