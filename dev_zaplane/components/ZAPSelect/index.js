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
          // react-select injects white backgrounds/near-black text via inline
          // styles that beat our SCSS classes — so theme the relevant slots here
          // with CSS variables (they resolve to the active light/dark palette).
          menuPortal: (base) => ({
            ...base,
            zIndex: 100001
          }),
          control: (base, state) => ({
            ...base,
            backgroundColor: "var(--zaplane-background)",
            borderColor: state.isFocused ? "var(--zaplane-primary)" : "var(--zaplane-border-color)",
            boxShadow: "none",
            "&:hover": { borderColor: "var(--zaplane-border-color)" }
          }),
          menu: (base) => ({
            ...base,
            backgroundColor: "var(--zaplane-background)",
            border: "1px solid var(--zaplane-border-color)",
            overflow: "hidden"
          }),
          menuList: (base) => ({
            ...base,
            maxHeight: 250,
            overflowY: "auto"
          }),
          option: (base, state) => ({
            ...base,
            cursor: "pointer",
            color: state.isSelected ? "var(--zaplane-primary)" : "var(--zaplane-font-color)",
            backgroundColor: state.isSelected
              ? "var(--zaplane-second-primary)"
              : state.isFocused
              ? "var(--zaplane-secondary-color)"
              : "transparent",
            "&:active": { backgroundColor: "var(--zaplane-secondary-color)" }
          }),
          singleValue: (base) => ({ ...base, color: "var(--zaplane-font-color)" }),
          input: (base) => ({ ...base, color: "var(--zaplane-font-color)" }),
          placeholder: (base) => ({ ...base, color: "var(--zaplane-placeholder)" }),
          dropdownIndicator: (base) => ({ ...base, color: "var(--zaplane-text-muted)" }),
          indicatorSeparator: (base) => ({ ...base, backgroundColor: "var(--zaplane-border-color)" }),
          multiValue: (base) => ({ ...base, backgroundColor: "var(--zaplane-primary)" }),
          multiValueLabel: (base) => ({ ...base, color: "#fff" })
        }}
      />
    </div>
  );
};

export default ZAPSelect;