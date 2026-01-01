import {
  Accordion,
  Span,
  VStack,
  Badge,
  HStack,
  Box,
  Text,
} from "@chakra-ui/react";

const LogDetails = () => {
  return (
    <Accordion.Root collapsible defaultValue={["schedule"]}>
      <Accordion.Item value="schedule">
        <Accordion.ItemTrigger>
          <HStack flex="1" justify="space-between">
            <HStack>
              <Text fontWeight="medium">Schedule</Text>
              <Badge colorScheme="blue">1</Badge>
              <Text fontSize="sm" color="gray.500">
                Tools
              </Text>
            </HStack>

            <HStack>
              <Box w="6px" h="6px" borderRadius="full" bg="green.500" />
              <Text fontSize="sm">Success</Text>
            </HStack>
          </HStack>

          <Accordion.ItemIndicator />
        </Accordion.ItemTrigger>
        <Accordion.ItemContent>
          <Accordion.ItemBody>
            <VStack align="stretch" spacing="3">
              <Box
                bg="white"
                p="3"
                borderRadius="md"
                border="1px solid"
                borderColor="gray.200"
              >
                <Text fontFamily="mono" fontSize="sm">
                  <Badge mr="2" colorScheme="purple">
                    +
                  </Badge>
                  "Input": []{" "}
                  <Span color="gray.500">0 items</Span>
                </Text>
              </Box>

              <Box
                bg="white"
                p="3"
                borderRadius="md"
                border="1px solid"
                borderColor="gray.200"
              >
                <Text fontFamily="mono" fontSize="sm">
                  <Badge mr="2" colorScheme="purple">
                    +
                  </Badge>
                  "Output": []{" "}
                  <Span color="gray.500">0 items</Span>
                </Text>
              </Box>
            </VStack>
          </Accordion.ItemBody>
        </Accordion.ItemContent>
      </Accordion.Item>
    </Accordion.Root>
  );
};

export default LogDetails;
