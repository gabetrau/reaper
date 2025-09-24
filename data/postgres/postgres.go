package postgres 

import (
	"database/sql"

	"github.com/gabetrau/reaper/cfg"
	_ "github.com/jackc/pgx/v5/stdlib"
)

func Connect(info cfg.DBInfo) (*sql.DB, error) {
	db, err := sql.Open("pgx", string(info.Driver) + "://" + info.User + ":" + info.Passwd + "@" + info.Host + ":" + info.Port + "/" + info.DB)
	if err != nil {
		return nil, err
	}

	return db, nil
}
