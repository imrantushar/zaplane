import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ConditionGroupField from "../ConditionGroupField/ConditionGroupField";
import ZAPDatePicker from "@ZAPComponents/ZAPDatePicker";

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
  const handleChange = (val) => setFieldValue(field.key, val);
  const commonProps = {
    label: field.label,
    placeholder: field.placeholder || "",
    value: value || "",
    onChange: (e) => handleChange(e.target.value),
  };

  switch (field.type) {
    case "text":
    case "expression":
    case "number":
    case "email":
    case "url":
    case "textarea":
      return (
        <ZAPInput
          {...commonProps}
          type={field.type === "expression" ? "text" : field.type}
        />
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
        />);
    case "select": {
      const key = getKey?.(field);
      const options = field.options
        ? field.options.map((opt) => ({ label: opt.label, value: opt.value }))
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
          onMenuOpen={field.dynamic ? () => fetchDynamicOptions(field) : undefined}
        />
      );
    }

    case "condition_group":
      return <ConditionGroupField value={value} field={field} nodeId={nodeId} workFlow={workFlow} nodes={nodes} edges={edges}/>;

    default:
      return null;
  }
};

export default ActionFieldRenderer;
