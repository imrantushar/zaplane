import React, { useRef, useState, useEffect } from "react";
import { Flex, Text } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import VariablePopover from "./VariablePopover";
import {
  saveSelection,
  restoreSelection,
  syncValue,
  renderVariableHTML,
  insertVariableAtRange,
} from "./helper";
import "./styles.scss";

const VariableEditor = ({ value, setFieldValue, field, variables, label, placeholder, containerStyle }) => {
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
        editorRef.current.innerHTML = renderVariableHTML(value, variables);
      }
      initialized.current = true;
    }

    setIsEmpty(!value || value.trim() === "");
  }, [value, variables]);

  // remove variable
  useEffect(() => {
    const editor = editorRef.current;
    if (!editor) return;

    const handleRemoveClick = (e) => {
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
    const text = editorRef.current.textContent.trim();
    setIsEmpty(text === "");
  };

  // open popover when @ typed
  const handleKeyDown = (e) => {
    if (e.key === "@") {
      const range = saveSelection();
      setActiveRange(range);
      setPopoverOpen(true);
      e.preventDefault();
    }
  };

  // save cursor inside editor
  const handleCursorSave = () => {
    const range = saveSelection();
    setActiveRange(range);
  };

  return (
    <>
      <Flex as="label" direction="column" gap={2} style={{ ...containerStyle }}>
        <Text className="zaplane-label">{__(label, "zaplane")}</Text>

        <div
          ref={editorRef}
          onInput={handleInput}
          className={`zaplane-variable-editor ${isEmpty ? "zaplane-empty" : ""}`}
          contentEditable
          suppressContentEditableWarning
          onKeyDown={handleKeyDown}
          onClick={handleCursorSave}
          onKeyUp={handleCursorSave}
          onBlur={() => syncValue(editorRef, field.key, setFieldValue)}
          data-placeholder={placeholder}
        />
      </Flex>

      <VariablePopover
        isOpen={isPopoverOpen}
        prefix="zaplane-variables-popover"
        onClose={() => setPopoverOpen(false)}
        data={variables}
        onSelectVariable={(variable) => {
          if (!activeRange) return;

          insertVariableAtRange({
            range: activeRange,
            variableKey:
              variable.key ||
              variable.replace("{{", "").replace("}}", ""),
            variables,
            editorRef,
            setActiveRange,
            setPopoverOpen,
            syncValueFn: () =>
              syncValue(editorRef, field.key, setFieldValue),
          });

          setTimeout(() => {
            if (!editorRef.current) return;
            setIsEmpty(editorRef.current.textContent.trim() === "");
          }, 0);
        }}
      />
    </>
  );
};

export default VariableEditor;