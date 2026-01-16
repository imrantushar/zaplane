import { Box, Flex, Badge, Spinner, Text } from "@chakra-ui/react";
import ZAPText from "@ZAPComponents/Text";
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
                <ZAPText fontSize="xl" fontWeight="semibold">
                  {singleData.name}
                </ZAPText>
                <ZAPText fontSize="sm" color="gray.500">
                  {singleData.app} connection
                </ZAPText>
              </Box>

              <Badge
                px={4}
                py={1.5}
                fontSize="sm"
                borderRadius="full"
                colorScheme={singleData.status === "active" ? "green" : "gray"}
                textTransform="capitalize"
              >
                {singleData.status}
              </Badge>
            </Flex>
          </Box>

          {/* Info Boxes */}
          <Flex gap={4} wrap="wrap">
            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <ZAPText fontSize="xs" color="gray.500">
                AUTH TYPE
              </ZAPText>
              <ZAPText fontSize="md" fontWeight="medium">
                {singleData.auth_type}
              </ZAPText>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <ZAPText fontSize="xs" color="gray.500">
                CREATED AT
              </ZAPText>
              <ZAPText fontSize="md" fontWeight="medium">
                {singleData.created_at}
              </ZAPText>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <ZAPText fontSize="xs" color="gray.500">
                LAST USED
              </ZAPText>
              <Text fontSize="md" fontWeight="medium">
                {singleData.last_used_at || "--"}
              </Text>
            </Box>

            <Box flex="1 1 45%" p={4} borderRadius="lg" borderWidth="1px" bg="gray.50">
              <ZAPText fontSize="xs" color="gray.500">
                LAST TESTED
              </ZAPText>
              <ZAPText fontSize="md" fontWeight="medium">
                {singleData.last_tested_at || "--"}
              </ZAPText>
            </Box>
          </Flex>
        </Box>
      )}
    </WPModal>
  );
};

export default ConnectionDetails;
