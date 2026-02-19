import {
  Button,
  VStack,
  Text,
  HStack,
  Input,
  Flex,
  Code
} from "@chakra-ui/react";
import ZAPDrawer from "@ZAPComponents/Drawer";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { integrations } from "@ZAPUtils/helper";
import { useFormikContext } from "formik";
import { useEffect, useMemo, useState } from "react";
import { useDispatch } from "react-redux";
import ZAPTab from "@ZAPComponents/Tab";
import { IoIosArrowForward } from "react-icons/io";
import { __, sprintf } from "@wordpress/i18n";
import { primaryBtn } from "../../../../../../../assets/scss/chakra/recipe";
import { useActionDrawer } from "@ZAPHooks/useActionDrawer/useActionDrawer";
import { TOOLS } from "@ZAPHooks/useActionDrawer/helper";
import { getActionHook, getIntegration } from "./helper";
import TestDetails from "./TestDetails/TestDetails";
import ActionFieldRenderer from "./ActionFieldRenderer/ActionFieldRenderer";
import { workFLowSingeNodeExction } from "@ZAPRedux/Slices/workFlowSlice/actions/workflowExctions";
import { fetchDynamic } from "@ZAPRedux/Slices/workFlowSlice/helper";


export default function ActionDrawer({ open, context, onClose, updateNodeData, createActionNode, workFlow, isFullscreen }) {
  const { source, node } = context;
  const dispatch = useDispatch();
  const { values, setFieldValue, resetForm } = useFormikContext();
  const [step, setStep] = useState("select");
  const [dynamicOptions, setDynamicOptions] = useState({});
  const [loadingFields, setLoadingFields] = useState({});
  const isTrigger = node?.data?.action === "trigger" && source === "node";

  const { mode, setMode, selectedItem, setSelectedItem, search, setSearch, list, searchList } =
    useActionDrawer(open, node, source, setFieldValue, isTrigger);


  // Auto-set actionType if only one tool action

  useEffect(() => {
    if (mode !== "tools" || !selectedItem) return;
    const tool = integrations.tools?.[selectedItem.id];
    const actions = Object.values(tool?.actions || {});
    if (actions.length === 1) setFieldValue("actionType", actions[0].key);
  }, [mode, selectedItem, setFieldValue]);

  //Generate action options for the selected item

  const actionOptions = useMemo(() => {
    const integration = getIntegration(mode, selectedItem);
    if (!integration) return [];
    const list = mode === "tools"
      ? Object.values(integration.actions || {})
      : isTrigger
        ? Object.values(integration.triggers || {})
        : Object.values(integration.actions || {});
    return list.map(i => ({ label: i.label, value: i.key, hook: i.hook }));
  }, [mode, selectedItem, isTrigger]);

  //Get schema fields for the selected action

  const selectedActionFields = useMemo(() => {
    const integration = getIntegration(mode, selectedItem);
    if (!integration || !values?.actionType) return [];
    if (mode === "tools") return integration.actions?.[values.actionType]?.schema || [];
    if (isTrigger) return integration.triggers?.[values.actionType]?.schema || [];
    return integration.actions?.[values.actionType]?.schema || [];
  }, [mode, selectedItem, values?.actionType, isTrigger]);

  const getKey = (field) => `${mode}:${selectedItem?.id}:${field.key}`;

  //Generate dynamic keys and fetch dynamic options

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

  const resetAll = () => {
    setMode(null);
    setStep("select");
    setSelectedItem(null);
    setSearch("");
    resetForm();
    onClose();
  };

  const handleContinue = () => {
    if (step === "select") return setStep("test");
    // if (step === "configure") return setStep("test");

    const payload = {
      app: selectedItem.name,
      name: selectedItem.name,
      event: values.actionType,
      hook: values.hook,
      config: selectedActionFields.reduce((acc, f) => {
        acc[f.key] = values[f.key];
        return acc;
      }, {}),
    };
    context?.source === "node" ? updateNodeData(payload) : createActionNode(payload);
    resetAll();
  };
  return (
    <ZAPDrawer
      open={open}
      isFullscreen={isFullscreen}
      onClose={resetAll}
      // closeOnOverlayClick
      title={!mode ? "Add Action" : selectedItem?.name || __('App', 'zaplane')}
      placement="end"
      size={["filter", "condition"].includes(values?.actionType) ? "xl" : "md"}
      footer={
        <HStack justify="space-between">
          <Button variant="ghost" onClick={resetAll}>{__("Cancel", "zaplane")}</Button>
          <Button {...primaryBtn}
            disabled={!values.actionType}
            onClick={handleContinue}>{step === 'test' ? __('Submit', 'zaplane') : __('Continue', 'zaplane')}
          </Button>
        </HStack>
      }
    >
      <Input placeholder={__("Search apps or tools...", "zaplane")} value={search} onChange={e => setSearch(e.target.value)} />

      {search && (
        <VStack spacing={2} align="stretch">
          {searchList.map(item => (
            <Button
              key={`${item.type}-${item.id}`}
              justifyContent="space-between"
              onClick={() => {
                setMode(item.type);
                setSelectedItem(item);
                setSearch("");
              }}
              background="var(--zaplane-background)"
              _hover={{ bg: "var(--zaplane-body-background)" }}
            >
              <Text className="zaplane-label">{sprintf(__("%s", "zaplane"), item.name)}</Text>
              <Text fontSize="xs" className="zaplane-label"> {item.type === 'tools' ? __('Tool', 'zaplane') : __('App', 'zaplane')}</Text>
            </Button>
          ))}
        </VStack>
      )}

      {!mode && !search && !selectedItem && (
        <VStack spacing={4}>
          <Button w="100%" background="var(--zaplane-background)" color="var(--zaplane-font-color)"
            justifyContent="space-between" _hover={{ bg: "var(--zaplane-body-background)", "& svg": { transform: "translateX(4px)" } }}
            onClick={() => setMode("app")}
          >
            <span>{__("Apps", "zaplane")}</span>
            <IoIosArrowForward />
          </Button>
          {(!isTrigger || source === "add") && TOOLS.map(tool => (
            <Button key={tool.id} background="var(--zaplane-background)" color="var(--zaplane-font-color)"
              justifyContent="left" w="100%" _hover={{ bg: "var(--zaplane-body-background)" }}
              onClick={() => {
                setMode("tools");
                setSelectedItem(tool);
              }}
            >
              {sprintf(__("%s", "zaplane"), tool.name)}
            </Button>
          ))}
        </VStack>
      )}

      {mode && !selectedItem && !search && (
        <VStack>
          {list.map(item => (
            <Button key={item.id} w="100%" background="var(--zaplane-background)" color="var(--zaplane-font-color)"
              justifyContent="left" _hover={{ bg: "var(--zaplane-body-background)" }}
              onClick={() => setSelectedItem(item)}
            >
              {sprintf(__("%s", "zaplane"), item.name)}
            </Button>
          ))}
          <Button size="sm" variant="ghost" onClick={() => setMode(null)}>{__('Back', 'zaplane')}</Button>
        </VStack>
      )}

      {selectedItem && (
        <ZAPTab
          value={step}
          onChange={values?.actionType && setStep}
          tabs={[
            {
              value: "select",
              label: "Select",
              content: (
                <>
                  <ZAPSelect
                    label={
                      isTrigger
                        ? __('Trigger Type', 'zaplane')
                        : __('Action Type', 'zaplane')
                    }
                    options={actionOptions}
                    value={values.actionType}
                    onChange={val => {
                      setFieldValue("actionType", val?.value)
                      setFieldValue(
                        "hook",
                        val?.hook
                      );
                    }}
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
                        nodeId={node?.id}
                        workFlow={workFlow}
                      />
                    ))}
                  </Flex>
                </>
              )
            },
            // { value: "configure", label: "Configure", content: <Text fontSize="sm">{__("Configure step", "zaplane")}</Text> },
            {
              value: "test",
              label: "Test",
              content: (
                <>
                  <Button mb={4} 
                  {...primaryBtn}
                  onClick={() =>
                    dispatch(workFLowSingeNodeExction({
                      workflow_hash: workFlow?.version?.hash,
                      node_key: node?.id,
                      input: values,
                    }))
                  }>
                    {__("Test Action", "zaplane")}
                  </Button>
                  <TestDetails id={node?.id} workFlow={workFlow} />
                </>
              )
            }
          ]}
        />
      )}
    </ZAPDrawer>
  );
}
