import React from "react";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import { __ } from "@wordpress/i18n";

const pad = (n) => String(n).padStart(2, "0");

// Parse a stored string value into a Date for the picker's `selected` prop.
// `date` mode keeps the original behavior (new Date("yyyy-MM-dd")).
const parseValue = (value, mode) => {
  if (!value) return null;
  if (mode === "time") return new Date(`1970-01-01T${value}`);
  if (mode === "datetime") return new Date(value.replace(" ", "T"));
  return new Date(value);
};

const ZAPDatePicker = ({
  label,
  value,
  onChange,
  placeholder,
  mode = "date"
}) => {
  const datePickerProps = {
    time: { showTimeSelect: true, showTimeSelectOnly: true, dateFormat: "HH:mm", timeFormat: "HH:mm" },
    datetime: { showTimeSelect: true, dateFormat: "yyyy-MM-dd HH:mm", timeFormat: "HH:mm" },
    date: { dateFormat: "yyyy-MM-dd" }
  }[mode];

  return <div style={{display:'flex', flexDirection:'column', gap:'8px'}}>
      <span className="zaplane-label font-[600] text-[0.875rem]">
        {__(label, "zaplane")}
      </span>

      <DatePicker
        selected={parseValue(value, mode)}
        onChange={date => onChange(date)}
        placeholderText={__(placeholder || datePickerProps.dateFormat, "zaplane")}
        className="zaplane-input"
        {...datePickerProps}
      />
    </div>;
};
export default ZAPDatePicker;
