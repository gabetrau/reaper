# Reaper
A tool for migrating data from one sql database to another. Also supports obfuscating sensitive data to create database replicas for testing environments.

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
### Supported Drivers
- MySQL/MariaDB -> "mysql"
- PostgreSQL -> "postgres"
- SQLite -> "sqlite"

