
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
  placeholder,
  isClearable = false,
  containerStyle,
}) => {
  return (
    <Flex direction="column" gap={2} style={containerStyle}>
      {label && <Text className="zaplane-label">
        {__(label, "zaplane")}
      </Text>}

      <Select
        className="zaplane-select"
        classNamePrefix="zaplane-select"
        options={options}
        isLoading={isLoading}
        placeholder={placeholder}
        isClearable={isClearable}
        value={options.find(o => o.value === value) || null}
        onMenuOpen={onMenuOpen}
        onChange={(opt) => onChange?.(opt)}
        styles={{
          menuPortal: (base) => ({ ...base, zIndex: 9999 }),
          menuList: (base) => ({
            ...base,
            maxHeight: 500,
            overflowY: "auto",
          }),
        }}
      />
    </Flex>
  );
};

export default ZAPSelect;
