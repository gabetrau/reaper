package cmd

import (
	"github.com/spf13/cobra"
	"github.com/spf13/viper"
)

var (
	// Used for flags.
	cfgFile     string
	userLicense string

	rootCmd = &cobra.Command{
		Use:   "reaper",
		Short: "A sql db migration tool",
		Long: `Reaper is a CLI tool that allows for duplicating databases with the ability to obfuscate sensitive data 
		for testing environments`,
	}
)

// Execute executes the root command.
func Execute() error {
	return rootCmd.Execute()
}

