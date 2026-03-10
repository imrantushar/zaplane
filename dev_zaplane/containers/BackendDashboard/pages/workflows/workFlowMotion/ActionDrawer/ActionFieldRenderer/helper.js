const insertVariable = (variableKey) => {
    if (!activeRange) return;
    restoreSelection(activeRange);

    const span = document.createElement("span");
    span.textContent = `{{${variableKey}}}`;
    span.setAttribute("data-variable", "true");
    span.style.background = "#E0F2FF";
    span.style.borderRadius = "4px";
    span.style.padding = "0 4px";
    span.style.margin = "0 2px";

    activeRange.deleteContents();
    activeRange.insertNode(span);

    const space = document.createTextNode(" ");
    span.parentNode.insertBefore(space, span.nextSibling);

    const newRange = document.createRange();
    newRange.setStartAfter(space);
    newRange.collapse(true);
    restoreSelection(newRange);

    setActiveRange(newRange);
    setPopoverOpen(false);

    // Update value
    setValue(Array.from(editorRef.current.children).map(c => c.textContent).join(" "));
};