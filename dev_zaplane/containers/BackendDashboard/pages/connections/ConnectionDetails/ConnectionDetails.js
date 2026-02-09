import { Box, Flex, Badge, Spinner, Text } from "@chakra-ui/react";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { __ } from "@wordpress/i18n";

const ConnectionDetails = ({ isOpen, onClose, connection }) => {
  return (
    <WPModal
      title={__("Connection Details", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
    >
      {!connection ? (
        <Flex justify="center" align="center" py={12}>
          <Spinner size="lg" />
        </Flex>
      ) : (
        <Box>
          <Box
            p={5}
            borderRadius="lg"
            bg="var(--zaplane-background)"
            borderWidth="1px"
            mb={5}
            boxShadow="sm"
          >
            <Flex justify="space-between" align="center">
              <Box>
                <Text className="zaplane-label" fontSize="xl" fontWeight="semibold">
                  {__(connection.name, "zaplane")}
                </Text>
                <Text className="zaplane-label" fontSize="sm" color="gray.500">
                  {__(connection.app, "zaplane")}
                </Text>
              </Box>
              <Badge
                px={4}
                py={1.5}
                fontSize="sm"
                borderRadius="full"
                colorPalette={connection.status === "active" ? "green" : "gray"}
                textTransform="capitalize"
              >
                {__(connection.status, "zaplane")}
              </Badge>
            </Flex>
          </Box>
          <Flex gap={4} wrap="wrap">
            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <Text className="zaplane-label">
                {__('AUTH TYPE', 'zaplane')}
              </Text>
              <Text className="zaplane-label" fontSize="md" fontWeight="medium">
                {__(connection.auth_type, 'zaplane')}
              </Text>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <Text  className="zaplane-label">
                {__('CREATED AT', 'zaplane')}
              </Text>
              <Text fontSize="md" fontWeight="medium" className="zaplane-label" >
                {__(connection.created_at, "zaplane")}
              </Text>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <Text className="zaplane-label">
                {__('LAST USED', 'zaplane')}
              </Text>
              <Text className="zaplane-label" fontSize="md" fontWeight="medium">
                {__(connection.last_used_at || "--", "zaplane")}
              </Text>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <Text  className="zaplane-label">
                {__('LAST TESTED', 'zaplane')}
              </Text>
              <Text fontSize="md" fontWeight="medium" className="zaplane-label">
                {connection.last_tested_at || "--"}
              </Text>
            </Box>
          </Flex>
        </Box>
      )}
    </WPModal>
  );
};

export default ConnectionDetails;
