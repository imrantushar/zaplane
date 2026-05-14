import React from "react";
const ZAPDivider = ({
  height = "1px",
  bg = "var(--zaplane-border-color)",
  w = "100%",
  my,
  mt,
  mb,
  ...props
}) => {
  const marginTop = mt || (my ? my : undefined);
  const marginBottom = mb || (my ? my : undefined);
  return <div role="separator" style={{background: bg, height, width: w, marginTop, marginBottom}} {...props} />;
};
export default ZAPDivider;