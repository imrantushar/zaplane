import {  Button, Flex, HStack,  } from "@chakra-ui/react";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { integrations } from "@ZAPUtils/helper";
import { useFormikContext } from "formik";
import { useEffect, useMemo, useState } from "react";
import { useDispatch } from "react-redux";
import ZAPTab from "@ZAPComponents/Tab";
import { __ } from "@wordpress/i18n";
import { primaryBtn } from "../../../../../../../assets/scss/chakra/recipe";
import { useActionDrawer } from "@ZAPHooks/useActionDrawer/useActionDrawer";
import { TOOLS } from "@ZAPHooks/useActionDrawer/helper";
import { getIntegration } from "./helper";
import SelectTab from "./SelectTab/SelectTab";
import TestRun from "./TestRun/TestRun";
import DrawerSearchList from "./DrawerSearchList/DrawerSearchList";
import DrawerModeList from "./DrawerItemList/DrawerModeList";
import DrawerItemList from "./DrawerItemList";
import ActionFieldRenderer from "./ActionFieldRenderer/ActionFieldRenderer";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { useDynamicFields } from "@ZAPHooks/useActionDrawer/useDynamicFields";
import { mapEdgesForBackend, mapNodesForBackend } from "../helper";
import { conditionVariables } from "@ZAPRedux/Slices/workFlowSlice/actions/conditonVariales";
import './styles.scss'
import Search from "@ZAPComponents/Search";

const ActionDrawer = ({ open, context, onClose, updateNodeData, handleAddAction, workFlow, isFullscreen, nodes, edges }) => {
  const { source, node } = context;
  const dispatch = useDispatch();
  const { values, setFieldValue, resetForm } = useFormikContext();
  const [step, setStep] = useState("select");
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

  // NOW call dynamic hook
  const {
    dynamicOptions,
    loadingFields,
    fetchDynamicOptions,
    getKey,
  } = useDynamicFields({
    selectedItem,
    mode,
    selectedActionFields,
    values,
  });

  const resetAll = () => {
    setMode(null);
    setStep("select");
    setSelectedItem(null);
    setSearch("");
    resetForm();
    onClose();
  };

  const handleContinue = () => {
    if (step === "select") {
      return setStep("configure");
    }
    if (step === "configure") {

      const payload = {
        icon: selectedItem.icon,
        app: selectedItem.id,
        name: selectedItem.name,
        event: values.actionType,
        config: selectedActionFields.reduce((acc, f) => {
          acc[f.key] = values[f.key];
          return acc;
        }, {}),
        ...(selectedItem.mode && { mode: selectedItem.mode }),
        ...(values.hook && { hook: values.hook }),
        ...(values.connection_id && { connection_id: values.connection_id }),
      };
      if (context?.source !== "node") {
        handleAddAction(payload);
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
  // seleted intregation
  const selectedIntegration = useMemo(() => {
    return getIntegration(mode, selectedItem);
  }, [mode, selectedItem]);

  // get global variable
  useEffect(() => {
    if (!node?.id || !workFlow?.version?.hash) return;
    const payload = {
      workflow_id: workFlow.workflow?.id,
      workflow_hash: workFlow.version?.hash,
      workflow_version_id: workFlow.version?.id,
      target_node_key: node?.id,
      graph: {
        nodes: mapNodesForBackend(nodes),
        edges: mapEdgesForBackend(edges),
      },
    };

    dispatch(conditionVariables(payload));
  }, [node?.id]);
  
  return (
    <ZAPDrawer
      open={open}
      isFullscreen={isFullscreen}
      onClose={resetAll}
      arrowClose={['tools', 'app'].includes(mode)}
      maxWidth='500px' {...(['filter', 'if'].includes(values?.actionType) && { maxWidth: '700px' })}
      arrowOnClick={() => {
        setSelectedItem(null);
        setMode(null);
        setStep("select");
        setFieldValue("actionType", "");
      }}
      // closeOnOverlayClick
      title={!mode ? "Add Action" : selectedItem?.name || __('App', 'zaplane')}
      placement="end"
      // size={["filter", "condition"].includes(values?.actionType) ? "xl" : "md"}
      footer={
        <HStack justify="space-between">
          <Button variant="outline" onClick={resetAll}>{__("Cancel", "zaplane")}</Button>
          <Button {...primaryBtn}
            disabled={!values.actionType}
            onClick={handleContinue}>{step === 'test' ? __('Submit', 'zaplane') : __('Continue', 'zaplane')}
          </Button>
        </HStack>
      }
    >
    
        <Search
          placeholder={__("Search apps or tools...", "zaplane")}
          defaultValue={search}
          onSearchHandler={(value) => setSearch(value)}
        />
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
                  selectedIntegration={selectedIntegration}
                  appSlug={selectedItem?.id}
                />
              )
            },
            {
              value: "configure", label: "Configure", content: <>
                <Flex direction="column" className="action-drowar-lists" gap={4}>
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