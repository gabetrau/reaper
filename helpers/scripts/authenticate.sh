# Validate user
tries="3"

echo -e "\n\nSOW Server: ${SOW_HOST}\nSOW DB: ${SOW_DB}"
if [ -z "$SOW_USERNAME" ] || [ -z "$SOW_PASSWORD" ]; then	
	read -p "USERNAME: " SOW_USERNAME
	while [ "$tries" -gt 0 ]
	do
	        read -p "PASSWORD: " -s SOW_PASSWORD
	        echo
	        if ! php helpers/php/test_conn.php ${SOW_HOST} ${SOW_USERNAME} ${SOW_PASSWORD} ${SOW_DB} ${EMAIL_LOG_RECIPIENT} > /dev/null 2>&1; then
	                echo -e "\nUnable to connect to '${SOW_DB}' on '${SOW_HOST}'. $(( tries - 1 )) $([ $tries -eq 2 ] && echo "try" || echo "tries") remaining.\n"      
	                tries=$((tries-1))
	                if [ "$tries" -eq 0 ]; then
	                        echo -e "Failed too many times. Goodbye!\n"
	                        exit 1
	                fi
	        else
	                echo -e "\nSuccessfully established connection to '${SOW_DB}' on '${SOW_HOST}'" && break
	        fi
	done
	tries="3"
else 
	if ! php helpers/php/test_conn.php ${SOW_HOST} ${SOW_USERNAME} ${SOW_PASSWORD} ${SOW_DB} ${EMAIL_LOG_RECIPIENT} > /dev/null 2>&1; then
		echo -e "\nUnable to connect to '${SOW_DB}' on '${SOW_HOST}'" && exit 1
	else
		echo -e "\nSuccessfully established connection to '${SOW_DB}' on '${SOW_HOST}'"
	fi
fi

echo -e "\n\nREAP Server: ${REAP_HOST}\nREAP DB: ${REAP_DB}"
if [ -z "$REAP_USERNAME" ] || [ -z "$REAP_PASSWORD" ]; then
	read -p "USERNAME: " REAP_USERNAME
	while [ "$tries" -gt 0 ]
	do
	        read -p "PASSWORD: " -s REAP_PASSWORD
	        echo
	        if ! php helpers/php/test_conn.php ${REAP_HOST} ${REAP_USERNAME} ${REAP_PASSWORD} ${REAP_DB} ${EMAIL_LOG_RECIPIENT} > /dev/null 2>&1; then
	                echo -e "\nUnable to connect to '${REAP_DB}' on '${REAP_HOST}'. $(( tries - 1 )) $([ $tries -eq 2 ] && echo "try" || echo "tries") remaining.\n"    
	                tries=$((tries-1))
	                if [ "$tries" -eq 0 ]; then
	                        echo -e "Failed too many times. Goodbye!\n"
	                        exit 1
	                fi
	        else
	                tries="0"
	                echo -e "\nSuccessfully established connection to '${REAP_DB}' on '${REAP_HOST}'" && break
	        fi
	done
else
	if ! php helpers/php/test_conn.php ${REAP_HOST} ${REAP_USERNAME} ${REAP_PASSWORD} ${REAP_DB} ${EMAIL_LOG_RECIPIENT} > /dev/null 2>&1; then
                echo -e "\nUnable to connect to '${REAP_DB}' on '${REAP_HOST}'" && exit 1
        else 
                echo -e "\nSuccessfully established connection to '${REAP_DB}' on '${REAP_HOST}'"
        fi
fi
