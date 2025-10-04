package migrate 

import (
	"context"

	"github.com/gabetrau/reaper/cfg"
)

type Index interface {
	IndexSytax() string
}

type MigratoryDB[I Index] interface {
	Connect(ctx *context.Context, info cfg.DBInfo) error
	Close() error
	GetAllIndexes() (map[string]I, error)
	GetAllTableNames() ([]string, error)
}

func MakeRelationalDBs(ctx context.Context) (MigratoryDB[Index], MigratoryDB[Index], error) {
	config := ctx.Value("globalCfg").(cfg.ReaperCfg)

	var src MigratoryDB[Index]
	switch config.SourceDBInfo.Driver {
	case cfg.MySQL:
		src = &MysqlDB{}
		err := src.Connect(&ctx, config.SourceDBInfo)
		if err != nil {
			return nil, nil, err
		}
	default:
		panic("cannot connect to db, config should be valid")
	}

	var dest MigratoryDB[Index]
	switch config.DestDBInfo.Driver {
	case cfg.MySQL:
		dest = &MysqlDB{}
		err := dest.Connect(&ctx, config.DestDBInfo)
		if err != nil {
			return nil, nil, err
		}
	default:
		panic("cannot connect to db, config should be valid")
	}
	return src, dest, nil
}

