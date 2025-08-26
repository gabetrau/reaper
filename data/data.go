package data

import (
	"database/sql"

	"github.com/gabetrau/reaper/cfg"
	"github.com/gabetrau/reaper/data/mysql"
)

func Connect(info cfg.DBInfo) (*sql.DB, error) {
	switch info.Driver {
	case cfg.MySQL:
		return mysql.Connect(info) 
	default:
		panic("cannot connect to db, cfg should be invalid")
	}
}
