import LabeledInput from "@ZAPComponents/LabeledInput";
import ZAPLabeledSelect from "@ZAPComponents/LabeledSelect";

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
        <LabeledInput
          label={field.label}
          placeholder={field.placeholder || ""}
          value={value || ""}
          type="text"
          onChange={(e) => setFieldValue(field.key, e.target.value)}
        />
      );

    case "number":
      return (
        <LabeledInput
          label={field.label}
          placeholder={field.placeholder || ""}
          value={value || ""}
          type="number"
          onChange={(e) => setFieldValue(field.key, e.target.value)}
        />
      );

    case "textarea":
      return (
        <LabeledInput
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
          <ZAPLabeledSelect
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
          <ZAPLabeledSelect
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

    default:
      return null;
  }
};

export default ActionFieldRenderer;
