import {
    Box,
    Button,
    Flex,
    Text,
} from "@chakra-ui/react";
import { FiTrash2 } from "react-icons/fi";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ZAPText from "@ZAPComponents/Text";


export default function ConditionGroupField({ value=[[{}]], onChange, field }) {
    const update = (newVal) => onChange(newVal);

    const leftField = field.fields.find((f) => f.key === "left");
    const operatorField = field.fields.find((f) => f.key === "operator");
    const rightField = field.fields.find((f) => f.key === "right");


    const addRule = () => {
        const newVal = [...value];
        newVal[0].push({ left: "", operator: "==", right: "" });
        update(newVal);
    };

    const deleteRule = (index) => {
        const newVal = [...value];
        newVal[0] = newVal[0].filter((_, i) => i !== index);
        update(newVal);
    };

    const updateRule = (index, key, value_) => {
        const newVal = [...value];
        newVal[0][index][key] = value_;
        update(newVal);
    };
    const addOrGroup = () => {
        const newVal = [...value, [{ left: "", operator: "==", right: "" }]];
        update(newVal);
    };

    const addOrRule = (gIndex) => {
        const newVal = [...value];
        newVal[gIndex].push({ left: "", operator: "==", right: "" });
        update(newVal);
    };

    const updateOrRule = (gIndex, rIndex, key, value_) => {
        const newVal = [...value];
        newVal[gIndex][rIndex][key] = value_;
        update(newVal);
    };

    const deleteOrRule = (gIndex, rIndex) => {
        const newVal = [...value];
        newVal[gIndex] = newVal[gIndex].filter((_, i) => i !== rIndex);
        update(newVal);
    };


    return (
        <Flex direction="column" gap={4}>
            {value[0].map((rule, i) => (
                <Flex key={i} gap={4} align="flex-end">

                    <ZAPInput
                        label={"Condition"}
                        placeholder={"Here to add data"}
                        style={{ width: "30%" }}
                        value={rule.left}
                        onChange={(e) =>
                            updateRule(i, "left", e.target.value)
                        }
                    />
                    <ZAPSelect
                        label={operatorField.label}
                        style={{ width: "30%" }}
                        options={operatorField.options}
                        value={rule.operator || null}
                        onChange={(selected) =>
                            updateRule(i, "operator", selected)
                        }
                    />

                    <ZAPInput
                        label={"Value"}
                        placeholder={"Here to add data"}
                        style={{ width: "30%" }}
                        value={rule.right}
                        onChange={(e) =>
                            updateRule(i, "right", e.target.value)
                        }
                    />

                    <Flex gap={2} mt="25px">
                        <Button onClick={addRule}>AND</Button>
                        <Button
                            colorScheme="red"
                            variant="ghost"
                            size="sm"
                            onClick={() => deleteRule(i)}
                        >
                            <FiTrash2 />
                        </Button>
                    </Flex>
                </Flex>
            ))}
            {value.slice(1).map((group, gIndex) => (
                <Box key={gIndex} p={3}>
                    <Flex align="center">
                        <Box flex="1" h="1px" bg="gray.300" />
                        <ZAPText mx={3} fontSize="sm" color="gray.500">
                            OR
                        </ZAPText>
                        <Box flex="1" h="1px" bg="gray.300" />
                    </Flex>

                    {group.map((rule, rIndex) => (
                        <Flex key={rIndex} gap={4} align="flex-end">

                            <ZAPInput
                                label={"Condtion"}
                                placeholder={"Here to add data"}
                                style={{ width: "30%" }}
                                value={rule.left}
                                onChange={(e) =>
                                    updateOrRule(gIndex + 1, rIndex, "left", e.target.value)
                                }
                            />

                            <ZAPSelect
                                label={operatorField.label}
                                style={{ width: "30%" }}
                                options={operatorField.options}
                                value={rule.operator || null}
                                onChange={(selected) =>
                                    updateOrRule(
                                        gIndex + 1,
                                        rIndex,
                                        "operator",
                                        selected
                                    )
                                }
                            />

                            <ZAPInput
                                label={"Value"}
                                placeholder={"Here to add data"}
                                style={{ width: "30%" }}
                                value={rule.right}
                                onChange={(e) =>
                                    updateOrRule(gIndex + 1, rIndex, "right", e.target.value)
                                }
                            />

                            <Flex gap={2} mt="25px">
                                <Button onClick={() => addOrRule(gIndex + 1)}>
                                    AND
                                </Button>
                                <Button
                                    colorScheme="red"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        deleteOrRule(gIndex + 1, rIndex)
                                    }
                                >
                                    <FiTrash2 />
                                </Button>
                            </Flex>
                        </Flex>
                    ))}
                </Box>
            ))}

            <Button width="120px" size="sm" onClick={addOrGroup}>
                + Or Group
            </Button>
        </Flex>
    );
}
