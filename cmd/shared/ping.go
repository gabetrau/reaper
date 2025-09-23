package shared

import (
	"database/sql"
	"log"

	"github.com/gabetrau/reaper/cfg"
	"github.com/gabetrau/reaper/data"
)

func ConnectToDbs(cfg cfg.ReaperCfg) (*sql.DB, *sql.DB, error) {
	src, err := data.Connect(cfg.SourceDBInfo)
	if err != nil {
		log.Fatalf(err.Error())
	}
	srcPingErr := src.Ping()
	if srcPingErr != nil {
		log.Fatalf("source ping error: %v", srcPingErr)
	}
	log.Printf("Source Connected!\n")

	dest, err := data.Connect(cfg.DestDBInfo)
	if err != nil {
		log.Fatalf(err.Error())
	}
	destPingErr := dest.Ping()
	if destPingErr != nil {
		log.Fatalf("destination ping error: %v", destPingErr)
	}
	log.Printf("Source Connected!\n\n")
	return src, dest, nil
}

