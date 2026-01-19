import { Box, Button, Flex } from "@chakra-ui/react";
import { FieldArray } from "formik";
import { FiTrash2 } from "react-icons/fi";

import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ZAPText from "@ZAPComponents/Text";
import { __ } from "@wordpress/i18n";

const EMPTY_RULE = { left: "", operator: "==", right: "" };

export default function ConditionGroupField({
    value = [[{ ...EMPTY_RULE }]],
    onChange,
    field,
}) {
    const operatorField = field.fields.find(f => f.key === "operator");

    return (
        <FieldArray name={field.key}>
            {groupHelpers => (
                <Flex direction="column" gap={4}>
                    {value.map((group, gIndex) => (
                        <Box key={gIndex}>
                            {gIndex > 0 && (
                                <Flex align="center" my={3}>
                                    <Box flex="1" h="1px" bg="gray.300" />
                                    <ZAPText mx={3} fontSize="sm">OR</ZAPText>
                                    <Box flex="1" h="1px" bg="gray.300" />
                                </Flex>
                            )}
                            <FieldArray name={`${field.key}.${gIndex}`}>
                                {ruleHelpers => (
                                    <>
                                        {group.map((rule, rIndex) => (
                                            <Flex key={rIndex} gap={4} align="flex-end">
                                                <ZAPInput
                                                    label="Condition"
                                                    value={rule.left}
                                                    onChange={(e) =>
                                                        ruleHelpers.replace(rIndex, {
                                                            ...rule,
                                                            left: e.target.value,
                                                        })
                                                    }
                                                    style={{ width: "30%" }}
                                                />

                                                <ZAPSelect
                                                    label={operatorField.label}
                                                    options={operatorField.options}
                                                    value={rule.operator}
                                                    onChange={(val) =>
                                                        ruleHelpers.replace(rIndex, {
                                                            ...rule,
                                                            operator: val,
                                                        })
                                                    }
                                                    style={{ width: "30%" }}
                                                />

                                                <ZAPInput
                                                    label="Value"
                                                    value={rule.right}
                                                    onChange={(e) =>
                                                        ruleHelpers.replace(rIndex, {
                                                            ...rule,
                                                            right: e.target.value,
                                                        })
                                                    }
                                                    style={{ width: "30%" }}
                                                />

                                                <Flex gap={2} mt="25px">
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
                                                        colorScheme="red"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            ruleHelpers.remove(rIndex);
                                                            if (group.length === 1 && gIndex > 0) {
                                                                groupHelpers.remove(gIndex);
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
                        onClick={() =>
                            groupHelpers.push([{ ...EMPTY_RULE }])
                        }
                    >
                        {__("OR Group", "zaplane")}
                    </Button>
                </Flex>
            )}
        </FieldArray>
    );
}
