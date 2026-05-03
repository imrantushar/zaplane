import React from "react";
import { __ } from '@wordpress/i18n';
const ZAPLoading = ({
  height = "300px",
  size = "xl",
  label,
  ...props
}) => {
  return <div style={{minHeight: height}} className="flex flex-col items-center justify-center gap-3" {...props}>
      <svg className="animate-spin" style={{width:'2rem',height:'2rem',color:'var(--zaplane-primary)'}} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      {label && <span style={{fontSize:'14px',color:'var(--zaplane-font-secondary-color)'}}>
         {__(label, 'zaplane')}
        </span>}
    </div>;
};
export default ZAPLoading;