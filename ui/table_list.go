package ui

import (
	"fmt"
	"io"
	"strconv"
	"strings"

	"github.com/charmbracelet/bubbles/key"
	"github.com/charmbracelet/bubbles/list"
	"github.com/charmbracelet/bubbles/progress"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
)

const (
	padding = 4
	maxWidth = 200
	maxTableNameLen = 30
	listHeight = 15
)

var (
	titleStyle        = lipgloss.NewStyle().MarginLeft(2)
	itemStyle         = lipgloss.NewStyle().PaddingLeft(4)
	paginationStyle   = list.DefaultStyles().PaginationStyle.PaddingLeft(4)
	listHelpStyle	  = list.DefaultStyles().PaginationStyle.PaddingLeft(4).PaddingBottom(1)
	pad 			  = strings.Repeat(" ", padding)

)

type finishMsg bool 

type TableItemDelegate struct{}

func (d TableItemDelegate) Height() int {
	return 1 
}
func (d TableItemDelegate) Spacing() int {
	return 0 
}
func (d TableItemDelegate) Update(_ tea.Msg, _ *list.Model) tea.Cmd {
	return nil 
}
func (d TableItemDelegate) Render(w io.Writer, m list.Model, index int, listItem list.Item) {
	t, ok := listItem.(Table)
	if !ok {
		return
	}
	var displayName string
	var wordspace string
	if len(t.Name) >= maxTableNameLen {
		wordspace = pad
		displayName = t.Name[:maxTableNameLen]
	} else {
		wordspace = strings.Repeat(" ", maxTableNameLen - len(t.Name)) + pad
		displayName = t.Name
	}
	str := pad + displayName + wordspace + t.progress.ViewAs(*t.currentPer) + "\n"
	fn := itemStyle.Render
	fmt.Fprint(w, fn(str))
}

type Table struct {
	Name string
	// 0.0 - 1.0
	currentPer *float64
	progress progress.Model
}

func (t Table) FilterValue() string {
	return ""
}

type Progress struct {
	Name string
	Percent float64
}

type TablesView struct {
	List list.Model
	TableMap map[string]Table
	ProgChan chan Progress 
	TblsInProg *int
}

func (tv *TablesView) Init() tea.Cmd {
	tv.List.Title = strconv.Itoa(*tv.TblsInProg) + " Tables in Progress"
	tv.List.SetShowStatusBar(false)
	tv.List.SetFilteringEnabled(false)
	tv.List.SetShowHelp(true)
	tv.List.KeyMap.CursorUp.SetEnabled(false)
	tv.List.KeyMap.CursorDown.SetEnabled(false)
	tv.List.AdditionalShortHelpKeys = func() []key.Binding {
		next := tv.List.KeyMap.NextPage
		prev := tv.List.KeyMap.PrevPage
		next.SetHelp("→", "next page")
		prev.SetHelp("←", "prev page")
		return []key.Binding{prev, next}
	}

	tv.List.Styles.Title = titleStyle
	tv.List.Styles.HelpStyle = listHelpStyle 

	return tv.finishCmd() 
}

func (tv *TablesView) View() string {
	return tv.List.View()
} 

func (tv *TablesView) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
    switch msg := msg.(type) {
    case tea.KeyMsg:
        switch msg.String() {
        case "ctrl+c", "q":
            return tv, tea.Quit
		default:
			var cmd tea.Cmd
			tv.List, cmd = tv.List.Update(msg)
			return tv, cmd
		}
	case tea.WindowSizeMsg:
		tv.List.SetWidth(msg.Width)
	case finishMsg:
		if msg {
			return tv, tea.Quit 
		}
		tv.List.Update(msg)
		tv.List.Title = strconv.Itoa(*tv.TblsInProg) + " Tables in Progress"
		return tv, tv.finishCmd() 
    }

    return tv, nil
}

func (tv *TablesView) finishCmd() tea.Cmd {	
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

