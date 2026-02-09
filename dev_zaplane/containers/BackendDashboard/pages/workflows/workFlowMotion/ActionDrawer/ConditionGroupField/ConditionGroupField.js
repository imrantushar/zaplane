import { Box, Button, Flex, Text, Accordion, Span } from "@chakra-ui/react";
import { FieldArray } from "formik";
import { FiTrash2 } from "react-icons/fi";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { __ } from "@wordpress/i18n";
import { buildEmptyRule } from "./helper";
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import { useState } from "react";
import { useDispatch } from "react-redux";
import { conditionVariables } from "@ZAPRedux/Slices/workFlowSlice/actions/conditonVariales";
const items = [
    { value: "a", title: "First Item", text: "Some value 1..." },
    { value: "b", title: "Second Item", text: "Some value 2..." },
    { value: "c", title: "Third Item", text: "Some value 3..." },
]
export default function ConditionGroupField({ value, onChange, field, nodeId ,singleData}) {
    console.log(nodeId, 'values');
    const ruleFields = field?.fields;
    const EMPTY_RULE = buildEmptyRule(ruleFields);
    const [isPopoverOpen, setPopoverOpen] = useState(false);
    const dispatch = useDispatch()
    dispatch(
        conditionVariables({
            targetNodeKey: nodeId,
            workflowHash: singleData?.version?.hash,
        })
    );



    return (
        <FieldArray name={field.key}>
            {(groupHelpers) => {
                if (!value || !value.length) {
                    groupHelpers.push([{ ...EMPTY_RULE }]);
                }

                const groups = value && value.length ? value : [];

                return (
                    <Flex direction="column" gap={4}>
                        {groups.map((group, gIndex) => (
                            <Box key={gIndex}>
                                {groups.length > 1 && gIndex !== 0 && (
                                    <Flex align="center">
                                        <Box flex="1" h="1px" bg="gray.300" />
                                        <Text className="zaplane-label" mx={3} fontSize="sm">
                                            {__("OR", "zaplane")}
                                        </Text>
                                        <Box flex="1" h="1px" bg="var(--zaplane-border-color)" />
                                    </Flex>
                                )}

                                <FieldArray name={`${field.key}.${gIndex}`}>
                                    {(ruleHelpers) => (
                                        <>
                                            {group.map((rule, rIndex) => (
                                                <Flex key={rIndex} gap={4} align="flex-end" mb="15px">
                                                    {ruleFields.map((f) => {
                                                        if (f.type === "select") {
                                                            return (
                                                                <ZAPSelect
                                                                    key={f.key}
                                                                    label={f.label}
                                                                    options={f.options}
                                                                    value={rule[f.key]}
                                                                    onChange={(val) =>
                                                                        ruleHelpers.replace(rIndex, {
                                                                            ...rule,
                                                                            [f.key]: val,
                                                                        })
                                                                    }
                                                                    containerStyle={{ width: "30%" }}
                                                                />
                                                            );
                                                        }

                                                        return (
                                                            <ZAPInput
                                                                key={f.key}
                                                                label={f.label}
                                                                value={rule[f.key]}
                                                                onChange={(e) => {
                                                                    const val = e.target.value
                                                                    ruleHelpers.replace(rIndex, {
                                                                        ...rule,
                                                                        [f.key]: val,
                                                                    })
                                                                    if (val.includes("@")) {
                                                                        setPopoverOpen(true);
                                                                    } else {
                                                                        setPopoverOpen(false);
                                                                    }
                                                                }
                                                                }
                                                                containerStyle={{ width: "30%" }}
                                                            />
                                                        );
                                                    })}

                                                    <Flex gap={2} mt="25px">
                                                        <Button
                                                            type="button"
                                                            onClick={() => ruleHelpers.push({ ...EMPTY_RULE })}
                                                        >
                                                            {__("Add", "zaplane")}
                                                        </Button>

                                                        <Button
                                                            type="button"
                                                            colorScheme="#FF0000"
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={group.length === 1 && groups.length === 1 && !gIndex}
                                                            onClick={() => {
                                                                if (group.length === 1) {
                                                                    groupHelpers.remove(gIndex);
                                                                } else {
                                                                    ruleHelpers.remove(rIndex);
                                                                }
                                                            }}
                                                        >
                                                            <FiTrash2 />
                                                        </Button>
                                                    </Flex>
                                                </Flex>
                                            ))}
                                        </>
                                    )}
                                </FieldArray>
                            </Box>
                        ))}

                        <Button
                            size="sm"
                            width="140px"
                            onClick={() => groupHelpers.push([{ ...EMPTY_RULE }])}
                        >
                            {__("OR Group", "zaplane")}
                        </Button>
                        <WPPopover
                            isOpen={isPopoverOpen}
                            onClose={() => setPopoverOpen(false)}
                            title="Insert data for Dynamic content"
                        >
                            <Accordion.Root collapsible >
                                {items.map((item, index) => (
                                    <Accordion.Item
                                        key={index}
                                        value={item.value}
                                        border="1px solid var(--zaplane-border-color)"
                                        borderBottom={index === items.length - 1 ? "1px solid var(--zaplane-border-color)" : "0"}
                                        borderBottomRadius={index === items.length - 1 ? "md" : "0"} 
                                        borderTopRadius={index === 0 ? "md" : "0"} 
                                        overflow="hidden"
                                    >
                                        <Accordion.ItemTrigger
                                            px="12px"
                                            py="10px"
                                            _hover={{ bg: "gray.50" }}
                                        >
                                            <Flex align="center" w="100%">
                                                <Text flex="1" fontSize="sm" fontWeight="500">
                                                    {item.title}
                                                </Text>
                                                <Accordion.ItemIndicator />
                                            </Flex>
                                        </Accordion.ItemTrigger>

                                        <Accordion.ItemContent>
                                            <Accordion.ItemBody px="12px" py="10px" bg="gray.50">
                                                <Text fontSize="sm">
                                                    {item.text}
                                                </Text>
                                            </Accordion.ItemBody>
                                        </Accordion.ItemContent>
                                    </Accordion.Item>
                                ))}
                            </Accordion.Root>
                        </WPPopover>

                    </Flex>
                );
            }}
        </FieldArray>
    );
}
