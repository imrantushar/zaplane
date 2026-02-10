import { Box, Button, Flex, Text, Accordion } from "@chakra-ui/react";
import { FieldArray } from "formik";
import { FiTrash2 } from "react-icons/fi";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { __ } from "@wordpress/i18n";
import { buildEmptyRule } from "./helper";
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import { useEffect, useState } from "react";
import { useDispatch } from "react-redux";
import { conditionVariables } from "@ZAPRedux/Slices/workFlowSlice/actions/conditonVariales";

const items = [
    { value: "a", title: "First Item", text: "Some value 1..." },
    { value: "b", title: "Second Item", text: "Some value 2..." },
    { value: "c", title: "Third Item", text: "Some value 3..." },
];

export default function ConditionGroupField({
    value,
    field,
    nodeId,
    workFlow,
}) {
    const ruleFields = field?.fields;
    const EMPTY_RULE = buildEmptyRule(ruleFields);
    const [isPopoverOpen, setPopoverOpen] = useState(false);
    const dispatch = useDispatch();

    useEffect(() => {
        if (!nodeId || !workFlow?.version?.hash) return;

        dispatch(
            conditionVariables({
                targetNodeKey: nodeId,
                workflowHash: workFlow.version.hash,
            })
        );
    }, [dispatch, nodeId, workFlow?.version?.hash]);

    return (
        <FieldArray name={field.key}>
            {(groupHelpers) => {
                if (!value || !value.length) {
                    groupHelpers.push([{ ...EMPTY_RULE }]);
                }

                const groups = value || [];

                return (
                    <Flex direction="column" gap={4}>
                        {groups.map((group, gIndex) => (
                            <Box key={gIndex}>
                                {groups.length > 1 && gIndex !== 0 && (
                                    <Flex align="center" mb={3}>
                                        <Box flex="1" h="1px" bg="gray.300" />
                                        <Text mx={3} fontSize="sm">
                                            {__("OR", "zaplane")}
                                        </Text>
                                        <Box flex="1" h="1px" bg="gray.300" />
                                    </Flex>
                                )}

                                <FieldArray name={`${field.key}.${gIndex}`}>
                                    {(ruleHelpers) => (
                                        <>
                                            {group.map((rule, rIndex) => (
                                                <Flex
                                                    key={rIndex}
                                                    gap={4}
                                                    align="flex-start" 
                                                    mb="15px"
                                                >
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
                                                                type="textarea"
                                                                label={f.label}
                                                                value={rule[f.key]}
                                                                onChange={(e) => {
                                                                    const val = e.target.value;
                                                                    ruleHelpers.replace(rIndex, {
                                                                        ...rule,
                                                                        [f.key]: val,
                                                                    });

                                                                    setPopoverOpen(val.includes("@"));
                                                                }}
                                                                containerStyle={{
                                                                    width: "30%",
                                                                    alignSelf: "stretch",
                                                                }}
                                                            />
                                                        );
                                                    })}

                                                    <Flex
                                                        gap={2}
                                                        mt="22px"
                                                        align="center"
                                                        minH="30px"
                                                    >
                                                        <Button
                                                            type="button"
                                                            onClick={() =>
                                                                ruleHelpers.push({ ...EMPTY_RULE })
                                                            }
                                                        >
                                                            {__("Add", "zaplane")}
                                                        </Button>

                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={
                                                                group.length === 1 &&
                                                                groups.length === 1 &&
                                                                !gIndex
                                                            }
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
                            <Accordion.Root collapsible>
                                {items.map((item, index) => (
                                    <Accordion.Item key={index} value={item.value}>
                                        <Accordion.ItemTrigger px="12px" py="10px">
                                            <Text flex="1" fontSize="sm">
                                                {item.title}
                                            </Text>
                                            <Accordion.ItemIndicator />
                                        </Accordion.ItemTrigger>

                                        <Accordion.ItemContent>
                                            <Accordion.ItemBody px="12px" py="10px">
                                                <Text fontSize="sm">{item.text}</Text>
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
