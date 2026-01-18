import {
    Button,
    VStack,
    Text,
    Box,
    HStack,
    Input,
    Tabs,
    Flex,
    Code
} from "@chakra-ui/react";
import ZAPDrawer from "@ZAPComponents/Drawer";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ZAPText from "@ZAPComponents/Text";
import {
    fetchDynamic,
    workFLowSingeNodeExction
} from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { integrations } from "@ZAPUtils/helper";
import { useFormikContext } from "formik";
import { useEffect, useMemo, useState } from "react";
import { useDispatch } from "react-redux";
import Select from "react-select";
import ActionFieldRenderer from "../Components/ActionFieldRenderer/ActionFieldRenderer";
import ZAPTab from "@ZAPComponents/Tab";
import { IoIosArrowForward } from "react-icons/io";

const APPS = Object.entries(integrations.apps || {}).map(([key, value]) => ({
    id: value.slug || key,
    name: value.name,
}));

const TOOLS = Object.entries(integrations.tools || {}).map(([key, value]) => ({
    id: value.slug || key,
    name: value.name,
}));

export default function ActionDrawer({
    open,
    context,
    onClose,
    updateNodeData,
    createActionNode,
    singleData
}) {
    const { source, node } = context;
    const dispatch = useDispatch();
    const { values, setFieldValue, resetForm } = useFormikContext();

    const [mode, setMode] = useState(null);
    const [step, setStep] = useState("select");
    const [selectedItem, setSelectedItem] = useState(null);
    const [dynamicOptions, setDynamicOptions] = useState({});
    const [loadingFields, setLoadingFields] = useState({});
    const [search, setSearch] = useState("");

    const isTrigger =
        node?.data?.action === "trigger" && source === "node";

    useEffect(() => {
        if (!open || !node?.data || source === "add") return;

        const nodeData = node.data;
        let detectedItem =
            TOOLS.find(t => t.name === nodeData.app || t.id === nodeData.app) ||
            APPS.find(a => a.name === nodeData.app || a.id === nodeData.app);

        if (detectedItem) {
            setMode(TOOLS.includes(detectedItem) ? "tools" : "app");
            setSelectedItem(detectedItem);
        }

        if (nodeData.event) {
            setFieldValue("actionType", nodeData.event);
        }

        if (nodeData.config) {
            Object.entries(nodeData.config).forEach(([key, value]) => {
                setFieldValue(key, value);
            });
        }
    }, [open, node?.data]);

    const resetAll = () => {
        setMode(null);
        setStep("select");
        setSelectedItem(null);
        setSearch("");
        resetForm();
        onClose();
    };

    const LIST =
        mode === "app"
            ? APPS
            : mode === "tools"
                ? TOOLS
                : [];

    // search list)
    const SEARCH_LIST = useMemo(() => {
        if (!search) return [];

        const q = search.toLowerCase();

        const apps = APPS.map(a => ({ ...a, type: "app" }));

        const tools =
            isTrigger
                ? []
                : TOOLS.map(t => ({ ...t, type: "tools" }));

        return [...apps, ...tools].filter(item =>
            item.name.toLowerCase().includes(q)
        );
    }, [search, isTrigger]);


    const getIntegration = () => {
        if (!selectedItem?.id) return null;
        return mode === "tools"
            ? integrations.tools?.[selectedItem.id]
            : integrations.apps?.[selectedItem.id];
    };

    useEffect(() => {
        if (mode !== "tools" || !selectedItem) return;
        const tool = integrations.tools?.[selectedItem.id];
        const actions = Object.values(tool?.actions || {});
        if (actions.length === 1) {
            setFieldValue("actionType", actions[0].key);
        }
    }, [mode, selectedItem]);

    const actionOptions = useMemo(() => {
        const integration = getIntegration();
        if (!integration) return [];

        if (mode === "tools") {
            return Object.values(integration.actions || {}).map(a => ({
                label: a.label,
                value: a.key,
            }));
        }

        if (isTrigger) {
            return Object.values(integration.triggers || {}).map(t => ({
                label: t.label,
                value: t.key,
            }));
        }

        return Object.values(integration.actions || {}).map(a => ({
            label: a.label,
            value: a.key,
        }));
    }, [selectedItem, mode, node]);

    const selectedActionFields = useMemo(() => {
        const integration = getIntegration();
        if (!integration || !values?.actionType) return [];

        if (mode === "tools") {
            return integration.actions?.[values.actionType]?.schema || [];
        }

        if (isTrigger) {
            return integration.triggers?.[values.actionType]?.schema || [];
        }

        return integration.actions?.[values.actionType]?.schema || [];
    }, [selectedItem, values?.actionType, mode, node]);

    const getKey = (field) =>
        `${mode}:${selectedItem?.id}:${field.key}`;

    const fetchDynamicOptions = async (field) => {
        if (!field.dynamic) return;
        const key = getKey(field);
        if (dynamicOptions[key]) return;

        setLoadingFields(p => ({ ...p, [key]: true }));
        const res = await fetchDynamic(field.dynamic);

        setDynamicOptions(p => ({
            ...p,
            [key]: Object.values(res).map(i => ({
                value: i[field.dynamic.select[0]],
                label: i[field.dynamic.select[1]],
            })),
        }));
        setLoadingFields(p => ({ ...p, [key]: false }));
    };

    const handleContinue = () => {
        if (step === "select") return setStep("configure");
        if (step === "configure") return setStep("test");

        const payload = {
            app: selectedItem.name,
            name: selectedItem.name,
            event: values.actionType,
            config: selectedActionFields.reduce((acc, f) => {
                acc[f.key] = values[f.key];
                return acc;
            }, {}),
        };

        if (context?.source === "node") {
            updateNodeData(payload);
        } else {
            createActionNode(payload);
        }

        resetAll();
    };
    console.log(values, 'values')
    return (
        <ZAPDrawer
            open={open}
            onClose={resetAll}
            title={
                !mode
                    ? "Add Action"
                    : selectedItem?.name
                        ? selectedItem.name
                        : "App"
            }

            placement="end"
            size="md"
            footer={
                <HStack justify="space-between">
                    <Button variant="ghost" onClick={resetAll}>
                        Cancel
                    </Button>
                    <Button onClick={handleContinue}>
                        {step === "test" ? "Submit" : "Continue"}
                    </Button>
                </HStack>
            }
        >
            {/* search filed */}
            <Input
                placeholder="Search apps or tools..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}

            />
            {search && (
                <VStack spacing={2} align="stretch">
                    {SEARCH_LIST.map(item => (
                        <Button
                            key={`${item.type}-${item.id}`}
                            justifyContent="space-between"
                            onClick={() => {
                                setMode(item.type);
                                setSelectedItem(item);
                                setSearch("");
                            }}
                            background="white"
                            _hover={{
                                    bg: "var(--zaplane-body-background)",
                                }}
                        >
                            <ZAPText color='black'>{item.name}</ZAPText>
                            <ZAPText fontSize="xs" color="black">
                                {item.type === "tools" ? "Tool" : "App"}
                            </ZAPText>
                        </Button>
                    ))}
                </VStack>
            )}
            {!mode && !search && (
                <VStack spacing={4}>
                    <Button
                        w="100%"
                        background="white"
                        color="black"
                        justifyContent="space-between"
                        transition="all 0.2s ease"
                        _hover={{
                            bg: "var(--zaplane-body-background)",
                            "& svg": { transform: "translateX(4px)" },
                        }}
                        onClick={() => setMode("app")}>
                        <span>Apps</span>
                        <IoIosArrowForward />
                    </Button>

                    {(node?.data?.action !== "trigger" || source === "add") &&
                        TOOLS.map(tool => (
                            <Button
                                background="white"
                                color="black"
                                key={tool.id}
                                justifyContent="left"
                                w="100%"
                                _hover={{
                                    bg: "var(--zaplane-body-background)",
                                }}
                                onClick={() => {
                                    setMode("tools");
                                    setSelectedItem(tool);
                                }}
                            >
                                {tool.name}
                            </Button>
                        ))}
                </VStack>
            )}

            {mode && !selectedItem && !search && (
                <VStack>
                    {LIST.map(item => (
                        <Button
                            background="white"
                            color="black"
                            key={item.id}
                            w="100%"
                            onClick={() => setSelectedItem(item)}
                            justifyContent="left"
                            _hover={{
                                bg: "var(--zaplane-body-background)",
                            }}
                        >
                            {item.name}
                        </Button>
                    ))}
                    <Button size="sm" variant="ghost" onClick={() => setMode(null)}>
                        ← Back
                    </Button>
                </VStack>
            )}

            {selectedItem && (
                <ZAPTab
                    value={step}
                    tabs={[
                        {
                            value: "select",
                            label: "Select",
                            content: (
                                <>
                                    <ZAPSelect
                                        label={isTrigger ? "Trigger Type" : "Action Type"}
                                        options={actionOptions}
                                        value={values.actionType}
                                        onChange={(val) =>
                                            setFieldValue("actionType", val)
                                        }
                                        placeholder="Select Action Type"
                                        isClearable
                                        mb={4}
                                    />

                                    <Flex direction="column" gap={4}>
                                        {selectedActionFields.map(field => (
                                            <ActionFieldRenderer
                                                key={field.key}
                                                field={field}
                                                value={values[field.key]}
                                                setFieldValue={setFieldValue}
                                                getKey={getKey}
                                                dynamicOptions={dynamicOptions}
                                                loadingFields={loadingFields}
                                                fetchDynamicOptions={fetchDynamicOptions}
                                            />
                                        ))}
                                    </Flex>
                                </>
                            ),
                        },
                        {
                            value: "configure",
                            label: "Configure",
                            content: <Text fontSize="sm">Configure step</Text>,
                        },
                        {
                            value: "test",
                            label: "Test",
                            content: (
                                <>
                                    <Button
                                        mb={4}
                                        onClick={() =>
                                            dispatch(
                                                workFLowSingeNodeExction({
                                                    workflow_hash: singleData?.version?.hash,
                                                    node_key: node?.id,
                                                    input: values,
                                                })
                                            )
                                        }
                                    >
                                        Run test
                                    </Button>
                                    <Code w="100%">Output</Code>
                                </>
                            ),
                        },
                    ]}
                />
            )}
        </ZAPDrawer>
    );
}
