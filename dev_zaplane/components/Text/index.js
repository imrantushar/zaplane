import React from "react";
import { Text } from "@chakra-ui/react";

const ZAPText = ({
  children,
  variant = "body",
  ...props
}) => {
  const variants = {
    body: {
      fontSize: "sm",
      color: "gray.700",
    },
    muted: {
      fontSize: "sm",
      color: "gray.500",
    },
    label: {
      fontSize: "xs",
      fontWeight: "500",
      color: "gray.600",
      textTransform: "uppercase",
    },
    heading: {
      fontSize: "md",
      fontWeight: "600",
      color: "gray.800",
    },
  };

  return (
    <Text
      m={0}
      {...variants[variant]}
      {...props}
    >
      {children}
    </Text>
  );
};

export default ZAPText;
