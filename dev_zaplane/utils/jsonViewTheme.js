// react-json-view's default theme hardcodes light-mode colors, which read as
// near-invisible on a dark panel. Mapping its base16 slots to our --zaplane-*
// CSS variables (rather than resolved hex values) lets the tree repaint
// itself when the app's light/dark mode flips, with no re-render needed.
//
// This library does NOT follow the generic base16 role convention (base05 =
// "default foreground" etc) — it repurposes slots for its own component
// styles. The mapping below is reverse-engineered from
// node_modules/react-json-view/dist/main.js's theme-to-style function, not
// guessed from the base16 spec:
//   base00 backgroundColor (app-container)
//   base01 editVariable.background, addKeyModal.labelColor
//   base02 objectBorder, dataTypes.background
//   base03 unused by this library
//   base04 objectSize (the "N items" text), addKeyModal.border
//   base05 dataTypes.undefined, addKeyModal.background
//   base06 unused by this library
//   base07 keyColor, braceColor, editVariable.border  <- must be legible, NOT the panel background
//   base08 dataTypes.nan
//   base09 ellipsisColor, dataTypes.string, editVariable.cancelIcon/removeIcon, validationFailure.background
//   base0A dataTypes.null/regexp, editVariable.color, addKeyModal.color
//   base0B dataTypes.float
//   base0C arrayKeyColor
//   base0D expandedIcon, dataTypes.date/function, copyToClipboardCheck
//   base0E collapsedIcon, dataTypes.boolean, editVariable.editIcon/addIcon/checkIcon
//   base0F copyToClipboard, dataTypes.integer
export const zaplaneJsonViewTheme = {
	base00: 'transparent', // let the parent panel's own background show through
	base01: 'var(--zaplane-secondary-color)',
	base02: 'var(--zaplane-border-color)',
	base03: 'var(--zaplane-font-secondary-color)',
	base04: 'var(--zaplane-text-muted)', // item-count text ("N items")
	base05: 'var(--zaplane-font-color)',
	base06: 'var(--zaplane-font-color)',
	base07: 'var(--zaplane-font-color)', // object/array keys + braces — was wrongly base-background, made keys invisible
	base08: 'var(--zaplane-danger)',
	base09: 'var(--zaplane-warning)', // string values
	base0A: 'var(--zaplane-primary)',
	base0B: 'var(--zaplane-success)',
	base0C: 'var(--zaplane-primary)', // array index labels
	base0D: 'var(--zaplane-primary)', // expand icon
	base0E: 'var(--zaplane-primary)', // collapse icon, boolean values
	base0F: 'var(--zaplane-danger)', // integer values
};
