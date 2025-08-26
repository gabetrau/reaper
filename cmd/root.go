/*
Copyright © 2025 NAME HERE <EMAIL ADDRESS>
*/
package cmd

import (
	"fmt"
	"log"
	"os"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/gabetrau/reaper/cfg"
	"github.com/gabetrau/reaper/data"
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
		src, err := data.Connect(globalCfg.SourceDBInfo)
		if err != nil {
			log.Fatalf(err.Error())
		}
		srcPingErr := src.Ping()
		if srcPingErr != nil {
			log.Fatalf("source ping error: %v", srcPingErr)
		}
		fmt.Printf("Source Connected!\n")

		dest, err := data.Connect(globalCfg.DestDBInfo)
		if err != nil {
			log.Fatalf(err.Error())
		}
		destPingErr := dest.Ping()
		if destPingErr != nil {
			log.Fatalf("destination ping error: %v", destPingErr)
		}
		fmt.Printf("Source Connected!\n\n")

		var tables []ui.Table
		finished := new(bool)
		*finished = false
		tables = append(tables, ui.NewTable("student", 0.0))
		tables = append(tables, ui.NewTable("address", 0.0))
		tables = append(tables, ui.NewTable("chess", 0.0))

		p := tea.NewProgram(ui.TablesView{
			Tables: tables,
			Finished: finished,
		})
		if _, err := p.Run(); err != nil {
			fmt.Printf("Alas, there's been an error: %v", err)
			os.Exit(1)
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

