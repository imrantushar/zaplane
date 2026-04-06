# Zaplane - Frontend Developer Documentation

## Project Overview

Zaplane is a WordPress automation plugin with a sophisticated React-based admin interface. The frontend provides a visual workflow builder resembling tools like n8n and Zapier, allowing users to create automation workflows through a drag-and-drop node-based editor.

**Technology Stack:**
- React 18 with functional components and hooks
- Redux Toolkit for state management
- @xyflow/react for node graph visualization
- Chakra UI v3 for component library
- React Router v6 for navigation
- WordPress Scripts for build tooling
- SCSS for styling with CSS Modules

---

## Directory Structure

### Root Level

**dev_zaplane/** - Complete React application source code
- All development source files live here
- Built assets output to `../assets/build/`

**assets/** - Compiled static assets (generated)
- `build/` - Contains compiled app.js and app.css
- `scss/` - Source SCSS files (some shared with source)
- `images/`, `json/`, `library/` - Static resources

---

## dev_zaplane/ - Application Source

### app.js
Main application entry point. Bootstraps React, sets up providers (Redux, Chakra UI, Router), and renders the app to the `#zaplane-app` container. Handles admin menu injection via React portal.

### app.scss
Global stylesheet entry point that imports all SCSS partials.

---

## components/ - Reusable UI Components

Atomic design system of presentational components. Each component is self-contained in its own directory with styles.

### ZAP Components (Design System)
Custom wrapper components built on Chakra UI:

- **ZAPInput** - Form text input with label, validation, and error states
- **ZAPSelect** - Dropdown select with search and multi-select support
- **ZAPDatePicker** - Date selection using react-datepicker
- **ZAPIcon** - Icon renderer using Lucide React icons
- **ZAPTooltip** - Tooltip wrapper for hover information
- **ZAPAlert** - Alert banners (success, error, warning, info)
- **ZAPDivider** - Visual separator line
- **ZAPActionBar** - Action button toolbar
- **ZAPInputGroup** - Input with button attachments

### Chakra Custom Components
Extended Chakra UI components with consistent styling:

- **ZAPButton** - Enhanced button with variants (primary, secondary, ghost, danger)
- **ZAPHeading** - Typography component with size scale
- **ZAPText** - Text component with color/weight variants
- **ZAPBadge** - Status badges and tags
- **ZAPAccordion** - Collapsible panels
- **ZAPCard** - Card container with header/body/footer
- **ZAPModal** - Modal dialog wrapper

### Layout Components
- **Modal** - Dialog wrapper using react-modal
- **Drawer** - Slide-out side panel
- **Table** - Generic table with sorting and pagination
- **ListTable** - List view with row actions
- **Pagination** - Page navigation controls
- **Tab** - Tabbed navigation interface

### Specialized Components
- **Search** - Search input with debouncing
- **StatusOptions** - Status display and selection components
- **Labels** - Tag and label renderers
- **Loading** - Loading states (spinner, skeleton, full-page)
- **Notification** - Toast notification system
- **OptionMenu** - Three-dot context menu
- **WhatsNew** - Release notes modal
- **SubTopBar** - Secondary toolbar for pages
- **NavigationBlocker** - Prevents navigation with unsaved changes
- **VariableEditor** - Variable management interface
- **Oops** - Error and empty state pages
- **ZaplaneLoader** - Full-screen loading overlay

---

## containers/ - Page-Level Containers

Route-level components that compose pages and manage their data flow.

### BackendDashboard/
Main application container that renders within WordPress admin.

#### index.js
Root container component. Parses URL query parameters and routes to appropriate page component. Contains the notification system and page switcher logic.

#### AdminMenu/
Injects the Zaplane menu into WordPress admin sidebar using React portal.

#### pages/ - Application Pages

##### dashboard/
Dashboard overview page showing metrics and recent activity.

- **OverviewSection/** - Main dashboard widgets
  - `StatCard.js` - Metric display card
  - `OverviewSection.js` - Container for stat cards
- **ExecutedFlows.js** - Workflow execution chart/graph
- **TotalExecutions.js** - Total count display
- **RecentLogs.js** - Recent execution log list
- `index.js` - Main dashboard page component

##### workflows/
Workflow management interface.

- **index.js** - Workflows list page with table and create button
- **WorkflowTable.js** - Workflow listing with actions (edit, delete, duplicate, toggle status)
- **workFlowMotion/** - Visual workflow editor (the core feature)
  - `index.js` - Workflow editor container
  - **FlowCanvas/** - Main graph editor using @xyflow/react
    - `FlowCanvas.js` - Canvas component with ReactFlow
    - **FlowTopBar/** - Toolbar with save, test, publish, history buttons
      - `FlowTopBar.js` - Main toolbar component
    - **RunsTable/** - Workflow execution history table
    - **FloatingEdge/** - Custom edge rendering for connections
    - `helper.js` - Canvas utilities and configuration
  - **ActionDrawer/** - Right-side panel for node configuration
    - `ActionDrawer.js` - Main drawer component with tabs
    - **DrawerItemList/** - List of action/trigger nodes to add
      - `DrawerModeList.js` - Display modes (triggers vs actions)
      - `DrawerSearchList.js` - Searchable filtered list
      - `DrawerItemButton.js` - Individual node type button
    - **SelectTab/** - Tabbed configuration interface
      - `SelectTab.js` - Tab management
      - **ConnectionSelector/** - Connection picker component
        - `ConnectionSelector.js` - Dropdown for credentials
        - `ConnectionPopaver.js` - Tooltip/popper for connections
      - `TestRun.js` - Test execution component
      - `TestDetails.js` - Test results display
    - **ActionFieldRenderer/** - Dynamic form field renderer
      - `ActionFieldRenderer.js` - Renders config fields for nodes
    - **ConditionGroupField/** - Conditional logic builder
      - `ConditionGroupField.js` - Nested condition groups
    - `helper.js` - Drawer utilities
  - **CustomNode/** - Custom node components for the graph
    - `CustomNode.js` - Main node wrapper with header/body
    - **NodeHeader/** - Node title with actions
    - **NodeInput/** - Input port/handle
    - **NodeOutput/** - Output port/handle
    - **NodeStatus/** - Status indicator (running, success, error)
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

##### setting/
Plugin settings page.

- `index.js` - Settings form with groups

---

## hooks/ - Custom React Hooks

Reusable logic extracted into custom hooks:

- **useActionDrawer.js** - Manages ActionDrawer state (open/close, selected node, tab)
- **useApiCountdown.js** - Handles API rate limiting countdown timers
- **useFlowActions.js** - Workflow CRUD operations (create, update, delete, test, publish)

---

## redux/ - State Management

Redux Toolkit-based global state.

### store.js
Main Redux store configuration. Combines all slice reducers and adds middleware (redux-logger).

### Slices/ - Redux Slices

Each slice manages a specific domain of application state:

- **appSlice/** - Global app state (loading, errors, initialization)
- **menuSlice/** - Admin menu state and configuration
- **workFlowSlice/** - Workflow data, graph state, execution
- **connectionsSlice/** - Connections CRUD and state
- **logsSlice/** - Log data and filtering
- **dashboardSlice/** - Dashboard metrics and statistics
- **settingSlice/** - Settings values
- **notificationSlice/** - Toast notifications queue
- **localizeSlice/** - Internationalization strings

Each slice typically contains:
- `createAsyncThunk` actions for API calls
- Synchronous reducers for local state updates
- Selectors for reading state (often inline with `useSelector`)

---

## utils/ - Utility Functions

Pure utility functions and helpers:

- **api.js** - Axios HTTP client instance with interceptors and default configuration
- **constants.js** - Application constants (API endpoints, status values, default configs)
- **formatters.js** - Date, number, and data formatting utilities
- **helpers.js** - General helper functions (debounce, memoize, etc.)
- **helper.js** - URL query string parser (`useQuery` hook)
- **validators.js** - Form and data validation functions

---

## assets/scss/ - Styles

Sass stylesheets organized by purpose:

### chakra/
Chakra UI theme customization.

- **theme.scss** - Extends Chakra's default theme with Zaplane colors, components, and tokens

### common/
Shared SCSS partials:

- **variables.scss** - CSS custom properties and Sass variables
- **mixins.scss** - Reusable mixins
- **functions.scss** - Sass helper functions

### components/
Component-specific styles that aren't CSS Modules:

- Styles for global components or overrides

### backend.scss
Main entry point that imports all SCSS files in order.

---

## Build & Configuration Files

### jsconfig.json
JavaScript/TypeScript configuration for IDE path aliases. Defines the `@ZAP*` imports to work with editor autocomplete.

### webpack.config.js
Custom Webpack configuration extending `@wordpress/scripts` defaults. Defines:
- Entry point: `dev_zaplane/app.js`
- Output: `assets/build/app.js`
- Path aliases for imports (@ZAPComponents, @ZAPUtils, etc.)
- CleanWebpackPlugin for clean builds

---

## Key Features & Architecture

### Workflow Editor (workFlowMotion/)

The visual workflow builder is the core feature:

- **Canvas (FlowCanvas)** - Infinite canvas using @xyflow/react for pan/zoom
- **Nodes (CustomNode)** - Configurable action/trigger blocks
  - Each node type has: header (title, actions), body (config fields), status indicator
  - Multiple output ports for branching logic
- **Edges (CustomEdge)** - Connections between nodes with labels
- **ActionDrawer** - Right panel for configuring selected node
  - Tabbed interface: Configuration, Advanced, Testing
  - Dynamic form fields based on node type
  - Real-time validation
- **Validation** - Graph validation (required connections, cycles, limits)
- **Testing** - Run workflow test from editor
- **Versioning** - Save drafts, publish versions, view history

### Graph Data Structure

Workflows stored as JSON:
- `nodes[]` - Array of node objects (id, type, data, position)
- `edges[]` - Array of connections (source, target, sourceHandle, targetHandle)
- Node `data` contains: `app`, `action`, `config`,credentials reference

### State Flow

1. **API calls** → Redux async thunks → Update slice state
2. **Slice state** → `useSelector` hooks → Component props
3. **Component events** → Dispatch actions → Update state
4. **Workflow graph** - Stored in workFlowSlice, synced to backend via API

### Authentication

- Uses WordPress nonces in API headers (`X-WP-Nonce`)
- Admin menu only visible to `manage_options` capability
- API permission callbacks verify user capabilities

---

## Development Workflow

### Setup

1. Install dependencies: `npm install`
2. Start dev server: `npm start`
3. Access WordPress admin → Zaplane menu

### Development Commands

```bash
npm start              # Dev server with HMR
npm run build          # Production build
npm run lint:js        # Check JavaScript
npm run lint:js --fix  # Auto-fix JavaScript
npm run lint:css       # Check SCSS
npm run format         # Prettier format all
npm run generate-icons # Generate icon font from SVGs
```

### Adding New Components

1. Create directory in `components/` or appropriate location
2. Add component file, styles (CSS Module or SCSS), and index.js
3. Export from index.js for easy imports
4. Use path alias: `import Component from '@ZAPComponents/Component'`

### Adding New Pages

1. Create page component in `containers/BackendDashboard/pages/`
2. Add route in `BackendDashboard/index.js` renderSwitch
3. Register Redux slice if needed (or reuse existing)
4. Add menu item in admin menu if standalone

---

## Code Conventions

### Naming
- Components: PascalCase (`WorkflowTable`)
- Files: Match component name (`WorkflowTable.js`)
- Hooks: `use` prefix camelCase (`useWorkflowActions`)
- Utils: camelCase (`formatDate`)
- Styles: Same name as component (`Component.module.scss`)

### Imports
- Standard library imports first
- Third-party packages second
- Internal aliases third
- Alphabetical within each group

### Components
- Functional components with hooks (no class components)
- Destructure props in function signature
- PropTypes or TypeScript for type checking (future)
- Memoize expensive calculations with `useMemo`
- Memoize callbacks with `useCallback`

### State Management
- Use Redux for shared state (across multiple components)
- Use local `useState` for component-specific UI state
- Keep Redux state normalized (by ID)
- Use createAsyncThunk for all API calls

### Styling
- Prefer Chakra UI props for simple styling
- Use CSS Modules for complex component styles
- Use Styled Components for dynamic/programmatic styles
- Follow BEM naming for CSS Modules

---

## Testing

### Run Tests
```bash
npm run test:unit   # Jest unit tests
npm run test:e2e    # Playwright E2E tests
```

Test files co-located with components or in `__tests__/` folders.

---

## Performance Considerations

- Bundle size monitored via webpack analyzer (optional)
- Lazy loading for route-based code splitting (using React.lazy)
- Memoization for expensive operations
- Virtualization for large lists (future enhancement)
- Optimized re-renders with React.memo and proper dependency arrays

---

## Browser Support

Modern browsers (Chrome, Firefox, Safari, Edge) - last 2 versions.
IE11 not supported due to React 18 and modern dependencies.

---

## Dependencies Overview

### Critical Dependencies
- **react, react-dom** - UI framework
- **@reduxjs/toolkit, react-redux** - State management
- **react-router-dom** - Routing
- **@chakra-ui/react** - Component library & styling
- **@xyflow/react** - Workflow graph visualization
- **@dnd-kit/core, @dnd-kit/sortable** - Drag and drop
- **axios** - HTTP client
- **formik, yup** - Forms and validation
- **styled-components** - CSS-in-JS

### WordPress Integration
- **@wordpress/components** - WordPress admin UI components
- **@wordpress/hooks** - Hook system
- **@wordPress/i18n** - Translation functions

### Utilities
- **lodash** - Utility functions
- **moment-timezone** - Date/time handling
- **lucide-react, react-icons** - Icon libraries
- **recharts** - Charts for dashboard

---

## Troubleshooting

### Build Issues
- Clear node_modules and reinstall if modules missing
- Check webpack config for alias conflicts
- Verify WordPress admin loads JS file from `assets/build/app.js`

### State Not Updating
- Check Redux DevTools for state changes
- Ensure you're not mutating state directly in reducers
- Verify useSelector is selecting correct slice path

### Component Not Rendering
- Check element is in DOM (some components use portals)
- Verify parent component renders without errors
- Check browser console for React errors

### Performance Issues
- Use React DevTools Profiler
- Check for unnecessary re-renders
- Consider memoization for expensive operations
- Verify bundle size isn't excessive

---

## Resources

### Internal
- Backend API: `includes/api/api.php`
- Database schema: `includes/framework/database/migrations/`
- Integration system: `integrations/` directory

### External Documentation
- [React](https://react.dev/)
- [Redux Toolkit](https://redux-toolkit.js.org/)
- [Chakra UI](https://chakra-ui.com/)
- [React Flow](https://xyflow.dev/)
- [WordPress Scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)

---

**Document Version:** 1.0
**Last Updated:** 2025-04-06
**For:** Zaplane Frontend Developers