import {
  Accordion,
  Box,
  Badge,
  HStack,
  Text,
  VStack,

} from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPLoading from "@ZAPComponents/Loading";
import ReactJson from "react-json-view";

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
      <ZAPLabel label={__(`Run ID: ${runId}`, 'zaplane')} type={"inputLabel"}/> 
      <Accordion.Root collapsible>
        {nodeDetails?.nodes?.map((log) => {
          const input = log?.input_json || {};
          const output = log?.output_json || {};

          return (
            <Accordion.Item key={log.id} value={log.id} border='1px solid var(--zaplane-border-color)'
            p='10px' borderRadius='8px' m='10px 0'>
              <Accordion.ItemTrigger p='0' >
                <HStack flex="1" justify="space-between">
                  <VStack gap={0}>
                    <Text fontWeight="medium" className="zaplane-label">
                      {sprintf(
                        __('%s', 'zaplane'),log?.node?.app)}
                    </Text>
                    <Text className="zaplane-sub-title">
                      {sprintf(
                        __('%s', 'zaplane'),log?.node?.event)}
                    </Text>
                  </VStack>

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
                     <ReactJson
                        src={input}
                        name="root"
                        collapsed={1}
                        enableClipboard={false}
                        displayDataTypes={false}
                      />
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
                      <ReactJson
                        src={output}
                        name="root"
                        collapsed={1}
                        enableClipboard={false}
                        displayDataTypes={false}
                      />
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
