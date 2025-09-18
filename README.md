# Reaper

## Configuration
create a file called `.reaper.yml` in your home directory. You can also use an env variable called `REAPER_PATH` to specify your configuration elsewhere. 

*Example .reaper.yml*
```
source:
  driver: "mysql"
  host: "localhost"
  db: "databasename"
  username: "pass"
  password: "pass1!"
  port: "3306"

destination:
  driver: "mysql"
  host: "localhost"
  db: "otherdbname"
  username: "pass"
  password: "pass1!"
  port: "3306"
```
