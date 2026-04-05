import React from "react";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import { Flex, Text } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";

const ZAPDatePicker = ({ label, value, onChange, placeholder }) => {
  return (
    <Flex direction="column" gap={2}>
      <Text className="zaplane-label" fontWeight="600" fontSize="0.875rem">
        {__(label, "zaplane")}
      </Text>

      <DatePicker
        selected={value ? new Date(value) : null}
        onChange={(date) => onChange(date)}
        placeholderText={__("yyyy-MM-dd", "zaplane")}
        dateFormat="yyyy-MM-dd"
        className="zaplane-input"
      />
    </Flex>
  );
};

export default ZAPDatePicker;