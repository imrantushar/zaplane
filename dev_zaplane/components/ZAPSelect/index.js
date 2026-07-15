import { __ } from "@wordpress/i18n";
import Select from "react-select";

const ZAPSelect = ({
  label,
  options = [],
  value,
  onChange,
  isLoading = false,
  onMenuOpen,
  onInputChange,
  inputValue,
  placeholder,
  isClearable = false,
  isMulti = false,
  isRequired = false,
  containerStyle = {}
}) => {

  // Handle selected value properly. For a single select, dynamic options load
  // lazily (e.g. the AI model list is fetched per connection), so the saved value
  // may not be in `options` yet — fall back to showing the raw value instead of
  // the placeholder, otherwise a already-chosen value reads as "nothing selected".
  const selectedValue = isMulti
    ? options.filter((o) => Array.isArray(value) && value.includes(o.value))
    : options.find((o) => o.value === value) ||
      (value !== undefined && value !== null && value !== ""
        ? { value, label: String(value) }
        : null);

  // Handle change properly
  const handleChange = (selected) => {
    if (isMulti) {
      onChange?.(selected ? selected.map((o) => o.value) : []);
    } else {
      onChange?.(selected);
    }
  };

  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        gap: "8px",
        ...containerStyle
      }}
    >
      {label && (
        <span className="zaplane-label">
          {__(label, "zaplane")}
          {isRequired && <span className="text-red-500 ml-[2px]">*</span>}
        </span>
      )}

      <Select
        className="zaplane-select"
        classNamePrefix="zaplane-select"
        options={options}
        isLoading={isLoading}
        placeholder={placeholder}
        isClearable={isClearable}
        isMulti={isMulti}
        value={selectedValue}
        onMenuOpen={onMenuOpen}
        onInputChange={onInputChange}
        inputValue={inputValue}
        onChange={handleChange}
        menuPortalTarget={document.body}
        menuPosition="fixed"
        styles={{
          menuPortal: (base) => ({
            ...base,
            zIndex: 100001
          }),
          menuList: (base) => ({
            ...base,
            maxHeight: 250,
            overflowY: "auto"
          })
        }}
      />
    </div>
  );
};

export default ZAPSelect;