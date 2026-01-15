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
import LabeledInput from "@ZAPComponents/LabeledInput";
import ZAPLabeledSelect from "@ZAPComponents/LabeledSelect";
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

    const isTrigger =
    node?.data?.action === "trigger" && source === "node";

    useEffect(() => {
        if (!open || !node?.data || source === "add") return;
        const nodeData = node.data;
        let detectedMode = null;
        let detectedItem = null;
        detectedItem = TOOLS.find(
            t => t.name === nodeData.app || t.id === nodeData.app
        );

        if (detectedItem) {
            detectedMode = "tools";
        } else {
            detectedItem = APPS.find(
                a => a.name === nodeData.app || a.id === nodeData.app
            );
            detectedMode = "app";
        }

        //set state
        setMode(detectedMode);
        setSelectedItem(detectedItem || null);
        setStep("select");

        //action type
        if (nodeData.event) {
            setFieldValue("actionType", nodeData.event);
        }
        //default config
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
        resetForm();
        onClose();
    };

    const LIST =
        mode === "app"
            ? APPS
            : mode === "tools" 
                ? TOOLS
                : [];

    const getIntegration = () => {
        if (!selectedItem?.id) return null;
        return mode === "tools"
            ? integrations.tools?.[selectedItem.id]
            : integrations.apps?.[selectedItem.id];
    };

    // toll action auto seleted
    useEffect(() => {
        if (mode !== "tools" || !selectedItem) return;

        const tool = integrations.tools?.[selectedItem.id];
        const actions = Object.values(tool?.actions || {});

        if (actions.length === 1) {
            setFieldValue("actionType", actions[0].key);
        }
    }, [mode, selectedItem]);

    //    action option
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

    // shema shows
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

    // dainamic filed
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

    return (
        <ZAPDrawer
            open={open}
            onClose={resetAll}
            title={!mode ? "Choose Type" : selectedItem?.name}
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
            {!mode && (
                <VStack spacing={4}>
                    <Button w="100%" justifyContent="left" onClick={() => setMode("app")}>
                        Apps
                    </Button>

                    {(node?.data?.action !== "trigger" || source === "add") && TOOLS.map(tool => (
                        <Button
                            key={tool.id}
                            w="100%"
                            justifyContent="space-between"
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

            {mode && !selectedItem && (
                <VStack>
                    {LIST.map(item => (
                        <Button
                            justifyContent="left"
                            key={item.id}
                            w="100%"
                            onClick={() => setSelectedItem(item)}
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
                                    <ZAPLabeledSelect
                                        label={isTrigger?"Trigger Type":"Action Type"
                                        }
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
                                            <Box key={field.key}>
                                                <ActionFieldRenderer
                                                    field={field}
                                                    value={values[field.key]}
                                                    setFieldValue={setFieldValue}
                                                    getKey={getKey}
                                                    dynamicOptions={dynamicOptions}
                                                    loadingFields={loadingFields}
                                                    fetchDynamicOptions={fetchDynamicOptions}
                                                />
                                            </Box>
                                        ))}
                                    </Flex>
                                </>
                            ),
                        },
                        {
                            value: "configure",
                            label: "Configure",
                            content: (
                                <Text fontSize="sm" color="gray.500">
                                    Configure step (future use)
                                </Text>
                            ),
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
                                                    workflow_hash:
                                                        singleData?.version?.hash,
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
