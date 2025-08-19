package cfg

import (
	"fmt"
	"log"
	"os"

	"github.com/spf13/viper"
)

type ReaperCfg struct {
	ReapDb string
	SowDb string
}

func GetConfig() *ReaperCfg {
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

	return &ReaperCfg{
		ReapDb: viper.GetString("reap-db"),
		SowDb: viper.GetString("sow-db"),
	}
}

