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
// Convert editor DOM content into backend variable format like {{variable}}
export const syncValue = (editorRef, fieldKey, setFieldValue) => {
    if (!editorRef.current) return;
    const nodes = Array.from(editorRef.current.childNodes);
    const backendValue = nodes
        .map((node) => (node.dataset?.variable ? `{{${node.dataset.variable}}}` : node.textContent))
        .join("");
    setFieldValue(fieldKey, backendValue);
};
// Convert backend text containing {{variables}} into styled HTML variable tags
export const renderVariableHTML = (val, vars = []) => {
    if (!val) return "";

    return val
        .split(/(\s+)/)
        .map((word) => {
            const match = word.match(/^{{(.+)}}$/);

            if (match) {
                const key = match[1];
                const parts = key.split(".");
                const indexNumber = parts[0];
                const actualKey = parts.slice(1).join(".");

                const variableObj = (vars || [])
                    .flatMap((v) => v.variables || [])
                    .find((v) => v.key === actualKey);

                const label = variableObj?.label || actualKey;

                // show number in UI
                const displayLabel = `${indexNumber}. ${formatVariableKey(label)}`;

                return `<span class="zaplane-variable-item" data-variable="${key}">
                        <span class="zaplane-variable-label">${displayLabel}</span>
                         <span class="zaplane-variable-remove">&times;</span>
                        </span>`;
            }

            return word;
        })
        .join("");
};
// Convert backend text containing {{variables}} into styled HTML variable tags
export const insertVariableAtRange = ({
    range,
    variableKey,
    variables,
    editorRef,
    setActiveRange,
    setPopoverOpen,
    syncValueFn,
}) => {
    if (!editorRef.current || !range) return;

    const variableObj = variables.flatMap(v => v.variables || []).find(v => v.key === variableKey);
    const displayLabel = variableObj?.label || variableKey;

    // Create span
    const span = document.createElement("span");
    span.className = "zaplane-variable-item";
    span.setAttribute("data-variable", variableKey);

    const labelSpan = document.createElement("span");
    labelSpan.textContent = displayLabel;
    labelSpan.className = "zaplane-variable-label";

    const removeSpan = document.createElement("span");
    removeSpan.innerHTML = "&times;";
    removeSpan.className = "zaplane-variable-remove";

    span.appendChild(labelSpan);
    span.appendChild(removeSpan);

    // Ensure range is still inside editor
    const editor = editorRef.current;
    if (!editor.contains(range.startContainer)) {
        // Fallback: place cursor at end
        range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false);
    }
    range.collapse(true);
    range.insertNode(span);
    const space = document.createTextNode(" ");
    span.parentNode.insertBefore(space, span.nextSibling);
    const newRange = document.createRange();
    newRange.setStartAfter(space);
    newRange.collapse(true);

    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(newRange);
    // Save the new range and update
    setActiveRange(newRange);
    setPopoverOpen(false);

    if (syncValueFn) syncValueFn();
};
