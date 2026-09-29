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
  inputRef,
  isRequired = false
}) => {
  const isTextarea = type === "textarea";
  return <div style={{display:'flex', flexDirection:'column', gap:'8px', ...containerStyle}}>
      {label && <span className='zaplane-label'>{__(label, "zaplane")}{isRequired && <span style={{ color: 'red', marginLeft: '2px' }}>*</span>}</span>}
      {isTextarea ? <textarea className="zaplane-textarea text-[var(--zaplane-text-muted)] bg-[var(--zaplane-background)]" placeholder={__(placeholder, "zaplane")} value={value} onChange={onChange} onKeyDown={onKeyDown} style={{ padding: '8px 12px', ...inputStyle }} /> :
      <input className="zaplane-input text-[var(--zaplane-text-muted)] bg-[var(--zaplane-background)]" ref={inputRef} type={type} placeholder={__(placeholder, "zaplane")} value={value} onKeyDown={onKeyDown} onChange={onChange} {...(type === "number" && {
      inputMode: "numeric",
      pattern: "[0-9]*"
    })} style={{...inputStyle}} />}
    </div>;
};
export default ZAPInput;