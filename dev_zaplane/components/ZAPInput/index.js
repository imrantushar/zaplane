import React, { useRef, useEffect } from "react";
import { __ } from "@wordpress/i18n";
const ZAPInput = ({
  label,
  placeholder,
  value,
  onChange,
  type = "text",
  containerStyle,
  onKeyDown,
  inputStyle,
  inputRef
}) => {
  const isTextarea = type === "textarea";
  return <div style={{display:'flex', flexDirection:'column', gap:'8px', ...containerStyle}}>
      {label && <span>{__(label, "zaplane")}</span>}
      {isTextarea ? <textarea placeholder={__(placeholder, "zaplane")} value={value} onChange={onChange} onKeyDown={onKeyDown} style={{...inputStyle}} /> : <input ref={inputRef} type={type} placeholder={__(placeholder, "zaplane")} value={value} onKeyDown={onKeyDown} onChange={onChange} {...(type === "number" && {
      inputMode: "numeric",
      pattern: "[0-9]*"
    })} style={{...inputStyle}} />}
    </div>;
};
export default ZAPInput;