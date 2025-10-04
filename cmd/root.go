/*
Copyright © 2025 Gabriel Rau
*/
package cmd

import (
	"context"
	"fmt"
	"log"
	"os"
	"time"

	"github.com/charmbracelet/bubbles/list"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/gabetrau/reaper/cfg"
	"github.com/gabetrau/reaper/migrate"
	"github.com/gabetrau/reaper/ui"
	"github.com/spf13/cobra"
)

const (
	defaultWidth = 40
	listHeight = 14
)

var (
	globalCfg cfg.ReaperCfg

	// rootCmd represents the base command when called without any subcommands
	rootCmd = &cobra.Command{
		Use:   "reaper",
		Short: "Database migration tool",
		Long: `A CLI tool used to move data from one relational database to another. You
		can also obfuscate columns that contain sensitive information, making this useful
		for creating databases for testing environments.
		`,
		Run: func(cmd *cobra.Command, args []string) {
			ctx := context.WithValue(context.Background(), "globalCfg", globalCfg)
			src, dest, err := migrate.MakeRelationalDBs(ctx)
			if err != nil {
				log.Fatalf("ping error %v", err)
			}
			defer src.Close()
			defer dest.Close()

			fmt.Printf("\n")
			tables, err := src.GetAllTableNames() 
			if err != nil {
				log.Fatalf("unable to get table names from source %v", err)
			}

			progChan := make(chan ui.Progress)
			tableMap := make(map[string]ui.Table)
			listItems := make([]list.Item, 0)
			for _, t := range tables {
				go copyTableData(src, dest, t, progChan)
				tableMap[t] = ui.NewTable(t, progChan)
				listItems = append(listItems, tableMap[t])
			}
			l := list.New(listItems, ui.TableItemDelegate{}, defaultWidth, listHeight)
			tblsInProg := len(tables)
			if tblsInProg == 0 {
				log.Printf("No tables found to copy")
				return
			}
			
			p := tea.NewProgram(&ui.TablesView{
				List: l,
				TableMap: tableMap,
				ProgChan: progChan,
				TblsInProg: &tblsInProg,
			})
			if _, err := p.Run(); err != nil {
				log.Printf("Alas, there's been an error: %v", err)
			}	
		},
	}
)

// Execute adds all child commands to the root command and sets flags appropriately.
// This is called by main.main(). It only needs to happen once to the rootCmd.
func Execute(cfg *cfg.ReaperCfg) {
	globalCfg = *cfg
	err := rootCmd.Execute()
	if err != nil {
		os.Exit(1)
	}
}

func copyTableData(src migrate.MigratoryDB[migrate.Index], dest migrate.MigratoryDB[migrate.Index], table string, c chan ui.Progress) {
	for i := range 10 {
		time.Sleep(time.Duration(len(table)) * time.Millisecond * 50)
		per := (float64(.1) * float64(i + 1))
		c <- ui.Progress{
			Name: table,
			Percent: per,
		} 
	}
}

