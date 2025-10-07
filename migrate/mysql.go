package migrate

import (
	"context"
	"database/sql"
	"log"

	"github.com/gabetrau/reaper/cfg"
	"github.com/go-sql-driver/mysql"
)

type MysqlIndex struct {
	Table string
	NonUnique bool
	KeyName string
	SeqInIndex bool
	ColumnName string
	Collation rune
	Cardinality bool
	SubPart *any
	Packed *any
	Null string
	IndexType string
	Comment string
	IndexComment string
	Ignored string
}

func (in *MysqlIndex) IndexSytax() string {
	return ""
}

type MysqlDB struct {
	ctx *context.Context
	db *sql.DB
	indexes map[string]Index
}

func (m *MysqlDB) Connect(ctx *context.Context, info cfg.DBInfo) error {
	db, err := createConnection(info)
	if err != nil {
		return err
	}
	m.db = db 
	m.ctx = ctx
	srcPingErr := m.db.Ping()
	if srcPingErr != nil {
		log.Fatalf("ping error: %v", srcPingErr)
	}
	return nil 
}

func (m *MysqlDB) Close() error {
	if m.db != nil {
		return m.db.Close()
	}
	return nil
}

func (m *MysqlDB) GetAllIndexes() (map[string]Index, error) {
	if m.indexes != nil {
		return m.indexes, nil
	}
	var err error
	m.indexes, err = m.fetchIndexes("student")
	if err != nil {
		return nil, err
	}
	return m.indexes, err
}

func (m *MysqlDB) GetAllTableNames() ([]string, error) {
	rows, err := m.db.QueryContext(*m.ctx, "SHOW TABLES")
	if err != nil {
		return nil, err
	}	
	tableNames := make([]string, 0) 
	for rows.Next() {
		var tn string
		if err := rows.Scan(&tn); err != nil {
			return nil, err
		}
		tableNames = append(tableNames, tn)
	}
	return tableNames, nil
}

func createConnection(info cfg.DBInfo) (*sql.DB, error) {
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

	return db, nil
}

func (m *MysqlDB) fetchIndexes(tableName string) (map[string]Index, error) {
	rows, err := m.db.QueryContext(*m.ctx, "SHOW INDEX FROM :table", sql.Named("table", tableName))
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	indexes := make(map[string]Index)

	for rows.Next() {
		var table *string
		var nonUnique *bool
		var keyName *string
		var seqInIndex *bool
		var columnName *string
		var collation *rune
		var cardinality *bool
		var subPart *any
		var packed *any
		var null *string
		var indexType *string
		var comment *string
		var indexComment *string
		var ignored *string
		if err := rows.Scan(table, nonUnique, keyName, seqInIndex, columnName, collation, cardinality, subPart, packed, null, indexType, comment, indexComment, ignored); err != nil {
			return nil, err
		}

		mysqlIn := MysqlIndex{}
		if table != nil {
			mysqlIn.Table = *table
		}
		if nonUnique != nil {
			mysqlIn.NonUnique = *nonUnique
		}
		if keyName != nil {
			mysqlIn.KeyName = *keyName
		}
		if seqInIndex != nil {
			mysqlIn.SeqInIndex = *seqInIndex
		}
		if columnName != nil {
			mysqlIn.ColumnName = *columnName
		}
		if collation != nil {
			mysqlIn.Collation = *collation
		}
		if cardinality != nil {
			mysqlIn.Cardinality = *cardinality
		}
		mysqlIn.SubPart = subPart
		mysqlIn.Packed = packed 
		if null != nil {
			mysqlIn.Null = *null
		}
		if indexType != nil {
			mysqlIn.IndexType = *indexType
		}
		if comment != nil {
			mysqlIn.Comment = *comment
		}
		if indexComment != nil {
			mysqlIn.IndexComment = *indexComment
		}
		if ignored != nil {
			mysqlIn.Ignored = *ignored
		}

		indexes[tableName] = &mysqlIn
	}

	return indexes, nil 
}

