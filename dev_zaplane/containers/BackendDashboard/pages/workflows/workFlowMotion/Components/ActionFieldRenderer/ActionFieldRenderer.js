import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ConditionGroupField from "../ConditionGroupField/ConditionGroupField";

const ActionFieldRenderer = ({
  field,
  value,
  setFieldValue,
  getKey,
  dynamicOptions,
  loadingFields,
  fetchDynamicOptions,
}) => {
  switch (field.type) {
    case "text":
    case "expression":
      return (
        <ZAPInput
          label={field.label}
          placeholder={field.placeholder || ""}
          value={value || ""}
          type="text"
          onChange={(e) => setFieldValue(field.key, e.target.value)}
        />
      );

    case "number":
      return (
        <ZAPInput
          label={field.label}
          placeholder={field.placeholder || ""}
          value={value || ""}
          type="number"
          onChange={(e) => setFieldValue(field.key, e.target.value)}
        />
      );

    case "textarea":
      return (
        <ZAPInput
          label={field.label}
          placeholder={field.placeholder || ""}
          value={value || ""}
          type="textarea"
          onChange={(e) => setFieldValue(field.key, e.target.value)}
        />
      );

    case "select":
      // static options
      if (field.options) {
        const options = field.options.map(opt => ({
          label: opt.label,
          value: opt.value,
        }));

        return (
          <ZAPSelect
            label={field.label}
            options={options}
            value={value}
            onChange={(val) => setFieldValue(field.key, val)}
            placeholder={field.placeholder || `Select ${field.label}`}
            isClearable
          />
        );
      }

      // dynamic options
      if (field.dynamic) {
        const key = getKey(field);

        return (
          <ZAPSelect
            label={field.label}
            options={dynamicOptions[key] || []}
            value={value}
            isLoading={loadingFields[key]}
            onMenuOpen={() => fetchDynamicOptions(field)}
            onChange={(val) => setFieldValue(field.key, val)}
            placeholder={`Select ${field.label}`}
            isClearable
          />
        );
      }

      return null;
    case "condition_group":
      return (
        <ConditionGroupField
          value={
            value
          }
          onChange={(val) =>
            setFieldValue(field.key, val)
          }
          field={field}
        />
      );

    default:
      return null;
  }
};

export default ActionFieldRenderer;
