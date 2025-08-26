package mysql

import (
	"database/sql"
	"time"

	"github.com/gabetrau/reaper/cfg"
	"github.com/go-sql-driver/mysql"
	_ "github.com/go-sql-driver/mysql"
)

func Connect(info cfg.DBInfo) (*sql.DB, error) {
	mysqlCfg := mysql.NewConfig()
	mysqlCfg.User = info.User
	mysqlCfg.Passwd = info.Passwd
	mysqlCfg.Net = "tcp"
	if info.Port != "" {
		mysqlCfg.Addr = info.Host + ":" + info.Port
	} else {
		mysqlCfg.Addr = info.Host
	}
	mysqlCfg.DBName = info.DB
	db, err := sql.Open(string(info.Driver), mysqlCfg.FormatDSN())
	if err != nil {
		return nil, err
	}

	db.SetConnMaxLifetime(time.Minute * 3)
	db.SetMaxOpenConns(10)
	db.SetMaxIdleConns(10)

	return db, nil
}
