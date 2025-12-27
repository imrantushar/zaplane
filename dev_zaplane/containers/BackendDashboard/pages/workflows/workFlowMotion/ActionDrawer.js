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
} from "@chakra-ui/react";
import { integrations } from "@ZAPUtils/helper";
import { useFormikContext } from "formik";
import { useEffect, useMemo, useState } from "react";
import Select from "react-select";
const APPS = [
    {
        id: "wordpress",
        name: "WordPress",
        actions: [
            { id: "create_post", name: "Create Post" },
            { id: "update_post", name: "Update Post" },
        ],
    },
    {
        id: "gemini",
        name: "Gemini",
        actions: [{ id: "generate_text", name: "Generate Text" }],
    },
];

const TOOLS = [
    {
        id: "condition",
        name: "Condition",
    },
    {
        id: "router",
        name: "Router",
    },
];

export default function ActionDrawer({ open, context, onClose, updateTriggerNode, createActionNode, createConditionNode, }) {
    const { source, node } = context
    const [mode, setMode] = useState(null);
    const [step, setStep] = useState("select");
    const [selectedItem, setSelectedItem] = useState(null);
    const [eventType, setEventType] = useState(null);
    const [connection, setConnection] = useState("");
    const { values, setFieldValue, resetForm } = useFormikContext()

    const [conditions, setConditions] = useState([
        {
            id: crypto.randomUUID(),
            type: "AND",
            rules: [
                {
                    id: crypto.randomUUID(),
                    field: "",
                    operator: "",
                    value: "",
                },
            ],
        },
    ]);
    const addAndCondition = (groupId) => {
        setConditions((prev) =>
            prev.map((g) =>
                g.id === groupId
                    ? {
                        ...g,
                        rules: [
                            ...g.rules,
                            {
                                id: crypto.randomUUID(),
                                field: "",
                                operator: "",
                                value: "",
                            },
                        ],
                    }
                    : g
            )
        );
    };

    const addOrGroup = () => {
        setConditions((prev) => [
            ...prev,
            {
                id: crypto.randomUUID(),
                type: "OR",
                rules: [
                    {
                        id: crypto.randomUUID(),
                        field: "",
                        operator: "",
                        value: "",
                    },
                ],
            },
        ]);
    };

    const updateRule = (groupId, ruleId, key, value) => {
        setConditions((prev) =>
            prev.map((g) =>
                g.id === groupId
                    ? {
                        ...g,
                        rules: g.rules.map((r) =>
                            r.id === ruleId ? { ...r, [key]: value } : r
                        ),
                    }
                    : g
            )
        );
    };

    const removeRule = (groupId, ruleId) => {
        setConditions((prev) =>
            prev.map((g) =>
                g.id === groupId
                    ? { ...g, rules: g.rules.filter((r) => r.id !== ruleId) }
                    : g
            )
        );
    };

    const resetAll = () => {
        setMode(null);
        setStep("select");
        setSelectedItem(null);
        setEventType(null);
        setConnection("");
        onClose();
        setConditions([{
            id: crypto.randomUUID(),
            rules: [
                {
                    id: crypto.randomUUID(),
                    field: "",
                    operator: "",
                    value: "",
                },
            ],
        },]),
            resetForm()
    };

    const LIST = mode === "app" && APPS;

    const handleContinue = () => {
        if (step === "select") {
            setStep("configure");
        } else if (step === "configure") {
            setStep("test");
        } else if (step === "test") {
            if (selectedItem.id === "condition") {
                createConditionNode({
                    conditions,
                });

                resetAll();
                return;
            }
            const payload = {
                label: selectedItem.name,
                eventType: values.eventType,
                api_access_key: values.api_access_key,
                api_access_url: values.api_access_url,
                connection_title: values.connection_title,
                connection: values.connection,

            };
            if (context?.source === "node" && context.node?.data?.action === "Trigger") {
                updateTriggerNode(payload);
            } else {
                createActionNode(payload);
            }

            resetAll();
        }
    };


    const isContinueDisabled = () => {
        if (step === "select") {
            return !eventType || !connection;
        }
        if (step === "configure") {
            return !connection;
        }
        return false;
    };
    const wordpressIntegration = integrations?.integrations?.wordpress;

    const actionOptions = useMemo(() => {
    if (!selectedItem?.id) return [];

    const actions =
        integrations.integrations?.[selectedItem.id]?.actions || {};

    return Object.values(actions).map((action) => ({
        label: action.label,
        value: action.key,
    }));
}, [selectedItem]);
    console.log(actionOptions,'actionOptions', selectedItem);

    // async function getPosts() {
    //   const posts = await fetchDynamic({
    //     integration: "wordpress",
    //     query: "posts",
    //     select: ["ID", "post_title"],
    //     limit: 20,
    //   });

    //   console.log(posts,'response');
    // }
    // getPosts()
    const renderField = (field, values, setFieldValue) => {
        switch (field.type) {
            case "text":
            case "expression":
                return (
                    <Input
                        size="sm"
                        value={values[field.key] || ""}
                        onChange={(e) =>
                            setFieldValue(field.key, e.target.value)
                        }
                        placeholder={field.label}
                    />
                );

            case "textarea":
                return (
                    <Input
                        as="textarea"
                        size="sm"
                        value={values[field.key] || ""}
                        onChange={(e) =>
                            setFieldValue(field.key, e.target.value)
                        }
                        placeholder={field.label}
                    />
                );

            case "select":
                // static options
                if (field.options) {
                    return (
                        <Select
                            options={field.options.map((opt) => ({
                                value: opt.value,
                                label: opt.label,
                            }))}
                            onChange={(opt) =>
                                setFieldValue(field.key, opt.value)
                            }
                        />
                    );
                }

                // dynamic (API call future-proof)
                if (field.dynamic) {
                    return (
                        <Select
                            placeholder={`Load ${field.label}`}
                            options={[]} // 👈 API থেকে আসবে
                            onChange={(opt) =>
                                setFieldValue(field.key, opt.value)
                            }
                        />
                    );
                }

                return null;

            default:
                return null;
        }
    };
    return (
        <Drawer.Root open={open} size="md" onOpenChange={(e) => !e.open && resetAll()}>
            <Portal>
                <Drawer.Backdrop />
                <Drawer.Positioner>
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
                                    <Button w="100%" justifyContent='left' onClick={() => setMode("app")}>
                                        Apps
                                    </Button>
                                    {TOOLS.map((item) => (
                                        <Button
                                            width="100%"
                                            key={item.id}
                                            justifyContent="space-between"
                                            onClick={() => {
                                                setSelectedItem(item);
                                                setStep("select");
                                                setMode("tools")
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
                                    <Button size="sm" variant="ghost" onClick={() => setMode(null)}>
                                        ← Back
                                    </Button>
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
                                        {selectedItem.id === "condition" ? (<>condition</>) : (
                                            <>
                                                <Flex direction="column" gap={4}>
                                                    <Select
                                                          options={actionOptions}
                                                        onChange={(opt) =>
                                                            setFieldValue('actionType', opt?.value)
                                                         
                                                            
                                                        }
                                                    />
                                                </Flex></>
                                        )}
                                    </Tabs.Content>
                                    <Tabs.Content value="configure">
                                        <VStack align="stretch" gap={4}>
                                            {selectedItem.id === "condition" ? (
                                                <>  {conditions.map((group, gi) => (
                                                    <Box
                                                        key={group.id}
                                                        border="1px solid #E2E8F0"
                                                        p={3}
                                                        rounded="md"
                                                    >
                                                        {group.rules.map((rule) => (
                                                            <HStack key={rule.id} mb={2}>
                                                                <Box width="35%">
                                                                    <Input
                                                                        placeholder="Condition"
                                                                        value={rule.field}
                                                                        onChange={(e) =>
                                                                            updateRule(
                                                                                group.id,
                                                                                rule.id,
                                                                                "field",
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                    />
                                                                </Box>

                                                                <Box width="35%">
                                                                    <Select
                                                                        placeholder="Operator"
                                                                        options={[
                                                                            { value: "equals", label: "Equals" },
                                                                            { value: "contains", label: "Contains" },
                                                                        ]}
                                                                        onChange={(opt) =>
                                                                            updateRule(
                                                                                group.id,
                                                                                rule.id,
                                                                                "operator",
                                                                                opt.value
                                                                            )
                                                                        }
                                                                    />
                                                                </Box>

                                                                <Box width="35%">
                                                                    <Input
                                                                        placeholder="Value"
                                                                        value={rule.value}
                                                                        onChange={(e) =>
                                                                            updateRule(
                                                                                group.id,
                                                                                rule.id,
                                                                                "value",
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                    />
                                                                </Box>

                                                                <Button
                                                                    size="sm"
                                                                    colorScheme="red"
                                                                    onClick={() =>
                                                                        removeRule(group.id, rule.id)
                                                                    }
                                                                >
                                                                    ✕
                                                                </Button>
                                                            </HStack>
                                                        ))}

                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => addAndCondition(group.id)}
                                                        >
                                                            + And
                                                        </Button>

                                                        {gi !== conditions.length - 1 && (
                                                            <Text
                                                                textAlign="center"
                                                                my={2}
                                                                fontSize="sm"
                                                                color="gray.500"
                                                            >
                                                                OR
                                                            </Text>
                                                        )}
                                                    </Box>
                                                ))}

                                                    <Button variant="outline" onClick={addOrGroup}>
                                                        + Or Group
                                                    </Button>
                                                </>
                                            ) : (
                                                <>
                                                    <Text margin={0} fontSize="sm">Connection*</Text>
                                                    <Input
                                                        size="sm"
                                                        value={values.connection}
                                                        onChange={(e) => setFieldValue('connection', e.target.value)
                                                        }
                                                        placeholder="Update API connection"
                                                    /></>
                                            )}



                                        </VStack>
                                    </Tabs.Content>

                                    {/* TEST */}
                                    <Tabs.Content value="test">
                                        <Text fontWeight="bold">Test Step</Text>
                                        <Text fontSize="sm" color="gray.500">
                                            Everything looks good. Click submit to save.
                                        </Text>

                                    </Tabs.Content>
                                </Tabs.Root>
                            )}
                        </Drawer.Body>

                        <Drawer.Footer>
                            <HStack justify="space-between" w="full">
                                <Button variant="ghost" onClick={resetAll}>
                                    Cancel
                                </Button>
                                <Button
                                    colorScheme="blue"
                                    onClick={handleContinue}
                                    isDisabled={isContinueDisabled()}
                                >
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
