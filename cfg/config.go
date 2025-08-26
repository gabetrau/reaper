package cfg

import (
	"fmt"
	"log"
	"os"

	"github.com/spf13/viper"
)

type SupportedDriver string

const (
	Maria SupportedDriver = "maria"
	MySQL SupportedDriver = "mysql"
	PostgreSQL SupportedDriver = "postgres"
	SQLite SupportedDriver = "sqlite"
)

type ReaperCfg struct {
	SourceDBInfo DBInfo
	DestDBInfo DBInfo
}

type DBInfo struct {
	Host string
	DB string
	User string
	Passwd string
	Port string
	Driver SupportedDriver
}

func GetConfig() (*ReaperCfg, error) {
	cfgPath := os.Getenv("REAPER_PATH") 
	if cfgPath == "" {
		cfgPath = os.Getenv("HOME")
		if cfgPath == "" {
			var err error
			cfgPath, err = os.Getwd()
			if err != nil {
				log.Fatal(err)
			}
		}
	}
	viper.AddConfigPath(cfgPath)
	viper.SetConfigName(".reaper")
	viper.SetConfigType("yaml")
	viper.ReadInConfig()

	sourceDriver, err := kindOf(viper.GetString("source.driver"))
	if err != nil {
		return nil, err
	}
	destDriver, err := kindOf(viper.GetString("destination.driver"))
	if err != nil {
		return nil, err
	}
	return &ReaperCfg{
		SourceDBInfo: DBInfo{
			Host: viper.GetString("source.host"),
			DB: viper.GetString("source.db"),
			User: viper.GetString("source.username"),
			Passwd: viper.GetString("source.password"),
			Port: viper.GetString("source.port"),
			Driver: sourceDriver,
		},
		DestDBInfo: DBInfo{
			Host: viper.GetString("destination.host"),
			DB: viper.GetString("destination.db"),
			User: viper.GetString("destination.username"),
			Passwd: viper.GetString("destination.password"),
			Port: viper.GetString("destination.port"),
			Driver: destDriver,
		},
	}, nil
}

func kindOf(s string) (SupportedDriver, error) {
	switch s {
	case string(Maria):
		return Maria, nil
	case string(MySQL):
		return MySQL, nil
	case string(PostgreSQL):
		return PostgreSQL, nil
	case string(SQLite):
		return SQLite, nil
	default:
		return "", fmt.Errorf("%s database is not supported", s) 
	}
}

