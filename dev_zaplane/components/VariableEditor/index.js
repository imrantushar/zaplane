import React, { useRef, useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import VariablePopover from "./VariablePopover";
import { saveSelection, restoreSelection, syncValue, renderVariableHTML, insertVariableAtRange } from "./helper";
import "./styles.scss";
const VariableEditor = ({
  value,
  setFieldValue,
  field,
  variables,
  variableContext,
  label,
  placeholder,
  containerStyle,
  isRequired = false,
  multiline = true
}) => {
  const editorRef = useRef(null);
  const [isPopoverOpen, setPopoverOpen] = useState(false);
  const [activeRange, setActiveRange] = useState(null);
  const [isEmpty, setIsEmpty] = useState(!value);

  // render value when load/change
  const initialized = useRef(false);
  useEffect(() => {
    if (!editorRef.current) return;
    if (!initialized.current) {
      if (value) {
        editorRef.current.innerHTML = renderVariableHTML(value, variables, variableContext);
      }
      initialized.current = true;
    }
    setIsEmpty(!value || value.trim() === "");
  }, [value, variables, variableContext]);

  // remove variable
  useEffect(() => {
    const editor = editorRef.current;
    if (!editor) return;
    const handleRemoveClick = e => {
      if (e.target.classList.contains("zaplane-variable-remove")) {
        const span = e.target.parentNode;
        span.remove();
        syncValue(editorRef, field.key, setFieldValue);
        const text = editor.textContent.trim();
        setIsEmpty(text === "");
      }
    };
    editor.addEventListener("click", handleRemoveClick);
    return () => editor.removeEventListener("click", handleRemoveClick);
  }, [field.key, setFieldValue]);

  // detect editor empty
  const handleInput = () => {
    if (!editorRef.current) return;
    const editor = editorRef.current;
    if (editor.innerHTML === "<br>") {
      editor.innerHTML = "";
    }
    const text = editor.textContent.trim();
    setIsEmpty(text === "");
    // The user is typing free text — get the picker out of the way.
    if (isPopoverOpen) setPopoverOpen(false);
  };

  // open popover when @ typed
  const handleKeyDown = e => {
    if (e.key === "Escape" && isPopoverOpen) {
      e.preventDefault();
      setPopoverOpen(false);
      return;
    }
    if (!multiline && e.key === "Enter") {
      e.preventDefault();
      return;
    }
    if (e.key === "@") {
      // Don't insert the literal "@" — just open the picker at the current caret.
      // Selecting a variable inserts it here; dismissing leaves nothing behind, so
      // there's never a stray "@" to delete before triggering the picker again.
      e.preventDefault();
      setActiveRange(saveSelection());
      setPopoverOpen(true);
    }
  };

  // paste plain text only, stripping newlines on single-line fields
  const handlePaste = e => {
    e.preventDefault();
    const text = e.clipboardData.getData("text/plain").replace(/[\r\n]+/g, " ");
    document.execCommand("insertText", false, text);
  };

  // save cursor inside editor
  const handleCursorSave = () => {
    const range = saveSelection();
    setActiveRange(range);
  };

  // Clicking into any field opens the variable picker directly — no need to type
  // "@" (which still works too). Clicks on an existing variable's remove (×)
  // button are ignored so deleting a chip doesn't pop the picker.
  const handleEditorClick = e => {
    if (e.target.classList.contains("zaplane-variable-remove")) return;
    setActiveRange(saveSelection());
    setPopoverOpen(true);
  };

  // Sync on blur, but keep the picker open when focus is moving into it (the
  // user is clicking a variable) — otherwise the picker would close before the
  // click registers.
  const handleBlur = e => {
    syncValue(editorRef, field.key, setFieldValue);
    const toPicker =
      e.relatedTarget &&
      typeof e.relatedTarget.closest === "function" &&
      e.relatedTarget.closest(".zaplane-variables-popover");
    if (!toPicker) setPopoverOpen(false);
  };
  return <>
      <div className="zaplane-label" style={{display:'flex', flexDirection:'column', gap:'8px', ...containerStyle}}>
        <span>{__(label, "zaplane")}{isRequired && <span style={{ color: 'red', marginLeft: '2px' }}>*</span>}</span>

        <div ref={editorRef} onInput={handleInput} className={`zaplane-variable-editor ${multiline ? "zaplane-variable-editor-multiline" : "zaplane-variable-editor-singleline"} ${isEmpty ? "zaplane-empty" : ""}`} contentEditable suppressContentEditableWarning onKeyDown={handleKeyDown} onPaste={multiline ? undefined : handlePaste} onClick={handleEditorClick} onKeyUp={handleCursorSave} onBlur={handleBlur} data-placeholder={placeholder} />
      </div>

      <VariablePopover isOpen={isPopoverOpen} prefix="zaplane-variables-popover" onClose={() => setPopoverOpen(false)} data={variables} contextData={variableContext} onSelectVariable={variable => {
      if (!activeRange) return;
      insertVariableAtRange({
        range: activeRange,
        variableKey: variable.key || variable.replace("{{", "").replace("}}", ""),
        variables,
        variableContext,
        editorRef,
        setActiveRange,
        setPopoverOpen,
        syncValueFn: () => syncValue(editorRef, field.key, setFieldValue)
      });
      setTimeout(() => {
        if (!editorRef.current) return;
        setIsEmpty(editorRef.current.textContent.trim() === "");
      }, 0);
    }} />
    </>;
};
export default VariableEditor;
