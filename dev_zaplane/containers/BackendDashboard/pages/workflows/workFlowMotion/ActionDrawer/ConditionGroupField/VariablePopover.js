import React from "react";
import { Accordion, Text, Flex } from "@chakra-ui/react";
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import { insertVariableIntoGroup } from "./helper";

export default function VariablePopover({ isOpen, onClose, data, activeInput, groups, groupHelpers, setPopoverOpen, setActiveInput }) {
    return (
        <WPPopover isOpen={isOpen} onClose={onClose} title="Insert data for Dynamic content">
            <Accordion.Root type="single" collapsible>
                {data?.map((item, index) => (
                    <Accordion.Item key={item.node_id} value={`node-${item.node_id}`} border="1px solid var(--zaplane-border-color)" borderBottom={index === data.length - 1 ? "1px solid var(--zaplane-border-color)" : "0"}>
                        <Accordion.ItemTrigger px="12px" py="10px" bg='var(--zaplane-body-background)'>
                            <Flex align="center" w="100%">
                                <Text flex="1" fontSize="sm" fontWeight="500">{item.node_name}</Text>
                                <Accordion.ItemIndicator />
                            </Flex>
                        </Accordion.ItemTrigger>

                        <Accordion.ItemContent>
                            <Accordion.ItemBody px="12px" py="10px" bg="var(--zaplane-background)" maxH="200px" overflowY="auto">
                                {item.variables?.length > 0 ? item.variables.map((v, vi) => (
                                    <Text key={vi} cursor="pointer" fontSize="sm" p='10px 0' _hover={{ background:"var(--zaplane-body-background)" }}
                                        onClick={() => {
                                            const formattedValue = `{{${item.node_id}.${v.key}}}`;
                                            insertVariableIntoGroup({ activeInput, groups, groupHelpers, valueToInsert: formattedValue, setPopoverOpen, setActiveInput });
                                        }}>
                                        {v.key} : {v.sample}
                                    </Text>
                                )) : <Text textAlign="center">No fields available</Text>}
                            </Accordion.ItemBody>
                        </Accordion.ItemContent>
                    </Accordion.Item>
                ))}
            </Accordion.Root>
        </WPPopover>
    );
}
