
import { Box, Flex, Text } from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
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
    <Flex  direction="column" gap={2} style={containerStyle}>
      {label &&  <ZAPLabel label={label} type={"inputLabel"} />}
     
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
      />
    </Flex>
  );
};

export default ZAPSelect;
