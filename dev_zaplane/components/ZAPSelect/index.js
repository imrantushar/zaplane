
import { Box, Flex, Text } from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
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
  containerStyle,
}) => {
  const selectedValue = isMulti
    ? options.filter((o) => Array.isArray(value) && value.includes(o.value))
    : options.find((o) => o.value === value) || null;

  const handleChange = (selected) => {
    if (isMulti) {
      onChange?.(selected ? selected.map((o) => o.value) : []);
    } else {
      onChange?.(selected);
    }
  };

  return (
    <Flex direction="column" gap={2} style={containerStyle}>
      {label && (
        <Text className="zaplane-label">{__(label, "zaplane")}</Text>
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
          menuPortal: (base) => ({ ...base, zIndex: 9999 }),
          menuList: (base) => ({
            ...base,
            maxHeight: 250,
            overflowY: "auto",
          }),
        }}
      />
    </Flex>
  );
};

export default ZAPSelect;
