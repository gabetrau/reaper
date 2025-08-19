package main

import (
	"github.com/gabetrau/reaper/cfg"
	"github.com/gabetrau/reaper/cmd"
)

func main() {
	cmd.Execute(cfg.GetConfig())
}
