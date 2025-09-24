package data

import (
	"database/sql"
	"time"

	"github.com/gabetrau/reaper/cfg"
	"github.com/gabetrau/reaper/data/mysql"
	"github.com/gabetrau/reaper/data/postgres"
)

func Connect(info cfg.DBInfo) (*sql.DB, error) {
	var db *sql.DB
	var err error
	switch info.Driver {
	case cfg.MySQL:
		db, err = mysql.Connect(info) 
	case cfg.PostgreSQL:
		db, err = postgres.Connect(info)
	default:
		panic("cannot connect to db, cfg should be valid")
	}

	db.SetConnMaxLifetime(time.Minute * 3)
	db.SetMaxOpenConns(10)
	db.SetMaxIdleConns(10)

	return db, err
}
