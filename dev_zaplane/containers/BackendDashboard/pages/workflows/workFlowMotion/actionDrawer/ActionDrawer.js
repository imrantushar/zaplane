import {
    Drawer,
    Portal,
    Button,
    CloseButton,
    VStack,
    Text,
    Box,
    HStack,
    Input,
    Tabs,
    Flex,
    Code
} from "@chakra-ui/react";
import { fetchDynamic, getSingleRun, getSingleRunDetails, getSingleRunNode, liveMonitor, singleNodeRun } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { integrations } from "@ZAPUtils/helper";
import { useFormikContext } from "formik";
import { useEffect, useMemo, useState } from "react";
import { useDispatch } from "react-redux";
import Select from "react-select";

const APPS = Object.entries(integrations?.integrations || {}).map(
    ([key, value]) => ({
        id: value.slug || key,
        name: value.name,
    })
);

const TOOLS = [
    // { id: "condition", name: "Condition" },
    { id: "router", name: "Router" },
];

export default function ActionDrawer({
    open,
    context,
    onClose,
    updateNodeData,
    createActionNode,
    createConditionNode,
}) {
    const { source, node } = context;
    const [mode, setMode] = useState(null);
    const [step, setStep] = useState("select");
    const [selectedItem, setSelectedItem] = useState(null);
    const [dynamicOptions, setDynamicOptions] = useState({});
    const [loadingFields, setLoadingFields] = useState({});
    const { values, setFieldValue, resetForm } = useFormikContext();
    const dispatch = useDispatch()

    const [conditions, setConditions] = useState([
        // {
        //     id: crypto.randomUUID(),
        //     type: "AND",
        //     rules: [
        //         {
        //             id: crypto.randomUUID(),
        //             field: "",
        //             operator: "",
        //             value: "",
        //         },
        //     ],
        // },
    ]);

    // const addAndCondition = (groupId) => {
    //     setConditions((prev) =>
    //         prev.map((g) =>
    //             g.id === groupId
    //                 ? {
    //                     ...g,
    //                     rules: [
    //                         ...g.rules,
    //                         { id: crypto.randomUUID(), field: "", operator: "", value: "" },
    //                     ],
    //                 }
    //                 : g
    //         )
    //     );
    // };

    // const addOrGroup = () => {
    //     setConditions((prev) => [
    //         ...prev,
    //         {
    //             id: crypto.randomUUID(),
    //             type: "OR",
    //             rules: [{ id: crypto.randomUUID(), field: "", operator: "", value: "" }],
    //         },
    //     ]);
    // };

    // const updateRule = (groupId, ruleId, key, value) => {
    //     setConditions((prev) =>
    //         prev.map((g) =>
    //             g.id === groupId
    //                 ? {
    //                     ...g,
    //                     rules: g.rules.map((r) => (r.id === ruleId ? { ...r, [key]: value } : r)),
    //                 }
    //                 : g
    //         )
    //     );
    // };

    // const removeRule = (groupId, ruleId) => {
    //     setConditions((prev) =>
    //         prev.map((g) =>
    //             g.id === groupId
    //                 ? { ...g, rules: g.rules.filter((r) => r.id !== ruleId) }
    //                 : g
    //         )
    //     );
    // };

    const resetAll = () => {
        setMode(null);
        setStep("select");
        setSelectedItem(null);
        onClose();
        // setConditions([
        //     {
        //         id: crypto.randomUUID(),
        //         rules: [{ id: crypto.randomUUID(), field: "", operator: "", value: "" }],
        //     },
        // ]);
        resetForm();
    };

    const LIST = mode === "app" && APPS;
    const actionOptions = useMemo(() => {
        if (!selectedItem?.id) return [];
        const integration = integrations?.integrations?.[selectedItem.id];
        if (!integration) return [];
        const isTriggerNode = node?.data?.action === "trigger" && source === "node";

        if (isTriggerNode) {
            return Object.values(integration.triggers || {}).map((t) => ({
                label: t.label,
                value: t.key,
            }));
        }
        return Object.values(integration.actions || {}).map((a) => ({
            label: a.label,
            value: a.key,
        }));
    }, [selectedItem, context?.node]);
    const selectedActionFields = useMemo(() => {
        if (!selectedItem?.id || !values?.actionType) return [];

        const integration = integrations?.integrations?.[selectedItem.id];
        if (!integration) return [];

        const isTriggerNode = node?.data?.action === "trigger" && source === "node";


        if (isTriggerNode) {
            return integration.triggers?.[values.actionType]?.schema || [];
        }

        return integration.actions?.[values.actionType]?.schema || [];
    }, [selectedItem, values?.actionType, context?.node]);
    const getKey = (field) =>
        `${context?.node?.data?.action}:${selectedItem?.id}:${field.key}`;

    // ✅ FIX: fetchDynamic ajax
    const fetchDynamicOptions = async (field) => {
        if (!field.dynamic) return;

        const key = getKey(field);
        if (dynamicOptions[key]) return;

        setLoadingFields(p => ({ ...p, [key]: true }));

        try {
            const res = await fetchDynamic(field.dynamic); 
            setDynamicOptions(p => ({
                ...p,
                [key]: Object.values(res).map(i => ({
                    value: i[field.dynamic.select[0]],
                    label: i[field.dynamic.select[1]],
                })),
            }));
        } catch (err) {
            console.error("Dynamic fetch failed:", err);
        }

        setLoadingFields(p => ({ ...p, [key]: false }));
    };

    //  Action/Trigger chane dynamic cache reset
    useEffect(() => {
        if (!values?.actionType) return;
        setDynamicOptions({});
        setLoadingFields({});
    }, [values?.actionType]);
    const renderField = (field, values, setFieldValue) => {
        switch (field.type) {
            case "text":
            case "expression":
                return (
                    <Box>
                        <Text fontSize="sm" margin="0 0 4px 0">{field.label}</Text>
                        <Input
                            size="sm"
                            value={values[field.key] || ""}
                            onChange={(e) =>
                                setFieldValue(field.key, e.target.value)
                            }
                            placeholder={field.label}
                        />
                    </Box>
                );

            case "textarea":
                return (
                    <Box>
                        <Text fontSize="sm" margin="0 0 4px 0">{field.label}</Text>
                        <Input
                            as="textarea"
                            size="sm"
                            value={values[field.key] || ""}
                            onChange={(e) =>
                                setFieldValue(field.key, e.target.value)
                            }
                            placeholder={field.label}
                        />
                    </Box>
                );

            case "select":
                if (field.options) {
                    const options = field.options.map((opt) => ({
                        value: opt.value,
                        label: opt.label,
                    }));

                    return (
                        <Box>
                            <Text fontSize="sm" margin="0 0 4px 0">{field.label}</Text>
                            <Select
                                options={options}
                                value={
                                    options.find(
                                        (opt) =>
                                            opt.value === values[field.key]
                                    ) || null
                                }
                                onChange={(opt) =>
                                    setFieldValue(field.key, opt.value)
                                }
                            />
                        </Box>
                    );
                }
                if (field.dynamic) {
                    const key = getKey(field);
                    const opts = dynamicOptions[key] || [];

                    return (
                        <Box>
                            <Text fontSize="sm" margin="0 0 4px 0">
                                {field.label}
                                {field.required && " *"}
                            </Text>

                            <Select
                                options={opts}
                                isLoading={loadingFields[key]}
                                value={
                                    opts.find(
                                        (opt) =>
                                            opt.value === values[field.key]
                                    ) || null
                                }
                                onMenuOpen={() =>
                                    fetchDynamicOptions(field)
                                }
                                onChange={(opt) =>
                                    setFieldValue(field.key, opt?.value)
                                }
                            />
                        </Box>
                    );
                }

                return null;

            default:
                return null;
        }
    };

    const handleContinue = () => {
        if (step === "select") setStep("configure");
        else if (step === "configure") setStep("test");
        else if (step === "test") {
            if (selectedItem.id === "condition") {
                createConditionNode({ conditions });
                resetAll();
                return;
            }

            const payload = {
                app: selectedItem.name,
                name: selectedItem.name,
                event: values?.actionType,
                config: selectedActionFields.reduce((acc, field) => {
                    acc[field.key] = values[field.key];
                    return acc;
                }, {}),
            };
            const triggerPayload = {
                app: selectedItem.name,
                name: selectedItem.name,
                event: values?.actionType,
                config: selectedActionFields.reduce((acc, field) => {
                    acc[field.key] = values[field.key];
                    return acc;
                }, {}),
            };

            if (context?.source === "node") {
                if (node?.data?.action === "trigger") {
                    updateNodeData(triggerPayload);
                } else {
                    updateNodeData(payload);
                }
            } else {
                createActionNode(payload);
            }

            resetAll();
        }
    };
    const isContinueDisabled = () => {
        if (step === "select") return !values.actionType;
        if (step === "configure") return !values.connection;
        return false;
    };
    useEffect(() => {
        if (!open) return;
        if (context?.source === "node" && node?.data?.app) {
            const matchedApp = APPS.find(
                (app) =>
                    app.name === node.data.app ||
                    app.id === node.data.app?.toLowerCase()
            );

            if (matchedApp) {
                setMode("app");
                setSelectedItem(matchedApp);
                setStep("select");
                if (node?.data?.config) {
                    Object.entries(node.data.config).forEach(([key, value]) => {
                        setFieldValue(key, value);
                    });
                }

                if (node?.data?.event) {
                    setFieldValue("actionType", node.data.event);
                }
            }
        }
    }, [open, node]);
    console.log(node);

    return (
        <Drawer.Root open={open} size="md" onOpenChange={(e) => !e.open && resetAll()}>
            <Portal>
                <Drawer.Backdrop />
                <Drawer.Positioner marginTop='30px'>
                    <Drawer.Content>
                        <Drawer.Header>
                            <Drawer.Title margin="0">
                                {!mode && "Choose Type"}
                                {mode && !selectedItem && `Select ${mode}`}
                                {selectedItem && selectedItem.name}
                            </Drawer.Title>
                            <Drawer.CloseTrigger asChild>
                                <CloseButton />
                            </Drawer.CloseTrigger>
                        </Drawer.Header>

                        <Drawer.Body>
                            {!mode && (
                                <VStack spacing={4}>
                                    <Button w="100%" justifyContent="left" onClick={() => setMode("app")}>Apps</Button>
                                    {TOOLS.map((item) => (
                                        <Button
                                            key={item.id}
                                            width="100%"
                                            justifyContent="space-between"
                                            onClick={() => {
                                                setSelectedItem(item);
                                                setStep("select");
                                                setMode("tools");
                                            }}
                                        >
                                            {item.name}
                                        </Button>
                                    ))}
                                </VStack>
                            )}

                            {mode && !selectedItem && (
                                <VStack align="stretch">
                                    {LIST.map((item) => (
                                        <Button
                                            key={item.id}
                                            justifyContent="space-between"
                                            onClick={() => {
                                                setSelectedItem(item);
                                                setStep("select");
                                            }}
                                        >
                                            {item.name} →
                                        </Button>
                                    ))}
                                    <Button size="sm" variant="ghost" onClick={() => setMode(null)}>← Back</Button>
                                </VStack>
                            )}

                            {selectedItem && (
                                <Tabs.Root value={step} isManual>
                                    <Tabs.List mb={4}>
                                        <Tabs.Trigger value="select">Select</Tabs.Trigger>
                                        <Tabs.Trigger value="configure">Configure</Tabs.Trigger>
                                        <Tabs.Trigger value="test">Test</Tabs.Trigger>
                                        <Tabs.Indicator />
                                    </Tabs.List>

                                    <Tabs.Content value="select">
                                        {selectedItem.id === "condition" ? (
                                            <>Condition</>
                                        ) : (
                                            <Flex direction="column" gap={4}>
                                                <Box>
                                                    <Text mb={0}>{node?.data?.action === "trigger" && source === "node" ? "Trigger Type" : "Action Type"}</Text>
                                                    <Select
                                                        options={actionOptions}
                                                        value={
                                                            actionOptions.find(
                                                                (opt) => opt.value === values.actionType
                                                            ) || null
                                                        }
                                                        onChange={(opt) => setFieldValue("actionType", opt?.value)}
                                                    />
                                                </Box>
                                                {selectedActionFields.map((field) => (
                                                    <Box key={field.key}>
                                                        {renderField(field, values, setFieldValue)}
                                                    </Box>
                                                ))}
                                            </Flex>
                                        )}
                                    </Tabs.Content>

                                    <Tabs.Content value="configure">
                                        {selectedItem.id === "condition" ? (
                                            <VStack align="stretch" gap={4}>
                                                {conditions.map((group, gi) => (
                                                    <Box key={group.id} border="1px solid #E2E8F0" p={3} rounded="md">
                                                        {group.rules.map((rule) => (
                                                            <HStack key={rule.id} mb={2}>
                                                                <Box width="35%">
                                                                    <Input
                                                                        placeholder="Condition"
                                                                        value={rule.field}
                                                                        onChange={(e) => updateRule(group.id, rule.id, "field", e.target.value)}
                                                                    />
                                                                </Box>
                                                                <Box width="35%">
                                                                    <Select
                                                                        placeholder="Operator"
                                                                        options={[
                                                                            { value: "equals", label: "Equals" },
                                                                            { value: "contains", label: "Contains" },
                                                                        ]}
                                                                        onChange={(opt) => updateRule(group.id, rule.id, "operator", opt.value)}
                                                                    />
                                                                </Box>
                                                                <Box width="35%">
                                                                    <Input
                                                                        placeholder="Value"
                                                                        value={rule.value}
                                                                        onChange={(e) => updateRule(group.id, rule.id, "value", e.target.value)}
                                                                    />
                                                                </Box>
                                                                <Button size="sm" colorScheme="red" onClick={() => removeRule(group.id, rule.id)}>✕</Button>
                                                            </HStack>
                                                        ))}
                                                        <Button size="sm" variant="outline" onClick={() => addAndCondition(group.id)}>+ And</Button>
                                                        {gi !== conditions.length - 1 && (
                                                            <Text textAlign="center" my={2} fontSize="sm" color="gray.500">OR</Text>
                                                        )}
                                                    </Box>
                                                ))}
                                                <Button variant="outline" onClick={addOrGroup}>+ Or Group</Button>
                                            </VStack>
                                        ) : (
                                            <>
                                                <Text margin='0'>There have only config data</Text>
                                            </>
                                        )}
                                    </Tabs.Content>

                                    <Tabs.Content value="test">
                                        <Box>
                                            <Button
                                                onClick={() => dispatch(singleNodeRun(node?.id))}
                                            >
                                                Run test
                                            </Button>
                                            <Text>Response</Text>
                                            <Box
                                                border="1px solid"
                                                borderColor="gray.200"
                                                borderRadius="md"
                                                bg="gray.50"
                                                p={4}
                                            >
                                                <Tabs.Root defaultValue="output">
                                                    <Tabs.List
                                                        display="flex"
                                                        gap={6}
                                                        borderBottom="1px solid"
                                                        borderColor="gray.200"
                                                        mb={4}
                                                        position="relative"
                                                    >
                                                        <Tabs.Trigger
                                                            value="input"
                                                            fontWeight="medium"
                                                            color="gray.600"
                                                        // _selected={{ color: "blue.600" }}
                                                        >
                                                            Input
                                                        </Tabs.Trigger>

                                                        <Tabs.Trigger
                                                            value="output"
                                                            fontWeight="medium"
                                                            color="gray.600"
                                                        // _selected={{ color: "blue.600" }}
                                                        >
                                                            Output
                                                        </Tabs.Trigger>

                                                        <Tabs.Indicator
                                                            height="2px"
                                                            bg="blue.500"
                                                            borderRadius="full"
                                                        />
                                                    </Tabs.List>
                                                    <Tabs.Content value="input">
                                                        <Box
                                                            bg="white"
                                                            border="1px solid"
                                                            borderColor="gray.200"
                                                            borderRadius="md"
                                                            p={3}
                                                            fontSize="sm"
                                                        >
                                                            <Code>input</Code>
                                                        </Box>
                                                    </Tabs.Content>
                                                    <Tabs.Content value="output">
                                                        <Box
                                                            bg="white"
                                                            border="1px solid"
                                                            borderColor="gray.200"
                                                            borderRadius="md"
                                                            p={3}
                                                            fontSize="sm"
                                                        >
                                                            <Code>output</Code>
                                                        </Box>
                                                    </Tabs.Content>
                                                </Tabs.Root>
                                            </Box>
                                        </Box>
                                    </Tabs.Content>

                                </Tabs.Root>
                            )}
                        </Drawer.Body>

                        <Drawer.Footer>
                            <HStack justify="space-between" w="full">
                                <Button variant="ghost" onClick={resetAll}>Cancel</Button>
                                <Button colorScheme="blue" onClick={handleContinue} isDisabled={isContinueDisabled()}>
                                    {step === "test" ? "Submit" : "Continue"}
                                </Button>
                            </HStack>
                        </Drawer.Footer>
                    </Drawer.Content>
                </Drawer.Positioner>
            </Portal>
        </Drawer.Root>
    );
}
