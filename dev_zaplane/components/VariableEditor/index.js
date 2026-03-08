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
import './styles.scss';


const VariableEditor = ({ value, setFieldValue, field, variables, label }) => {
  const editorRef = useRef(null);
  const [isPopoverOpen, setPopoverOpen] = useState(false);
  const [activeRange, setActiveRange] = useState(null);
  const [isEmpty, setIsEmpty] = useState(!value);

  useEffect(() => {
    if (editorRef.current && value) {
      editorRef.current.innerHTML = renderVariableHTML(value, variables);
    }
  }, [value, variables]);

  useEffect(() => {
    const editor = editorRef.current;
    if (!editor) return;

    const handleRemoveClick = (e) => {
      if (e.target.classList.contains("zaplane-variable-remove")) {
        const span = e.target.parentNode;
        span.remove();
        syncValue(editorRef, field.key, setFieldValue);
      }
    };

    editor.addEventListener("click", handleRemoveClick);
    return () => editor.removeEventListener("click", handleRemoveClick);
  }, [field.key, setFieldValue]);


  const handleInput = () => {
    if (!editorRef.current) return;
    setIsEmpty(editorRef.current.textContent.trim() === "");
  };

  const handleKeyDown = (e) => {
    if (e.key === "@") {
      setActiveRange(saveSelection());
      setPopoverOpen(true);
      e.preventDefault();
    }
  };

  return (
    <>
      <Flex as="label" direction="column" gap={2}>
        <Text className="zaplane-label">{__(label, "zaplane")}</Text>
        <div
          onInput={handleInput}
          ref={editorRef}
          className={`zaplane-variable-editor ${isEmpty ? "zaplane-empty" : ""}`}
          contentEditable
          suppressContentEditableWarning
          onKeyDown={handleKeyDown}
          onClick={() => setActiveRange(saveSelection())}
          onKeyUp={() => setActiveRange(saveSelection())}
          onBlur={() => syncValue(editorRef, field.key, setFieldValue)}
          data-placeholder={__('Type "@" here to add dynamic', "zaplane")}
        />
      </Flex>

      <VariablePopover
        isOpen={isPopoverOpen}
        prefix="zaplane-variables-popover"
        onClose={() => setPopoverOpen(false)}
        data={variables}
        onSelectVariable={(variable) => {
          const range = activeRange;
          if (!range) return;
          insertVariableAtRange({
            range,
            variableKey: variable.key || variable.replace("{{", "").replace("}}", ""),
            variables,
            editorRef,
            setActiveRange,
            setPopoverOpen,
            syncValueFn: () => syncValue(editorRef, field.key, setFieldValue),
          });
        }}
      />
    </>
  );
};

export default VariableEditor;