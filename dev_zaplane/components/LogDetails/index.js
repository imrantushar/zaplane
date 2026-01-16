import {
  Accordion,
  Box,
  Badge,
  HStack,
  Text,
  VStack,
} from "@chakra-ui/react";
import { useSelector } from "react-redux";

const LogDetails = ({ runId, onBack }) => {
  const { nodeDetails = [] } = useSelector(
    (state) => state.workflows
  );

  return (
    <Box>
      <Text
        mb="4"
        cursor="pointer"
        color="blue.500"
        onClick={onBack}
      >
        ← Back to Runs
      </Text>

      <Text mb="4" fontWeight="bold">
        Run ID: #{runId}
      </Text>

      <Accordion.Root collapsible>
        {nodeDetails?.nodes?.map((log) => {
          const input = JSON.parse(log.input_json || "{}");
          const output = JSON.parse(log.output_json || "{}");

          return (
            <Accordion.Item key={log.id} value={log.id}>
              <Accordion.ItemTrigger>
                <HStack flex="1" justify="space-between">
                  <HStack>
                    <Text fontWeight="medium">
                      Node #{log.node_key}
                    </Text>
                    <Badge>Log {log.id}</Badge>
                  </HStack>

                  <Badge
                    colorScheme={
                      log.status === "completed"
                        ? "green"
                        : log.status === "failed"
                        ? "red"
                        : "blue"
                    }
                  >
                    {log.status}
                  </Badge>
                </HStack>
                <Accordion.ItemIndicator />
              </Accordion.ItemTrigger>

              <Accordion.ItemContent>
                <Accordion.ItemBody>
                  <VStack spacing="4" align="stretch">
                    <Box
                      p="3"
                      border="1px solid"
                      borderColor="gray.200"
                      borderRadius="md"
                      bg="gray.50"
                    >
                      <Text fontWeight="bold" mb="2">
                        Input
                      </Text>
                      <pre>{JSON.stringify(input, null, 2)}</pre>
                    </Box>

                    <Box
                      p="3"
                      border="1px solid"
                      borderColor="gray.200"
                      borderRadius="md"
                      bg="gray.50"
                    >
                      <Text fontWeight="bold" mb="2">
                        Output
                      </Text>
                      <pre>{JSON.stringify(output, null, 2)}</pre>
                    </Box>
                  </VStack>
                </Accordion.ItemBody>
              </Accordion.ItemContent>
            </Accordion.Item>
          );
        })}
      </Accordion.Root>
    </Box>
  );
};

export default LogDetails;
