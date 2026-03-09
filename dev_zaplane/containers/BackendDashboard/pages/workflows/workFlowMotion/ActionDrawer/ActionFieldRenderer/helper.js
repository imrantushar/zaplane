export const insertVariableAtCursor = ({
  variable,
  inputRef,
  fieldKey,
  setFieldValue,
  setPopoverOpen,
}) => {
  const el = inputRef?.current;
  if (!el) return;
  const currentVal = el.value || "";
  const cursorPos = el.selectionStart ?? currentVal.length;
  const before = currentVal.slice(0, cursorPos).replace(/@$/, "");
  const after = currentVal.slice(cursorPos);
  // remove space " "
  const newVal = before + variable;
  setFieldValue(fieldKey, newVal + after);
  setPopoverOpen(false);
  setTimeout(() => {
    const newCursorPos = before.length + variable.length + 1;
    el.focus();
    el.setSelectionRange(newCursorPos, newCursorPos);
  }, 0);
};