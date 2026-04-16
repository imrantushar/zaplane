import {  useRef,  } from "react";
import {  useSelector } from "react-redux";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ZAPDatePicker from "@ZAPComponents/ZAPDatePicker";
import ConditionGroupField from "../ConditionGroupField/ConditionGroupField";
import './styles.scss'
import { __ } from "@wordpress/i18n";
import VariableEditor from "@ZAPComponents/VariableEditor/index.js";

const ActionFieldRenderer = ({
  field,
  value,
  setFieldValue,
  getKey,
  dynamicOptions,
  loadingFields,
  fetchDynamicOptions,
}) => {

  const inputRef = useRef(null);
  const { workflowVariables } = useSelector(
    (state) => state.workflows
  );
  switch (field.type) {

    case "number":
    case "email":
    case "url":

      return <>
        <ZAPInput
          type={field.type}
          label={field.label}
          value={value || ""}
          inputRef={inputRef}
          onChange={(e) => setFieldValue(field.key, e.target.value)}
        /></>
    case "text":
    case "expression":

    case "textarea":
      return (
        <>
          <VariableEditor
            label={field.label}
            value={value || ""}
            setValue={(val) => setFieldValue(field.key, val)}
            variables={workflowVariables?.data || []}
            field={field}
            setFieldValue={setFieldValue}
            placeholder={__('Type "@" here to add dynamic', "zaplane")}
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
          onChange={(opt) => setFieldValue(field.key, opt?.value)}
          placeholder={field.placeholder || `Select ${field.label}`}
          isClearable
          isLoading={field.dynamic ? loadingFields[key] : false}
          onMenuOpen={
            field.dynamic ? () => fetchDynamicOptions(field) : undefined
          }
        />
      );
    }

    case "multi-select": {
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
          value={value || []}
          onChange={(vals) => setFieldValue(field.key, vals)}
          placeholder={field.placeholder || `Select ${field.label}`}
          isClearable
          isMulti
          isLoading={field.dynamic ? loadingFields[key] : false}
          onMenuOpen={
            field.dynamic ? () => fetchDynamicOptions(field) : undefined
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