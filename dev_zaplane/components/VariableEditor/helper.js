export const insertVariableIntoGroup = ({
    activeInput,
    groups,
    groupHelpers,
    valueToInsert,
    setPopoverOpen,
    setActiveInput,
}) => {
    if (!activeInput) return;

    const { gIndex, rIndex, fieldKey } = activeInput;
    const newGroups = [...groups];
    const currentRule = newGroups[gIndex][rIndex];

    let baseValue = currentRule[fieldKey] || "";
    if (baseValue.endsWith("@")) baseValue = baseValue.slice(0, -1);

    // Append selected variable remove space " "
    const newValue = baseValue ? `${baseValue}${valueToInsert}` : valueToInsert;

    newGroups[gIndex][rIndex] = {
        ...currentRule,
        [fieldKey]: newValue,
    };
    groupHelpers.replace(gIndex, newGroups[gIndex]);
    setPopoverOpen(false);
    setActiveInput(null);
}
export const formatVariableKey = (key) => {
    if (!key) return "";

    return key
        .split(".")
        .pop()
        .replace(/\[\]/g, "")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());
};
export const getContextVariableGroups = (context = {}) => (
    ["workflow", "wp"].map((key) => {
        const group = context?.[key] || {};

        return {
            key,
            label: group.label || formatVariableKey(key),
            prefix: group.prefix || key,
            variables: group.variables || [],
        };
    })
);

export const getVariableDisplayLabel = (variableKey, variables = [], context = {}) => {
    const contextVariable = getContextVariableGroups(context)
        .flatMap((group) => (
            (group.variables || []).map((variable) => ({
                ...variable,
                variableKey: `${group.prefix}.${variable.key}`,
            }))
        ))
        .find((variable) => variable.variableKey === variableKey);

    if (contextVariable) {
        return contextVariable.label || formatVariableKey(contextVariable.key);
    }

    const appVariable = (variables || [])
        .flatMap((item) => (
            (item.variables || []).map((variable) => ({
                ...variable,
                variableKey: `${item.node_id}.${variable.key}`,
            }))
        ))
        .find((variable) => variable.variableKey === variableKey || variable.key === variableKey);

    return appVariable?.label || formatVariableKey(appVariable?.key || variableKey);
};
// Save current cursor/selection position from the editor
export const saveSelection = () => {
    const sel = window.getSelection();
    if (!sel.rangeCount) return null;
    return sel.getRangeAt(0);
};
// Restore previously saved cursor/selection back to the editor
export const restoreSelection = (range) => {
    if (!range) return;
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
};
// Convert editor DOM content into backend variable format like {{variable}}.
// Walks the tree so a variable chip serialises to its token even if it ends up
// nested, and free text around it is preserved (not dropped).
export const syncValue = (editorRef, fieldKey, setFieldValue) => {
    if (!editorRef.current) return;

    const serialize = (node) => {
        if (node.nodeType === Node.TEXT_NODE) return node.textContent;
        if (node.nodeType !== Node.ELEMENT_NODE) return "";
        if (node.dataset && node.dataset.variable) return `{{${node.dataset.variable}}}`;
        if (node.tagName === "BR") return "\n";
        return Array.from(node.childNodes).map(serialize).join("");
    };

    const backendValue = Array.from(editorRef.current.childNodes).map(serialize).join("");
    setFieldValue(fieldKey, backendValue);
};
// Escape a string for safe insertion as HTML text.
const escapeHtml = (str) =>
    String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");

// Convert backend text containing {{variables}} into styled HTML variable tags.
// Tokens are matched ANYWHERE (not only as whitespace-delimited words), so a
// variable placed right next to text (e.g. "hi{{workflow.name}}") still renders
// as a chip on reload. Chips are contenteditable=false so typing next to one
// never merges into it.
export const renderVariableHTML = (val, vars = [], context = {}) => {
    if (!val) return "";

    return String(val)
        .split(/(\{\{.*?\}\})/g)
        .map((part) => {
            const match = part.match(/^\{\{(.+)\}\}$/);
            if (match) {
                const key = match[1];
                const displayLabel = getVariableDisplayLabel(key, vars, context);

                return `<span class="zaplane-variable-item" contenteditable="false" data-variable="${escapeHtml(key)}"><span class="zaplane-variable-label">${escapeHtml(displayLabel)}</span><span class="zaplane-variable-remove">&times;</span></span>`;
            }
            return escapeHtml(part);
        })
        .join("");
};
// Convert backend text containing {{variables}} into styled HTML variable tags
export const insertVariableAtRange = ({
    range,
    variableKey,
    variables,
    variableContext,
    editorRef,
    setActiveRange,
    setPopoverOpen,
    syncValueFn,
}) => {
    const displayLabel = getVariableDisplayLabel(variableKey, variables, variableContext);

    const span = document.createElement("span");
    span.className = "zaplane-variable-item";
    span.setAttribute("data-variable", variableKey);
    // Atomic: typing next to the chip never merges into it.
    span.setAttribute("contenteditable", "false");

    const labelSpan = document.createElement("span");
    labelSpan.textContent = displayLabel;
    labelSpan.className = "zaplane-variable-label";

    const removeSpan = document.createElement("span");
    removeSpan.innerHTML = "&times;";
    removeSpan.className = "zaplane-variable-remove";

    span.appendChild(labelSpan);
    span.appendChild(removeSpan);

    restoreSelection(range);

    if (range.startContainer.nodeType === Node.TEXT_NODE) {
        const textContent = range.startContainer.textContent;
        const startOffset = range.startOffset;
        // Only strip a legacy trigger "@" sitting immediately before the caret —
        // never reach back and delete an unrelated earlier literal "@".
        if (startOffset > 0 && textContent[startOffset - 1] === "@") {
            range.setStart(range.startContainer, startOffset - 1);
        }
    }

    range.deleteContents();
    range.insertNode(span);

    const space = document.createTextNode(" ");
    span.parentNode.insertBefore(space, span.nextSibling);

    const newRange = document.createRange();
    newRange.setStartAfter(space);
    newRange.collapse(true);
    restoreSelection(newRange);

    setActiveRange(newRange);
    setPopoverOpen(false);

    if (syncValueFn) syncValueFn();
};
