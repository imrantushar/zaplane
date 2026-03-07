import React from "react";
import { Accordion, Text, Flex } from "@chakra-ui/react";
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import { __ } from "@wordpress/i18n";
import { formatVariableKey, insertVariableIntoGroup } from "./helper";

export default function VariablePopover({
  isOpen,
  onClose,
  data,
  onSelectVariable,   
  activeInput,  
  groups,
  groupHelpers,
  setPopoverOpen,
  setActiveInput,
  prefix,
}) {
  const handleClick = (item, variable) => {
    const formattedValue = `{{${item.node_id}.${variable.key}}}`;
    if (onSelectVariable) {
      onSelectVariable(formattedValue);
    } else if (activeInput && groups && groupHelpers) {
      insertVariableIntoGroup({
        activeInput,
        groups,
        groupHelpers,
        valueToInsert: formattedValue,
        setPopoverOpen,
        setActiveInput,
      });
    }
  };

  return (
    <WPPopover isOpen={isOpen} onClose={onClose} 
     title={__("Insert data for Dynamic content", 'zaplane')}
     prefix={prefix}>
      <Accordion.Root type="single" collapsible>
        {data?.map((item, index) => (
          <Accordion.Item
            key={item.node_id}
            value={`node-${item.node_id}`}
            border="1px solid var(--zaplane-border-color)"
            borderBottom={index === data.length - 1 ? "1px solid var(--zaplane-border-color)" : "0"}
            borderRadius={index === 0 ? "4px 4px 0 0" : index === data.length - 1 ? "0 0 4px 4px" : "0"}
          >
            <Accordion.ItemTrigger
              px="12px"
              py="10px"
              bg="var(--zaplane-body-background)"
              _focus={{ boxShadow: "none", outline: "none" }}
              _focusVisible={{ boxShadow: "none", outline: "none" }}
              _expanded={{ bg: "var(--zaplane-body-background)" }}
            >
              <Flex align="center" w="100%">
                <Text flex='1' className="zaplane-label">{item.node_name}</Text>
                <Accordion.ItemIndicator />
              </Flex>
            </Accordion.ItemTrigger>

            <Accordion.ItemContent>
              <Accordion.ItemBody py="10px" bg="var(--zaplane-background)" maxH="200px" overflowY="auto">
                {item.variables?.length > 0 ? item.variables.map((v, vi) => (
                  <Flex
                    key={vi}
                    p='8px 15px'
                    alignItems="center"
                    cursor="pointer"
                    _hover={{ background: "var(--zaplane-body-background)" }}
                    onClick={() => handleClick(item, v)}
                  >
                    <Text as="span" fontSize="sm" className="zaplane-label">
                      {__(formatVariableKey(v.key), 'zaplane')}
                    </Text>
                    <Text as="p" m='0' fontWeight="400" color="#64748b" textOverflow="ellipsis" overflow='hidden'>
                      {" : "}{__(v.sample,'zaplane')}
                    </Text>
                  </Flex>
                )) : <Text textAlign="center">{__("No fields available", 'zaplane')}</Text>}
              </Accordion.ItemBody>
            </Accordion.ItemContent>
          </Accordion.Item>
        ))}
      </Accordion.Root>
    </WPPopover>
  );
}