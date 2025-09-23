package ui

import (
	"fmt"
	"strconv"
	"strings"

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

type finishMsg bool 

type Table struct {
	Name string
	// 0.0 - 1.0
	currentPer *float64
	progress progress.Model
}

type Progress struct {
	Name string
	Percent float64
}

type TablesView struct {
	Tables []string
	TableMap map[string]Table
	ProgChan chan Progress 
	TblsInProg *int
}

func (tv TablesView) Init() tea.Cmd {
	return tv.finishCmd() 
}

func (tv TablesView) View() string {
	pad := strings.Repeat(" ", padding)
	output := "\n" + "Tables in progress: " + strconv.Itoa(*tv.TblsInProg) + "\n\n"
	for _, t := range tv.Tables {
		wordspace := strings.Repeat(" ", maxTableNameLen - len(tv.TableMap[t].Name))
		output += pad + tv.TableMap[t].Name + wordspace + tv.TableMap[t].progress.ViewAs(*tv.TableMap[t].currentPer) + "\n"
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
		for _, t := range tv.TableMap {
			t.progress.Width = min(msg.Width - padding*2 - 4, maxWidth)
		}
	case finishMsg:
		if msg {
			return tv, tea.Quit 
		}
		return tv, tv.finishCmd() 
	default:
		return tv, nil
    }

    return tv, nil
}

func (tv TablesView) finishCmd() tea.Cmd {	
	return func() tea.Msg {
		pm, ok := <-tv.ProgChan
		if ok {
			t, exists := tv.TableMap[pm.Name]
			if exists {
				*t.currentPer = pm.Percent
			} else {
				panic(fmt.Sprintf("Progress msg received for unknown table '%s'", pm.Name))
			}
			if pm.Percent == 1.0 {
				*tv.TblsInProg-- 
			}
		}
		return finishMsg(*tv.TblsInProg == 0)
	}
}

func NewTable(name string, perChan chan Progress) Table {
	per := float64(0.0)
	return Table{
		Name: name,
		currentPer: &per,
		progress: progress.New(progress.WithSolidFill("#8E73FF")),
	}
}

