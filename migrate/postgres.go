package migrate

import (
	"context"
	"database/sql"

	"github.com/gabetrau/reaper/cfg"
	_ "github.com/jackc/pgx/v5/stdlib"
)

type PostgresDB struct {
	ctx *context.Context
	db *sql.DB
	indexes map[string]Index
}


func (p *PostgresDB) Connect(ctx *context.Context, info cfg.DBInfo) error {
	var err error
	port := "5432" 
	if info.Port != "" {
		port = info.Port
	}
	p.db, err = sql.Open("pgx", string(info.Driver) + "://" + info.User + ":" + info.Passwd + "@" + info.Host + ":" + port + "/" + info.DB)
	if err != nil {
		return err
	}
	p.ctx = ctx
	return nil
}

func (p *PostgresDB) Close() error {
	return p.db.Close()
}


func (p *PostgresDB) GetAllIndexes() (map[string]Index, error) {
	// TODO finish this
	return map[string]Index{}, nil
}


func (p *PostgresDB) GetAllTableNames() ([]string, error) {
	rows, err := p.db.QueryContext(*p.ctx, `SELECT table_name
FROM information_schema.tables
WHERE table_schema='public'
AND table_type='BASE TABLE';
`)
	if err != nil {
		return nil, err
	}	
	tableNames := make([]string, 0) 
	for rows.Next() {
		var tableName string
		if err := rows.Scan(&tableName); err != nil {
			return nil, err
		}
		tableNames = append(tableNames, tableName)
	}
	return tableNames, nil
}

