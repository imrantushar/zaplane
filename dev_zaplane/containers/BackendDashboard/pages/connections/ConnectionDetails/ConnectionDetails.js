import { Box, Flex, Badge, Spinner, Text } from "@chakra-ui/react";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { __ } from "@wordpress/i18n";

const ConnectionDetails = ({ isOpen, onClose, singleData }) => {
  return (
    <WPModal
      title={__("Connection Details", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
    >
      {!singleData ? (
        <Flex justify="center" align="center" py={12}>
          <Spinner size="lg" />
        </Flex>
      ) : (
        <Box>
          {/* Top Card */}
          <Box
            p={5}
            borderRadius="lg"
            bg="white"
            borderWidth="1px"
            mb={5}
            boxShadow="sm"
          >
            <Flex justify="space-between" align="center">
              <Box>
                <Text className="zaplane-label" fontSize="xl" fontWeight="semibold">
                  {__(singleData.name, "zaplane")}
                </Text>
                <Text className="zaplane-label" fontSize="sm" color="gray.500">
                  {__(singleData.app, "zaplane")}
                </Text>
              </Box>

              <Badge
                px={4}
                py={1.5}
                fontSize="sm"
                borderRadius="full"
                colorScheme={singleData.status === "active" ? "green" : "gray"}
                textTransform="capitalize"
              >
                {__(singleData.status, "zaplane")}
              </Badge>
            </Flex>
          </Box>
          <Flex gap={4} wrap="wrap">
            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <Text fontSize="xs" className="zaplane-label">
                {__('AUTH TYPE', 'zaplane')}
              </Text>
              <Text fontSize="md" fontWeight="medium">
                {__(singleData.auth_type, 'zaplane')}
              </Text>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <Text fontSize="xs" className="zaplane-label">
                {__('CREATED AT', 'zaplane')}
              </Text>
              <Text fontSize="md" fontWeight="medium" className="zaplane-label" >
                {__(singleData.created_at, "zaplane")}
              </Text>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <Text fontSize="xs" className="zaplane-label">
                {__('LAST USED', 'zaplane')}
              </Text>
              <Text fontSize="md" fontWeight="medium">
                {__(singleData.last_used_at || "--", "zaplane")}
              </Text>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <Text fontSize="xs" className="zaplane-label">
                {__('LAST TESTED', 'zaplane')}
              </Text>
              <Text fontSize="md" fontWeight="medium" className="zaplane-label">
                {singleData.last_tested_at || "--"}
              </Text>
            </Box>
          </Flex>
        </Box>
      )}
    </WPModal>
  );
};

export default ConnectionDetails;
