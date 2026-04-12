# Zaplane - Frontend Developer Documentation


##### dashboard/
Dashboard overview page showing metrics and recent activity.

- **OverviewSection/** - Main dashboard widgets
  - `OverviewSection.js` - There have deshboard overview part
- **ExecutedFlows.js** - Workflow execution chart/graph
- **TotalExecutions.js** - Total count display
- **RecentLogs.js** - Recent execution log list

##### workflows/
Workflow management interface.

- **index.js** - Workflows list page with table and create button
- **WorkflowTable.js** - Workflow listing with actions (edit, delete, duplicate, toggle status,export also create recipy )
- **workFlowMotion/** - workflow  (the core feature is here)
  - `index.js` - Workflow editor container also have formik parent point
  - **FlowCanvas/** - Main graph 
    - `FlowCanvas.js` - react flow mainly show hare its parent point
    - **FlowTopBar/** - Toolbar with save, test, publish, history buttons,title name export 
      - `FlowTopBar.js` - Main toolbar component
    - **RunsTable/** - Workflow execution history table is here
    - **FloatingEdge/** - Custom edge last adding node connect ui show from here i made id using custom logic becuse its pro feated react flow pacage
    - `helper.js` - Canvas utilities and configuration
  - **ActionDrawer/** - Right-side panel for when node add click
    - `ActionDrawer.js` - Main drawer component with tabs
    - **DrawerItemList/** - List of action/trigger nodes to add
      - `DrawerModeList.js` - Display modes (triggers vs actions)
      - `DrawerSearchList.js` - Searchable filtered list
      - `DrawerItemButton.js` - Individual node type button
    - **SelectTab/** - Tabbed configuration interface
      - `SelectTab.js` - Tab management
      - **ConnectionSelector/** - Connection third party api intregation part is here
        - `ConnectionSelector.js` - Dropdown for credentials
        - `ConnectionPopaver.js` - Tooltip/popper for connections
      - `TestRun.js` - Test execution component
      - `TestDetails.js` - Test results display
    - **ActionFieldRenderer/** - Dynamic form field renderer
      - `ActionFieldRenderer.js` - Renders config fields for nodes
    - **ConditionGroupField/** - Conditional logic builder also filter logice builder
      - `ConditionGroupField.js` - Nested condition groups
    - `helper.js` - Drawer utilities
  - **CustomNode/** - Custom node components for the graph
    - `CustomNode.js` - Main node wrapper with header/body
    - **NodeInput/** - Input port/handle
    - **NodeOutput/** - Output port/handle
  - **CustomEdge/** - Custom edge/connection rendering
    - `CustomEdge.js` - Edge component with labels and styles
  - `helper.js` - Workflow utilities (graph operations, validation)

##### connections/
Connection management for external integrations.

- **index.js** - Connection list page with create button
- **ConnectionTable.js** - Table of connections with status and actions
- **ConnectionDetails/**
  - `ConnectionDetails.js` - Single connection view/edit form
  - Connection form fields (credentials, config)

##### logs/
Workflow execution logs viewer.

- `index.js` - Logs page with filtering and table



## hooks/ - Custom React Hooks

Reusable logic extracted into custom hooks:

- **useActionDrawer.js** -it use actiondrawer.js:- Manages ActionDrawer state (open/close, selected node, tab)
- **useApiCountdown.js** - Handles API rate limiting countdown timers
- **useFlowActions.js** -it use  flowcanvas.js:- Workflow CRUD operations (create, update, delete, test, publish)

