import React from "react";
import { Box } from "@chakra-ui/react";

const ZAPDivider = ({
  height = "1px",
  bg = "var(--zaplane-border-color)",
  w = "100%",
  my,
  ...props
}) => {
  return (
    <Box
      role="separator"
      bg={bg}
      height={height}
      w={w}
      my={my}
      {...props}
    />
  );
};

export default ZAPDivider;
