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
import { reactDebounce } from "@ZAPUtils/helper";

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

    case "number":
    case "email":
    case "url":

      return <div>
        <ZAPInput
          type={field.type}
          label={field.label}
          value={value || ""}
          inputRef={inputRef}
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
            value={value || ""}
            setValue={(val) => { setFieldValue(field.key, val); clearError(); }}
            variables={workflowVariables?.data || []}
            field={field}
            setFieldValue={setFieldValue}
            placeholder={__('Type "@" here to add dynamic', "zaplane")}
          />
          <ErrorMsg />
        </div>
      );

    case "date":
      return (
        <div>
          <ZAPDatePicker
            label={field.label}
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
            options={options}
            value={value}
            onChange={(opt) => { setFieldValue(field.key, opt?.value); clearError(); }}
            placeholder={field.placeholder || `Select ${field.label}`}
            isClearable
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
            options={options}
            value={value || []}
            onChange={(vals) => { setFieldValue(field.key, vals); clearError(); }}
            placeholder={field.placeholder || `Select ${field.label}`}
            isClearable
            isMulti
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