// helper.js
export const insertVariableHelper = ({
  variable,
  value,
  cursorPosition,
  setFieldValue,
  fieldKey,
  inputRef,
  setPopoverOpen
}) => {
  if (cursorPosition === null) return;

  let before = value.slice(0, cursorPosition);
  const after = value.slice(cursorPosition);

  // check and remove last @ only
  if (before.endsWith("@")) before = before.slice(0, -1);

  // add variable + space
  const newValue = before + variable + " " + after;

  // update Formik value
  setFieldValue(fieldKey, newValue);

  // close popover
  setPopoverOpen(false);

  // restore cursor
  setTimeout(() => {
    if (inputRef.current) {
      const newCursor = before.length + variable.length + 1; // +1 for space
      inputRef.current.focus();
      inputRef.current.setSelectionRange(newCursor, newCursor);
    }
  }, 0);
};
