# Question User
echo -e "\n"

JSON_KEYS_FILE="${SOW_HOST}.${SOW_DB}.json"
COPY_ALL=false
echo -e "\n"
if [ -f "$JSON_KEYS_FILE" ]; then
    echo -e "Using primary keys stored in '${SOW_HOST}.${SOW_DB}.json'\n"
else
    COPY_ALL=true
    echo -e "No json file found from prior run. copying all records"
fi

