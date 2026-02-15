
import { Box, Button, Flex, Text, Accordion } from "@chakra-ui/react";
import { FieldArray } from "formik";
import { FiTrash2 } from "react-icons/fi";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { __ } from "@wordpress/i18n";
import { buildEmptyRule, insertVariableIntoGroup } from "./helper";
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import { useEffect, useState } from "react";
import { useDispatch } from "react-redux";
import { conditionVariables } from "@ZAPRedux/Slices/workFlowSlice/actions/conditonVariales";

const items = [
    {
        name: "Wordpress",
        variable: [
            { key: "post_modified", type: "string", value: "2026-02-09 02:11:10" },
            { key: "post_modified_gmt", type: "string", value: "0000-00-00 00:00:00" },
            { key: "post_content_filtered", type: "string", value: "" },
            { key: "post_parent", type: "integer", value: 0 },
            { key: "guid", type: "string", value: "http://localhost/kodezen/?p=13" },
        ],
    },
    { name: "slack", variable: [] },
];

export default function ConditionGroupField({ value, field, nodeId, workFlow }) {
    const ruleFields = field?.fields;
    const EMPTY_RULE = buildEmptyRule(ruleFields);

    const [isPopoverOpen, setPopoverOpen] = useState(false);
    const [activeInput, setActiveInput] = useState(null);

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
                                                <Flex key={rIndex} gap={4} align="flex-start" mb="15px">
                                                    {ruleFields.map((f) => {
                                                        if (f.type === "select") {
                                                            return (
                                                                <ZAPSelect
                                                                    key={f.key}
                                                                    label={f.label}
                                                                    options={f.options}
                                                                    value={rule[f.key]}
                                                                    onChange={(val) =>
                                                                        ruleHelpers.replace(rIndex, { ...rule, [f.key]: val })
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
                                                                    ruleHelpers.replace(rIndex, { ...rule, [f.key]: val });
                                                                    if (val.endsWith("@")) {
                                                                        setActiveInput({ gIndex, rIndex, fieldKey: f.key });
                                                                        setPopoverOpen(true);
                                                                    }
                                                                }}
                                                                containerStyle={{ width: "30%", alignSelf: "stretch" }}
                                                            />
                                                        );
                                                    })}

                                                    <Flex gap={2} mt="34px" align="center" minH="30px">
                                                        <Button
                                                            type="button"
                                                            height="34px"
                                                            onClick={() => ruleHelpers.push({ ...EMPTY_RULE })}
                                                        >
                                                            {__("Add", "zaplane")}
                                                        </Button>

                                                        <Button
                                                            type="button"
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

                        <Button size="sm" width="140px" onClick={() => groupHelpers.push([{ ...EMPTY_RULE }])}>
                            {__("OR Group", "zaplane")}
                        </Button>

                        <WPPopover
                            isOpen={isPopoverOpen}
                            onClose={() => {
                                setPopoverOpen(false);
                                setActiveInput(null);
                            }}
                            title="Insert data for Dynamic content"
                        >
                            <Accordion.Root collapsible>
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
                                        <Accordion.ItemTrigger px="12px" py="10px" _hover={{ bg: "gray.50" }}>
                                            <Flex align="center" w="100%">
                                                <Text flex="1" fontSize="sm" fontWeight="500">
                                                    {item.name}
                                                </Text>
                                                <Accordion.ItemIndicator />
                                            </Flex>
                                        </Accordion.ItemTrigger>

                                        <Accordion.ItemContent>
                                            <Accordion.ItemBody px="12px" py="10px" bg="gray.50">
                                                {item.variable.map((v, vi) => (
                                                    <Text
                                                        key={vi}
                                                        cursor="pointer"
                                                        fontSize="sm"
                                                        _hover={{ color: "blue.600" }}
                                                        onClick={() => {
                                                            insertVariableIntoGroup({
                                                                activeInput,
                                                                groups,
                                                                groupHelpers,
                                                                valueToInsert: v.key,
                                                                setPopoverOpen,
                                                                setActiveInput,
                                                            });
                                                        }}
                                                    >
                                                        {v.key}: {v.value}
                                                    </Text>
                                                ))}
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
