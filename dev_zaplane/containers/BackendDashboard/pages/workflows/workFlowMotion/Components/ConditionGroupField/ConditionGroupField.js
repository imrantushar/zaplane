import { Box, Button, Flex, Text } from "@chakra-ui/react";
import { FieldArray } from "formik";
import { FiTrash2 } from "react-icons/fi";

import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { __ } from "@wordpress/i18n";
import { buildEmptyRule } from "./helper";

export default function ConditionGroupField({ value, onChange, field }) {
    const ruleFields = field?.fields;
    const EMPTY_RULE = buildEmptyRule(ruleFields);

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
                                                                onChange={(e) =>
                                                                    ruleHelpers.replace(rIndex, {
                                                                        ...rule,
                                                                        [f.key]: e.target.value,
                                                                    })
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
                    </Flex>
                );
            }}
        </FieldArray>
    );
}
