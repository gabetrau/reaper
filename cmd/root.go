/*
Copyright © 2025 NAME HERE <EMAIL ADDRESS>
*/
package cmd

import (
	"fmt"
	"os"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/gabetrau/reaper/ui"
	"github.com/spf13/cobra"
)

// rootCmd represents the base command when called without any subcommands
var rootCmd = &cobra.Command{
	Use:   "reaper",
	Short: "Database migration tool",
	Long: `A CLI tool used to move data from one relational database to another. You
	can also obfuscate columns that contain sensitive information, making this useful
	for creating databases for testing environments.
	`,
	Run: func(cmd *cobra.Command, args []string) {
		p := tea.NewProgram(ui.InitialModel())
		if _, err := p.Run(); err != nil {
			fmt.Printf("Alas, there's been an error: %v", err)
			os.Exit(1)
		}
	},
}

// Execute adds all child commands to the root command and sets flags appropriately.
// This is called by main.main(). It only needs to happen once to the rootCmd.
func Execute() {
	err := rootCmd.Execute()
	if err != nil {
		os.Exit(1)
	}
}

