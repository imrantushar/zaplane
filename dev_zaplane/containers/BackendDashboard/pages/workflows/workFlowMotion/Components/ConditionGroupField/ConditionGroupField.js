import {
    Box,
    Button,
    HStack,
    VStack,
    Input,
    Text,
    Flex,
} from "@chakra-ui/react";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { FiTrash2 } from "react-icons/fi";
import Select from "react-select";

const OPERATORS = [
    { label: "==", value: "==" },
    { label: "!=", value: "!=" },
    { label: "<", value: "<" },
    { label: ">", value: ">" },
    { label: "<=", value: "<=" },
    { label: ">=", value: ">=" },
];

export default function ConditionGroupField({ value, onChange }) {
    const val = value && value.length ? value : [[{ left: "", operator: "==", right: "" }]];
    const update = (newVal) => onChange(newVal);
    const addRule = () => {
        const newVal = [...val];
        newVal[0].push({ left: "", operator: "==", right: "" });
        update(newVal);
    };

    const deleteRule = (index) => {
        const newVal = [...val];
        newVal[0] = newVal[0].filter((_, i) => i !== index);
        update(newVal);
    };

    const updateRule = (index, key, val_) => {
        const newVal = [...val];
        newVal[0][index][key] = val_;
        update(newVal);
    };
    const addOrGroup = () => {
        const newVal = [...val, [{ left: "", operator: "==", right: "" }]];
        update(newVal);
    };

    const updateOrRule = (gIndex, rIndex, key, val_) => {
        const newVal = [...val];
        newVal[gIndex][rIndex][key] = val_;
        update(newVal);
    };

    const addOrRule = (gIndex) => {
        const newVal = [...val];
        newVal[gIndex].push({ left: "", operator: "==", right: "" });
        update(newVal);
    };

    const deleteOrRule = (gIndex, rIndex) => {
        const newVal = [...val];
        newVal[gIndex] = newVal[gIndex].filter((_, i) => i !== rIndex);
        update(newVal);
    };

    return (
        <Flex flexDirection="column" gap="10px">
            {val[0].map((rule, i) => (
                <Flex key={i} gap={4} align="flex-end">
                    <ZAPInput
                        label="Condition"
                        placeholder="Condition"
                        value={rule.left}
                        style={{ width: "30%" }}
                        onChange={(e) => updateRule(i, "left", e.target.value)}
                    />
                    <ZAPSelect
                        label="Operator"
                        options={OPERATORS}
                        style={{ width: "30%" }}
                        value={OPERATORS.find(op => op.value === rule.operator)}
                        onChange={(selected) => updateRule(i, "operator", selected.value)}
                    />
                    <ZAPInput
                        label="value"
                        placeholder="Value"
                        style={{ width: "30%" }}
                        value={rule.right}
                        onChange={(e) => updateRule(i, "right", e.target.value)} />
                    <Flex gap={2} marginTop="25px">
                        <Button onClick={addRule}>AND</Button>
                        <Button colorScheme="red" variant="ghost" size="sm" onClick={() => deleteRule(i)}>
                            <FiTrash2 />
                        </Button>
                    </Flex>
                </Flex>
            ))}
            {val.slice(1).map((group, gIndex) => (
                <Box key={gIndex} p={3} borderWidth="1px" gap="5px" borderRadius="md">
                    <Text fontWeight="bold" mb={2}>OR</Text>
                    {group.map((rule, rIndex) => (
                        <HStack key={rIndex}>
                            <ZAPInput
                                style={{ width: "30%" }}
                                label="Condition"
                                value={rule.left}
                                placeholder="Condition"
                                onChange={(e) => updateOrRule(gIndex + 1, rIndex, "left", e.target.value)}
                            />
                            <ZAPSelect
                                label="Operator"
                                style={{width:"30%"}}
                                options={OPERATORS}
                                value={OPERATORS.find(op => op.value === rule.operator)}
                                onChange={(selected) => updateOrRule(gIndex + 1, rIndex, "operator", selected.value)}
                            />
                            <ZAPInput
                                label="value"
                                value={rule.right}
                                style={{width:"30%"}}
                                placeholder="Value"
                                onChange={(e) => updateOrRule(gIndex + 1, rIndex, "right", e.target.value)}
                            />
                            <Flex gap={2} marginTop="25px">
                                <Button onClick={() => addOrRule(gIndex + 1)}>AND</Button>
                                <Button colorScheme="red" variant="ghost" size="sm" onClick={() => deleteOrRule(gIndex + 1, rIndex)}>
                                    <FiTrash2 />
                                </Button>
                            </Flex>
                        </HStack>
                    ))}
                </Box>
            ))}

            <Button size="sm" width="100px" onClick={addOrGroup}>+ Or Group</Button>
        </Flex>
    );
}
