/*
Copyright © 2025 Gabriel Rau 
*/
package cmd

import (
	"database/sql"
	"log"
	"os"
	"time"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/gabetrau/reaper/cfg"
	"github.com/gabetrau/reaper/cmd/shared"
	"github.com/gabetrau/reaper/ui"
	"github.com/spf13/cobra"
)



var globalCfg cfg.ReaperCfg

// rootCmd represents the base command when called without any subcommands
var rootCmd = &cobra.Command{
	Use:   "reaper",
	Short: "Database migration tool",
	Long: `A CLI tool used to move data from one relational database to another. You
	can also obfuscate columns that contain sensitive information, making this useful
	for creating databases for testing environments.
	`,
	Run: func(cmd *cobra.Command, args []string) {
		tables := []string{"student", "address", "country", "book", "player", "mob", "pin", "subject", "weapon", "strategy"}
		
		src, dest, err := shared.ConnectToDbs(globalCfg)
		if err != nil {
			log.Fatalf("ping error %v", err)
		}

		progChan := make(chan ui.Progress)
		tableMap := make(map[string]ui.Table)
		for _, e := range tables {
			go copyTableData(src, dest, e, progChan)
			tableMap[e] = ui.NewTable(e, progChan)
		}
		p := tea.NewProgram(ui.TablesView{
			Tables: tables,
			TableMap: tableMap,
			ProgChan: progChan,
			Finished: len(tables),
		})
		if _, err := p.Run(); err != nil {
			log.Printf("Alas, there's been an error: %v", err)
		}
		
	},
}

// Execute adds all child commands to the root command and sets flags appropriately.
// This is called by main.main(). It only needs to happen once to the rootCmd.
func Execute(cfg *cfg.ReaperCfg) {
	globalCfg = *cfg
	err := rootCmd.Execute()
	if err != nil {
		os.Exit(1)
	}
}

func copyTableData(src *sql.DB, dest *sql.DB, ent string, c chan ui.Progress) {
	for i := range 10 {
		time.Sleep(time.Duration(len(ent)) * time.Millisecond * 50)
		per := (float64(.1) * float64(i + 1))
		c <- ui.Progress{
			Name: ent,
			Percent: per,
		} 
	}
}

