
import { Box ,Text} from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import Select from "react-select";

const ZAPSelect = ({
  label,
  options = [],
  value,
  onChange,
  isLoading = false,
  onMenuOpen,
  mb = 0,
  placeholder,
  isClearable = false,
  containerStyle,
}) => {
  return (
    <Box mb={mb} style={containerStyle}>
      {label && <Text className="zaplane-label" fontWeight="600" fontSize="0.875rem" mb="4px">
         {sprintf(__('%s', 'zaplane'), label)}
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
      />
    </Box>
  );
};

export default ZAPSelect;
