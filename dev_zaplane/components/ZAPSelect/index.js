
import { Box } from "@chakra-ui/react";
import Select from "react-select";
import ZAPText from "@ZAPComponents/Text";

const ZAPSelect = ({
  label,
  options = [],
  value,
  onChange,
  isLoading = false,
  onMenuOpen,
  mb = 0,
  placeholder,
  isClearable = false
}) => {
  return (
    <Box mb={mb}>
      {label && <ZAPText mb="4px">{label}</ZAPText>}

      <Select
        options={options}
        isLoading={isLoading}
        placeholder={placeholder}
        isClearable={isClearable}
        value={options.find(o => o.value === value) || null}
        onMenuOpen={onMenuOpen}
        onChange={(opt) => onChange?.(opt?.value)}
      />
    </Box>
  );
};

export default ZAPSelect;
