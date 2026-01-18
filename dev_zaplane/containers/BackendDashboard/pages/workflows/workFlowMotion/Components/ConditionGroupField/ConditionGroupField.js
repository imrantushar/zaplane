import {
  Box,
  Button,
  HStack,
  VStack,
  Input,
  Text,
} from "@chakra-ui/react";
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
  const val = value && value.length ? value : [ [ { left: "", operator: "==", right: "" } ] ];
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
    const newVal = [...val, [ { left: "", operator: "==", right: "" } ]];
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
    <VStack align="stretch" spacing={4}>
      {val[0].map((rule, i) => (
        <HStack key={i}>
          <Input
            value={rule.left}
            placeholder="Condition"
            onChange={(e) => updateRule(i, "left", e.target.value)}
          />
          <Box width="200px">
            <Select
              value={OPERATORS.find(op => op.value === rule.operator)}
              onChange={(selected) => updateRule(i, "operator", selected.value)}
              options={OPERATORS}
            />
          </Box>
          <Input
            value={rule.right}
            placeholder="Value"
            onChange={(e) => updateRule(i, "right", e.target.value)}
          />
          <Button onClick={addRule}>AND</Button>
          <Button colorScheme="red" variant="ghost" size="sm" onClick={() => deleteRule(i)}>
            <FiTrash2 />
          </Button>
        </HStack>
      ))}
      {val.slice(1).map((group, gIndex) => (
        <Box key={gIndex} p={3} borderWidth="1px" borderRadius="md">
          <Text fontWeight="bold" mb={2}>OR</Text>
          {group.map((rule, rIndex) => (
            <HStack key={rIndex}>
              <Input
                value={rule.left}
                placeholder="Condition"
                onChange={(e) => updateOrRule(gIndex + 1, rIndex, "left", e.target.value)}
              />
              <Box width="200px">
                <Select
                  value={OPERATORS.find(op => op.value === rule.operator)}
                  onChange={(selected) => updateOrRule(gIndex + 1, rIndex, "operator", selected.value)}
                  options={OPERATORS}
                />
              </Box>
              <Input
                value={rule.right}
                placeholder="Value"
                onChange={(e) => updateOrRule(gIndex + 1, rIndex, "right", e.target.value)}
              />
              <Button onClick={() => addOrRule(gIndex + 1)}>AND</Button>
              <Button colorScheme="red" variant="ghost" size="sm" onClick={() => deleteOrRule(gIndex + 1, rIndex)}>
                <FiTrash2 />
              </Button>
            </HStack>
          ))}
        </Box>
      ))}

      <Button size="sm" width="100px" onClick={addOrGroup}>+ Or Group</Button>
    </VStack>
  );
}
