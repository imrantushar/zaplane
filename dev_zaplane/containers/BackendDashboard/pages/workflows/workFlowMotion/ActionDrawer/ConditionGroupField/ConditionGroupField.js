import { Box, Button, Flex, Text } from "@chakra-ui/react";
import { FieldArray } from "formik";
import { FiTrash2 } from "react-icons/fi";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { __ } from "@wordpress/i18n";
import { buildEmptyRule } from "./helper";
import { useState } from "react";
import VariableEditor from "@ZAPComponents/VariableEditor";



export default function ConditionGroupField({ value, field, variables }) {
    const ruleFields = field?.fields;
    const EMPTY_RULE = buildEmptyRule(ruleFields);
    const [isPopoverOpen, setPopoverOpen] = useState(false);
    const [activeInput, setActiveInput] = useState(null);
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
                                                                        ruleHelpers.replace(rIndex, { ...rule, [f.key]: val.value })
                                                                    }
                                                                    containerStyle={{ width: "30%" }}
                                                                />
                                                            );
                                                        }

                                                        return (
                                                            <VariableEditor
                                                                containerStyle={{ width: '30%' }}
                                                                label={f.label}
                                                                placeholder={__('Type "@" here to...', "zaplane")}
                                                                value={rule[f.key]}
                                                                variables={variables}
                                                                field={{ key: `${field.key}.${gIndex}.${rIndex}.${f.key}` }}
                                                                setFieldValue={(key, val) => {
                                                                    ruleHelpers.replace(rIndex, {
                                                                        ...rule,
                                                                        [f.key]: val
                                                                    });
                                                                }}
                                                            />
                                                        );
                                                    })}

                                                    <Flex gap={2} mt="27px" align="center" minH="30px">
                                                        <Button
                                                            type="button"
                                                            height="34px"
                                                            bg={"var(--zaplane-secondary)"} color="var(--zaplane-font-color)"
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

                        <Button bg={"var(--zaplane-secondary)"} color="var(--zaplane-font-color)" size="sm" width="140px" fontWeight="500"
                            onClick={() => groupHelpers.push([{ ...EMPTY_RULE }])}>
                            {__("OR Group", "zaplane")}
                        </Button>
                    </Flex>
                );
            }}
        </FieldArray>
    );
}
