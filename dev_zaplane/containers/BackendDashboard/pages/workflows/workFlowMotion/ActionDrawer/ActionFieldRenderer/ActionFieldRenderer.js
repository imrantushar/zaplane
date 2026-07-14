import { useRef, useState, useMemo } from "react";
import { useFormikContext } from "formik";
import { useSelector } from "react-redux";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ZAPDatePicker from "@ZAPComponents/ZAPDatePicker";
import ZAPCheckbox from "@ZAPComponents/ZAPCheckbox";
import ConditionGroupField from "../ConditionGroupField/ConditionGroupField";
import RepeaterField from "../RepeaterField/RepeaterField";
import './styles.scss'
import { __ } from "@wordpress/i18n";
import VariableEditor from "@ZAPComponents/VariableEditor/index.js";
import RichTextField from "@ZAPComponents/RichTextField";
import { reactDebounce, rest_url } from "@ZAPUtils/helper";
import CopyInput from "./CopyInput";

const pad = (n) => String(n).padStart(2, "0");
const formatTime = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;
const formatDateTime = (date) =>
  `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${formatTime(date)}`;

const ActionFieldRenderer = ({
  field,
  value,
  setFieldValue,
  getKey,
  dynamicOptions,
  loadingFields,
  fetchDynamicOptions,
  workFlow,
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

      // Templates like `zaplane/v1/hook/{workflow_id}` are per-workflow, so the
      // real id is only known here (and only after the workflow is saved).
      if (typeof displayValue === "string" && displayValue.includes("{workflow_id}")) {
        const workflowId = workFlow?.workflow?.id;
        if (!workflowId) {
          return (
            <div className="flex flex-col gap-2">
              {field.label && <span className="zaplane-label">{__(field.label, "zaplane")}</span>}
              <p className="text-gray-500 text-xs">
                {__("Save the workflow to generate its webhook URL.", "zaplane")}
              </p>
            </div>
          );
        }
        displayValue = displayValue.replace("{workflow_id}", workflowId);
      }

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

    // Email supports dynamic data (e.g. {{trigger.email}}) like text fields; a
    // literal value is validated as an email address on continue (see
    // ActionDrawer's validateRequiredFields).
    case "email":
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
            placeholder={field.placeholder || __('name@example.com — or type "@" for dynamic data', "zaplane")}
            isRequired={!!field.required}
          />
          <ErrorMsg />
        </div>
      );

    case "number":
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

    // File picker: opens the WP Media Library to upload/select a file, stores its
    // URL, and shows the chosen file's name with Change / Remove controls.
    case "file": {
      const openMediaLibrary = () => {
        const media = window.wp && window.wp.media;
        if (!media) {
          console.warn("WP media library is not available.");
          return;
        }
        const frame = media({
          title: __("Select a file", "zaplane"),
          button: { text: __("Use this file", "zaplane") },
          multiple: false,
        });
        frame.on("select", () => {
          const attachment = frame.state().get("selection").first().toJSON();
          setFieldValue(field.key, attachment.url || "");
          clearError();
        });
        frame.open();
      };

      let fileName = "";
      if (value) {
        try {
          fileName = decodeURIComponent(String(value).split("/").pop().split("?")[0]);
        } catch (e) {
          fileName = String(value).split("/").pop();
        }
      }

      return (
        <div className="flex flex-col gap-2">
          <span className="zaplane-label">
            {__(field.label, "zaplane")}
            {field.required && <span className="text-red-500 ml-[2px]">*</span>}
          </span>

          {value ? (
            <div className="flex items-center justify-between gap-3 rounded-md border border-[var(--zaplane-border-color)] px-3 py-2">
              <span className="truncate text-sm" title={fileName}>{fileName}</span>
              <div className="flex items-center gap-3 shrink-0">
                <button
                  type="button"
                  onClick={openMediaLibrary}
                  className="text-[13px] text-[var(--zaplane-primary)] bg-transparent border-0 cursor-pointer p-0"
                >
                  {__("Change", "zaplane")}
                </button>
                <button
                  type="button"
                  onClick={() => { setFieldValue(field.key, ""); }}
                  className="text-[13px] text-red-500 bg-transparent border-0 cursor-pointer p-0"
                >
                  {__("Remove", "zaplane")}
                </button>
              </div>
            </div>
          ) : (
            <button
              type="button"
              onClick={openMediaLibrary}
              className="flex items-center justify-center gap-1.5 rounded-md border border-dashed border-[var(--zaplane-border-color)] bg-transparent px-3 py-3 text-[13px] text-[var(--zaplane-text-muted)] cursor-pointer hover:border-[var(--zaplane-primary)]"
            >
              {__("Upload or select a file", "zaplane")}
            </button>
          )}

          {field.help && (
            <span className="text-[13px] text-[var(--zaplane-text-muted)] leading-relaxed mt-0.5">
              {__(field.help, "zaplane")}
            </span>
          )}
          <ErrorMsg />
        </div>
      );
    }

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

    case "boolean":
    case "checkbox":
      return (
        <div>
          <ZAPCheckbox
            label={field.label}
            isRequired={!!field.required}
            checked={value ?? field.default ?? false}
            onChange={(checked) => { setFieldValue(field.key, checked); clearError(); }}
          />
          <ErrorMsg />
        </div>
      );

    case "time":
      return (
        <div>
          <ZAPDatePicker
            label={field.label}
            required={!!field.required}
            mode="time"
            value={value}
            onChange={(date) => { setFieldValue(field.key, date ? formatTime(date) : ""); clearError(); }}
            placeholder={field.placeholder}
          />
          <ErrorMsg />
        </div>
      );

    case "datetime":
      return (
        <div>
          <ZAPDatePicker
            label={field.label}
            required={!!field.required}
            mode="datetime"
            value={value}
            onChange={(date) => { setFieldValue(field.key, date ? formatDateTime(date) : ""); clearError(); }}
            placeholder={field.placeholder}
          />
          <ErrorMsg />
        </div>
      );

    case "map":
    case "repeater":
      return (
        <RepeaterField
          field={field}
          value={value || []}
          getKey={getKey}
          dynamicOptions={dynamicOptions}
          loadingFields={loadingFields}
          fetchDynamicOptions={fetchDynamicOptions}
        />
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
