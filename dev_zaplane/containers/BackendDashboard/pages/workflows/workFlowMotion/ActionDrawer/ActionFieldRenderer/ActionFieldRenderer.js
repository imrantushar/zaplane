import { useState, useRef, useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ZAPDatePicker from "@ZAPComponents/ZAPDatePicker";
import ConditionGroupField from "../ConditionGroupField/ConditionGroupField";
import { mapEdgesForBackend, mapNodesForBackend } from "../../helper";
import { conditionVariables } from "@ZAPRedux/Slices/workFlowSlice/actions/conditonVariales";
import {  insertVariableAtCursor } from "./helper";
import VariablePopover from "../VariablePopaver/VariablePopover";
import './styles.scss'
import { __ } from "@wordpress/i18n";

const ActionFieldRenderer = ({
  field,
  value,
  setFieldValue,
  getKey,
  dynamicOptions,
  loadingFields,
  fetchDynamicOptions,
  nodeId,
  workFlow,
  nodes,
  edges
}) => {
  const [isPopoverOpen, setPopoverOpen] = useState(false);
  const inputRef = useRef(null);
  const { workflowVariables } = useSelector(
    (state) => state.workflows
  );
  switch (field.type) {

    case "text":
    case "expression":
    case "number":
    case "email":
    case "url":
    case "textarea":
      return (
        <>
          <ZAPInput
            type={field.type}
            label={field.label}
            placeholder={__('Type "@" here to add dynamic', 'zaplane')}
            value={value || ""}
            inputRef={inputRef}
            onChange={(e) => setFieldValue(field.key, e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "@") {
                setPopoverOpen(true);
              }
            }}
          />

          <VariablePopover
            isOpen={isPopoverOpen}
            prefix="variables-popaver"
            onClose={() => setPopoverOpen(false)}
            data={workflowVariables?.data}
            onSelectVariable={(variable) => {
              insertVariableAtCursor({
                variable,
                inputRef,
                fieldKey: field.key,
                setFieldValue,
                setPopoverOpen,
              });
            }}
          />
        </>
      );

    case "date":
      return (
        <ZAPDatePicker
          label={field.label}
          value={value}
          onChange={(date) =>
            setFieldValue(field.key, date?.toISOString().split("T")[0])
          }
          placeholder={field.placeholder}
        />
      );

    case "select": {
      const key = getKey?.(field);
      const options = field.options
        ? field.options.map((opt) => ({
          label: opt.label,
          value: opt.value
        }))
        : dynamicOptions[key] || [];

      return (
        <ZAPSelect
          label={field.label}
          options={options}
          value={value}
          onChange={(opt) =>
            setFieldValue(field.key, opt?.value)
          }
          placeholder={
            field.placeholder || `Select ${field.label}`
          }
          isClearable
          isLoading={
            field.dynamic ? loadingFields[key] : false
          }
          onMenuOpen={
            field.dynamic
              ? () => fetchDynamicOptions(field)
              : undefined
          }
        />
      );
    }

    case "condition_group":
      return (
        <ConditionGroupField
          value={value}
          field={field}
          variables={workflowVariables?.data}
        />
      );

    default:
      return null;
  }
};

export default ActionFieldRenderer;