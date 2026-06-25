import { useRef, useState, useMemo } from "react";
import { useFormikContext } from "formik";
import { useSelector } from "react-redux";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ZAPDatePicker from "@ZAPComponents/ZAPDatePicker";
import ConditionGroupField from "../ConditionGroupField/ConditionGroupField";
import './styles.scss'
import { __ } from "@wordpress/i18n";
import VariableEditor from "@ZAPComponents/VariableEditor/index.js";
import RichTextField from "@ZAPComponents/RichTextField";
import { reactDebounce, rest_url } from "@ZAPUtils/helper";
import CopyInput from "./CopyInput";

const ActionFieldRenderer = ({
  field,
  value,
  setFieldValue,
  getKey,
  dynamicOptions,
  loadingFields,
  fetchDynamicOptions,
}) => {

  const [searchTerm, setSearchTerm] = useState("");
  const inputRef = useRef(null);
  const { workflowVariables } = useSelector((state) => state.workflows);
  const { errors, setFieldError } = useFormikContext();
  const fieldError = errors[field.key];

  const clearError = () => { if (fieldError) setFieldError(field.key, undefined); };

  const debouncedFetch = useMemo(
    () => reactDebounce((f, s) => fetchDynamicOptions(f, s), 500),
    [fetchDynamicOptions]
  );

  const handleInputChange = (val, { action }) => {
    if (action === "input-change") {
      setSearchTerm(val);
      if (field.dynamic) {
        debouncedFetch(field, val);
      }
    }
  };

  const ErrorMsg = () => {
    return fieldError ? (
      <p className="text-red-500 text-xs mt-1">{fieldError}</p>
    ) : null;
  }

  switch (field.type) {
    case "copy": {
      let displayValue = field.value || value || "";
      if (typeof displayValue === "string" && displayValue) {
        if (displayValue.startsWith("http")) {
          // Legacy: an absolute URL was stored — force it to the live origin.
          try {
            const urlObj = new URL(displayValue);
            displayValue = displayValue.replace(urlObj.origin, window.location.origin);
          } catch (e) {
            // Ignore invalid URLs
          }
        } else {
          // Relative REST path -> prefix the current site's REST domain.
          displayValue = `${rest_url}${displayValue.replace(/^\/+/, "")}`;
        }
      }

      return (
        <CopyInput
          label={field.label}
          value={displayValue}
          help={field.help}
        />
      );
    }

    case "number":
    case "email":
    case "url":

      return <div>
        <ZAPInput
          type={field.type}
          label={field.label}
          required={!!field.required}
          value={value || ""}
          inputRef={inputRef}
          isRequired={!!field.required}
          onChange={(e) => { setFieldValue(field.key, e.target.value); clearError(); }}
        />
        <ErrorMsg />
      </div>;

    case "text":
    case "expression":
    case "textarea":
      return (
        <div>
          <VariableEditor
            label={field.label}
            required={!!field.required}
            value={value || ""}
            setValue={(val) => { setFieldValue(field.key, val); clearError(); }}
            variables={workflowVariables?.data || []}
            variableContext={workflowVariables?.context || {}}
            field={field}
            setFieldValue={setFieldValue}
            placeholder={__('Type "@" here to add dynamic', "zaplane")}
            isRequired={!!field.required}
            multiline={field.type === "textarea"}
          />
          <ErrorMsg />
        </div>
      );

    // Simple rich-text email body (HTML in / HTML out). The full drag-and-drop
    // builder lives on the dedicated Email Templates page, not inline here.
    case "richtext":
      return (
        <div>
          <RichTextField
            field={field}
            value={value}
            setFieldValue={(key, val) => { setFieldValue(key, val); clearError(); }}
            workflowVariables={workflowVariables}
          />
          <ErrorMsg />
        </div>
      );

    case "date":
      return (
        <div>
          <ZAPDatePicker
            label={field.label}
            required={!!field.required}
            value={value}
            onChange={(date) => {
              setFieldValue(field.key, date?.toISOString().split("T")[0]);
              clearError();
            }}
            placeholder={field.placeholder}
          />
          <ErrorMsg />
        </div>
      );

    case "select": {
      const key = getKey?.(field, searchTerm);
      const options = field.options
        ? field.options.map((opt) => ({
          label: opt.label,
          value: opt.value
        }))
        : dynamicOptions[key] || [];

      return (
        <div>
          <ZAPSelect
            label={field.label}
            required={!!field.required}
            options={options}
            value={value}
            onChange={(opt) => { setFieldValue(field.key, opt?.value); clearError(); }}
            placeholder={field.placeholder || `Select ${field.label}`}
            isClearable
            isRequired={!!field.required}
            isLoading={field.dynamic ? loadingFields[key] : false}
            onMenuOpen={
              field.dynamic ? () => fetchDynamicOptions(field, searchTerm) : undefined
            }
            onInputChange={handleInputChange}
            inputValue={searchTerm}
          />
          <ErrorMsg />
        </div>
      );
    }

    case "multi-select": {
      const key = getKey?.(field, searchTerm);
      const options = field.options
        ? field.options.map((opt) => ({
          label: opt.label,
          value: opt.value
        }))
        : dynamicOptions[key] || [];

      return (
        <div>
          <ZAPSelect
            label={field.label}
            required={!!field.required}
            options={options}
            value={value || []}
            onChange={(vals) => { setFieldValue(field.key, vals); clearError(); }}
            placeholder={field.placeholder || `Select ${field.label}`}
            isClearable
            isMulti
            isRequired={!!field.required}
            isLoading={field.dynamic ? loadingFields[key] : false}
            onMenuOpen={
              field.dynamic ? () => fetchDynamicOptions(field, searchTerm) : undefined
            }
            onInputChange={handleInputChange}
            inputValue={searchTerm}
          />
          <ErrorMsg />
        </div>
      );
    }

    case "json":
      return (
        <div>
          <label className="zaplane-label">{__(field.label, "zaplane")}{field.required && <span style={{ color: 'red', marginLeft: '2px' }}>*</span>}</label>
          <textarea
            value={value || ""}
            onChange={(e) => { setFieldValue(field.key, e.target.value); clearError(); }}
            placeholder={'{ "Header-Name": "value" }'}
            rows={5}
            style={{ width: "100%", fontFamily: "monospace", fontSize: "13px", padding: "8px", borderRadius: "6px", border: "1px solid var(--zaplane-border-color)", resize: "vertical", boxSizing: "border-box", color: "var(--zaplane-font-secondary-color)" }}
          />
          <ErrorMsg />
        </div>
      );

    case "condition_group":
      return (
        <ConditionGroupField
          value={value}
          field={field}
          variables={workflowVariables?.data}
          variableContext={workflowVariables?.context || {}}
        />
      );

    default:
      return null;
  }
};

export default ActionFieldRenderer;
