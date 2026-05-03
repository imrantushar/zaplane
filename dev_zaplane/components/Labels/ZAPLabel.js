import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { sliceString } from '@ZAPUtils/helper';
const ZAPLabel = ({
  type = "title",
  label = "",
  fontSize = "",
  fontWeight = "",
  textTransform = "",
  margin = "0",
  padding = "",
  color = "",
  bg = "",
  borderRadius = "",
  href = null,
  textAlign = "",
  borderBottomWidth = "",
  borderColor = "",
  icon,
  whiteSpace = "",
  lineClamp = "",
  truncate,
  lineHeight,
  enableSlice = false,
  sliceLength = 100,
  sliceMore = '...',
  textOverflow = '',
  onClick
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const variantStyles = {
    heading: { fontSize: "20px", fontWeight: "200", color: "var(--zaplane-font-color)" },
    title: { fontSize: "14px", fontWeight: "500", color: "var(--zaplane-font-color)" },
    subtitle: { fontSize: "14px", fontWeight: "400", color: "#4B5563" },
    miniTitle: { fontSize: "12px", fontWeight: "400", color: "#4B5563" },
    basic: { fontSize: "14px", fontWeight: "500", color: "var(--zaplane-font-color)" },
    simple: { fontSize: "14px", fontWeight: "400", color: "var(--zaplane-font-color)", lineHeight: '20px' },
    simpleLight: { fontSize: "12px", fontWeight: "400", color: "var(--zaplane-gray-color)" },
    bold: { fontSize: "20px", lineHeight: '30px', fontWeight: "500", color: "var(--zaplane-font-color)" },
    boldLight: { fontSize: "16px", fontWeight: "500", color: "var(--zaplane-gray-color)" },
    inputLabel: { fontSize: "14px", fontWeight: "500", color: "var(--zaplane-font-color)", lineHeight: '20px', margin: '0' }
  };
  const styles = variantStyles[type] || variantStyles.title;
  const textStyle = {
    fontSize: fontSize || styles.fontSize,
    fontWeight: fontWeight || styles.fontWeight,
    textTransform: textTransform || undefined,
    color: color || styles.color,
    background: bg || undefined,
    borderRadius: borderRadius || undefined,
    margin: margin,
    padding: padding || undefined,
    fontFamily: "var(--zaplane-font)",
    textAlign: textAlign || undefined,
    borderBottom: borderBottomWidth ? `${borderBottomWidth} solid ${borderColor || 'currentColor'}` : undefined,
    whiteSpace: whiteSpace || undefined,
    lineHeight: lineHeight || undefined,
    textOverflow: textOverflow || undefined,
    overflow: (truncate || textOverflow) ? 'hidden' : undefined,
    cursor: onClick ? 'pointer' : undefined,
  };
  return <>
    {href ? (
      <button style={{background:'transparent', padding:0, border:'none', cursor:'pointer'}} onClick={onClick}>
        <Link to={href} style={{color: "var(--zaplane-font-color)"}}>
          <span style={textStyle}>{`${label}`}</span>
        </Link>
      </button>
    ) : (
      <span style={textStyle} onClick={onClick}>{`${label}`}</span>
    )}
  </>;
};
export default ZAPLabel;
