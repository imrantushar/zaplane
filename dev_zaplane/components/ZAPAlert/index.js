import { Alert } from "@chakra-ui/react";

const ZAPAlert = ({
  status = "info",
  title,
  description,
  children,
  ...rest
}) => {
  return (
    <Alert.Root
      status={status}
      borderRadius="md"
      {...rest}
    >
      <Alert.Indicator />

      <Alert.Content>
        {title && (
          <Alert.Title fontSize="sm" fontWeight="semibold">
            {title}
          </Alert.Title>
        )}

        {description && (
          <Alert.Description fontSize="sm">
            {description}
          </Alert.Description>
        )}

        {children}
      </Alert.Content>
    </Alert.Root>
  );
};

export default ZAPAlert;