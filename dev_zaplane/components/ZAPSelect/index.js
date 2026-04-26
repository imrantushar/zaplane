import { __ } from "@wordpress/i18n";
import { useState, useRef } from "react";
import CreatableSelect from "react-select/creatable";
import VariablePopover from "@ZAPComponents/VariableEditor/VariablePopover";

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
  containerStyle = {},
  variables = []
}) => {
  const [isPopoverOpen, setPopoverOpen] = useState(false);
  const containerRef = useRef(null);

  // Handle selected value properly
  const selectedValue = isMulti
    ? (Array.isArray(value) ? value.map(val => options.find(o => o.value === val) || { label: val, value: val }) : [])
    : (options.find((o) => o.value === value) || (value ? { label: value, value: value } : null));

  // Handle change properly
  const handleChange = (selected) => {
    if (isMulti) {
      onChange?.(selected ? selected.map((o) => o.value) : []);
    } else {
      onChange?.(selected);
    }
  };

  const handleInputChange = (val, actionMeta) => {
    if (actionMeta.action === "input-change") {
      if (val.endsWith("@")) {
        setPopoverOpen(true);
      } else {
        setPopoverOpen(false);
      }
    }
    onInputChange?.(val, actionMeta);
  };

  const handleSelectVariable = (variable) => {
    const varString = typeof variable === 'string' 
      ? variable 
      : `{{${variable.key || variable.replace("{{", "").replace("}}", "")}}}`;
    
    // Replace the last '@' in the input value with the selected variable
    const currentInput = inputValue || "";
    const lastAtIndex = currentInput.lastIndexOf("@");
    let newInputValue = varString;
    if (lastAtIndex !== -1) {
       newInputValue = currentInput.substring(0, lastAtIndex) + varString;
    }
    
    setPopoverOpen(false);
    
    // Send the new string into the search input so the user can continue typing or press Enter
    onInputChange?.(newInputValue, { action: "input-change" });
  };

  return (
    <div
      ref={containerRef}
      style={{
        display: "flex",
        flexDirection: "column",
        gap: "8px",
        position: "relative",
        ...containerStyle
      }}
    >
      {label && (
        <span className="zaplane-label">
          {__(label, "zaplane")}
        </span>
      )}

      <CreatableSelect
        className="zaplane-select"
        classNamePrefix="zaplane-select"
        options={options}
        isLoading={isLoading}
        placeholder={placeholder}
        isClearable={isClearable}
        isMulti={isMulti}
        value={selectedValue}
        onMenuOpen={onMenuOpen}
        onInputChange={handleInputChange}
        formatCreateLabel={(inputValue) => `Use "${inputValue}"`}
        inputValue={inputValue}
        onChange={handleChange}
        menuPortalTarget={document.body}
        menuPosition="fixed"
        styles={{
          menuPortal: (base) => ({
            ...base,
            zIndex: 9999
          }),
          menuList: (base) => ({
            ...base,
            maxHeight: 250,
            overflowY: "auto"
          })
        }}
      />

      <VariablePopover
        isOpen={isPopoverOpen}
        prefix="zaplane-variables-popover"
        onClose={() => setPopoverOpen(false)}
        data={variables}
        onSelectVariable={handleSelectVariable}
      />
    </div>
  );
};

export default ZAPSelect;