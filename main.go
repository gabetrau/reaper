package main

import (
	"log"

	"github.com/gabetrau/reaper/cfg"
	"github.com/gabetrau/reaper/cmd"
)

func main() {
	cfg, err := cfg.GetConfig()
	if err != nil {
		log.Fatalln(err.Error())
	}
	cmd.Execute(cfg)
}
