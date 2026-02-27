import { Button, Flex, HStack, Input, } from "@chakra-ui/react";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { integrations } from "@ZAPUtils/helper";
import { useFormikContext } from "formik";
import { useEffect, useMemo, useState } from "react";
import { useDispatch } from "react-redux";
import ZAPTab from "@ZAPComponents/Tab";
import { __, sprintf } from "@wordpress/i18n";
import { primaryBtn } from "../../../../../../../assets/scss/chakra/recipe";
import { useActionDrawer } from "@ZAPHooks/useActionDrawer/useActionDrawer";
import { TOOLS } from "@ZAPHooks/useActionDrawer/helper";
import { getIntegration } from "./helper";
import { fetchDynamic } from "@ZAPRedux/Slices/workFlowSlice/helper";
import SelectTab from "./SelectTab/SelectTab";
import TestRun from "./TestRun/TestRun";
import DrawerSearchList from "./DrawerSearchList/DrawerSearchList";
import DrawerModeList from "./DrawerItemList/DrawerModeList";
import DrawerItemList from "./DrawerItemList";
import ActionFieldRenderer from "./ActionFieldRenderer/ActionFieldRenderer";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";

const ActionDrawer = ({ open, context, onClose, updateNodeData, createActionNode, workFlow, isFullscreen,nodes,edges }) => {
  const { source, node } = context;
  const dispatch = useDispatch();
  const { values, setFieldValue, resetForm } = useFormikContext();
  const [step, setStep] = useState("select");
  const [dynamicOptions, setDynamicOptions] = useState({});
  const [loadingFields, setLoadingFields] = useState({});
  const isTrigger = node?.data?.action === "trigger" && source === "node";
  const [showWarning, setShowWarning] = useState(false);

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
    setShowWarning(false)
  };

  const handleContinue = () => {
    if (step === "select") {
      return setStep("configure");
    }

    if (step === "configure") {

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
      if (context?.source !== "node") {
        createActionNode(payload);
      }
      else {
        updateNodeData(payload);
      }

      return setStep("test");
    }

    if (step === "test") {
      resetAll();
    }
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
      {search &&
        <DrawerSearchList
          searchList={searchList}
          setMode={setMode}
          setSelectedItem={setSelectedItem}
          setSearch={setSearch}
        />}

      {!mode && !search && !selectedItem && (
        <DrawerModeList
          setMode={setMode}
          setSelectedItem={setSelectedItem}
          isTrigger={isTrigger}
          source={source}
          TOOLS={TOOLS}
        />
      )}

      {mode && !selectedItem && !search && (
        <DrawerItemList
          list={list}
          setSelectedItem={setSelectedItem}
          setMode={setMode}
        />
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
                <SelectTab
                  isTrigger={isTrigger}
                  actionOptions={actionOptions}
                  selectedActionFields={selectedActionFields}
                  values={values}
                  setFieldValue={setFieldValue}
                  dynamicOptions={dynamicOptions}
                  loadingFields={loadingFields}
                  fetchDynamicOptions={fetchDynamicOptions}
                  getKey={getKey}
                  node={node}
                  workFlow={workFlow}
                />
              )
            },
            {
              value: "configure", label: "Configure", content: <>
                <Flex direction="column" gap={4}>
                  {selectedActionFields?.length > 0 ? (
                    selectedActionFields.map((field) => (
                      <ActionFieldRenderer
                        key={field.key}
                        field={field}
                        value={values?.[field.key]}
                        setFieldValue={setFieldValue}
                        getKey={getKey}
                        dynamicOptions={dynamicOptions}
                        loadingFields={loadingFields}
                        fetchDynamicOptions={fetchDynamicOptions}
                        nodeId={node?.id}
                        workFlow={workFlow}
                        nodes={nodes}
                        edges={edges}
                      />
                    ))
                  ) : (
                    <ZAPLabel label={__("No configuration required for this action.", "zaplane")} type="simple" />
                  )}
                </Flex>
              </>
            },
            {
              value: "test",
              label: "Test",
              content: (
                <TestRun
                 nodes={nodes}
                 edges={edges}
                  source={source}
                  node={node}
                  workFlow={workFlow}
                  values={values}
                />
              )
            }
          ]}
        />
      )}
    </ZAPDrawer>
  );
}
export default ActionDrawer