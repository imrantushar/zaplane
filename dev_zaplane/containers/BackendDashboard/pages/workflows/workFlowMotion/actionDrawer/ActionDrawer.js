import { Button, Flex, HStack } from "@chakra-ui/react";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { useFormikContext } from "formik";
import { useEffect } from "react";
import { useDispatch } from "react-redux";
import ZAPTab from "@ZAPComponents/Tab";
import { __ } from "@wordpress/i18n";
import { primaryBtn } from "../../../../../../../assets/scss/chakra/recipe";
import { useActionDrawer } from "@ZAPHooks/useActionDrawer/useActionDrawer";
import { TOOLS } from "@ZAPHooks/useActionDrawer/helper";
import { buildContinuePayload } from "./helper";
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
  const isTrigger = node?.data?.action === "trigger" && source === "node";

  const {
    mode, setMode,
    selectedItem, setSelectedItem,
    search, setSearch,
    step, setStep,
    list, searchList,
    selectedIntegration,
    actionOptions,
    selectedActionFields,
    visibleFields,
    resetAll,
  } = useActionDrawer({
    open, node, source, setFieldValue, isTrigger, values, resetForm, onClose,
  });

  // NOW call dynamic hook
  const {
    dynamicOptions,
    loadingFields,
    fetchDynamicOptions,
    getKey,
  } = useDynamicFields({
    selectedItem,
    mode,
    selectedActionFields: visibleFields,
    values,
  });

  const handleContinue = () => {
    if (step === "select") {
      return setStep("configure");
    }
    if (step === "configure") {
      const payload = buildContinuePayload(selectedItem, values, visibleFields);

      if (context?.source !== "node") {
        handleAddAction(payload);
      } else {
        updateNodeData(payload);
      }

      return setStep("test");
    }

    if (step === "test") {
      resetAll();
    }
  };

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
      footer={
        <HStack justify="space-between">
          <Button variant="outline" onClick={resetAll}>{__("Cancel", "zaplane")}</Button>
          <Button {...primaryBtn}
            disabled={!values.actionType}
            onClick={handleContinue}>{step === 'test' ? __('Save', 'zaplane') : __('Continue', 'zaplane')}
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
                  {visibleFields?.length > 0 ? (
                    visibleFields.map((field) => (
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