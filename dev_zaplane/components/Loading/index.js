import React from "react";
import { Flex, Spinner, Text } from "@chakra-ui/react";
import { __ } from '@wordpress/i18n';

const ZAPLoading = ({
  height = "300px",
  size = "xl",
  label,
  ...props
}) => {
  return (
    <Flex
      align="center"
      justify="center"
      h={height}
      direction="column"
      gap={3}
      {...props}
    >
      <Spinner size={size} />

      {label && (
        <Text fontSize="sm" color="var(--zaplane-body-background)">
         { __(label, 'zaplane')}
        </Text>
      )}
    </Flex>
  );
};

export default ZAPLoading;
