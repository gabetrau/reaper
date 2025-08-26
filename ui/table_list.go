package ui

import (
	"strings"
	"time"

	"github.com/charmbracelet/bubbles/progress"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
)

const (
	padding = 4
	maxWidth = 200
	maxTableNameLen = 15
)

var helpStyle = lipgloss.NewStyle().Foreground(lipgloss.Color("#626262")).Render

type tickMsg time.Time

type Table struct {
	Name string
	// 0.0 - 1.0
	Percent float64
	progress progress.Model
}

type TablesView struct {
	Tables []Table
	Finished *bool
}

func (tv TablesView) Init() tea.Cmd {
	return tv.tickCmd() 
}

func (tv TablesView) View() string {
	pad := strings.Repeat(" ", padding)
	output := "\n"
	for _, t := range tv.Tables {
		wordspace := strings.Repeat(" ", maxTableNameLen - len(t.Name))
		if t.Percent < 100 {
			output += pad + t.Name + wordspace + t.progress.ViewAs(t.Percent) + "\n"
		}
	}
	output += "\n" + pad + helpStyle("press ctrl+c to quit") + "\n\n"
	return output
} 

func (tv TablesView) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
    switch msg := msg.(type) {
    case tea.KeyMsg:
        switch msg.String() {
        case "ctrl+c", "q":
            return tv, tea.Quit
		}
	case tea.WindowSizeMsg:
		for _, t := range tv.Tables {
			t.progress.Width = min(msg.Width - padding*2 - 4, maxWidth)
		}
	case tickMsg:
		if *tv.Finished {
			return tv, tea.Quit
		}
		for i := 0; i < len(tv.Tables); i++ {
			tv.Tables[i].Percent += float64(i) * 0.1 + .1
		}
		return tv, tv.tickCmd()
	default:
		return tv, nil
    }

    return tv, nil
}

func (tv TablesView) tickCmd() tea.Cmd {
	return tea.Tick(time.Millisecond * 50, func(t time.Time) tea.Msg {
		notFinished := false 
		for _, t := range tv.Tables {
			if t.Percent < 1.0  {
				notFinished = true 
			}
		}
		*tv.Finished = !notFinished
		return tickMsg(t)
	})
}

func NewTable(name string, percent float64) Table {
	return Table{
		Name: name,
		Percent: percent,
		progress: progress.New(progress.WithSolidFill("#8E73FF")),
	}
}

