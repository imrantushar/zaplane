import React from "react";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import { __ } from "@wordpress/i18n";
const ZAPDatePicker = ({
  label,
  value,
  onChange,
  placeholder
}) => {
  return <div style={{display:'flex', flexDirection:'column', gap:'8px'}}>
      <span className="zaplane-label font-[600] text-[0.875rem]">
        {__(label, "zaplane")}
      </span>

      <DatePicker selected={value ? new Date(value) : null} onChange={date => onChange(date)} placeholderText={__("yyyy-MM-dd", "zaplane")} dateFormat="yyyy-MM-dd" className="zaplane-input" />
    </div>;
};
export default ZAPDatePicker;