import {
  Accordion,
  Box,
  Badge,
  HStack,
  Text,
  VStack,

} from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import ZAPLoading from "@ZAPComponents/Loading";

import { useSelector } from "react-redux";
const LogDetails = ({ runId, onBack }) => {
  const { nodeDetails = [], isloading } = useSelector(
    (state) => state.workflows
  );
  if (isloading) {
    return <ZAPLoading />;
  }
//after the  response I’ll add translation support.
  return (
    <Box>
      <Text mb="4" fontWeight="bold"
        className="zaplane-label">

        {__(`Run ID: ${runId}`, 'zaplane')}
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
                    <Text fontWeight="medium" className="zaplane-label">
                      {__(`Node ${log.node_key}`, 'zaplane')}
                    </Text>
                    <Badge>
                      {__(`Log ${log.id}`, 'zaplane')}
                    </Badge>
                  </HStack>

                  <Badge
                    colorPalette={
                      log.status === "completed"
                        ? "green"
                        : log.status === "failed"
                          ? "red"
                          : "blue"

                    }
                  >
                    {__(log.status, 'zaplane')}
                  </Badge>
                </HStack>
                <Accordion.ItemIndicator />
              </Accordion.ItemTrigger>

              <Accordion.ItemContent>
                <Accordion.ItemBody>
                  <VStack spacing="4" align="stretch">
                    <Box
                      p="3"
                      border="1px solid var(--zaplane-border-color)"
                      borderRadius="md"
                      bg="var(--zaplane-gray)"
                    >
                      <Text className="zaplane-label" fontWeight="bold" mb="2">
                        {__('Input', 'zaplane')}
                      </Text>
                      <pre>{JSON.stringify(input, null, 2)}</pre>
                    </Box>

                    <Box
                      p="3"
                      border="1px solid var(--zaplane-border-color)"
                      borderRadius="md"
                      bg="var(--zaplane-gray)"
                    >
                      <Text fontWeight="bold" mb="2">
                        {__('Output', 'zaplane')}
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
