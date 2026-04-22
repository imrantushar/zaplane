import ZAPDrawer from "@ZAPComponents/Drawer";
import { integrations } from "@ZAPUtils/helper";
import { useFormikContext } from "formik";
import { useEffect, useMemo, useState, useCallback } from "react";
import { useDispatch } from "react-redux";
import ZAPTab from "@ZAPComponents/Tab";
import { __ } from "@wordpress/i18n";
import { outlineBtn, primaryBtn } from "../../../../../../../assets/scss/chakra/recipe";
import { useActionDrawer } from "@ZAPHooks/useActionDrawer/useActionDrawer";
import { TOOLS } from "@ZAPHooks/useActionDrawer/helper";
import { buildContinuePayload } from "./helper";
import SelectTab from "./SelectTab/SelectTab";


import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { useDynamicFields } from "@ZAPHooks/useActionDrawer/useDynamicFields";
import { mapEdgesForBackend, mapNodesForBackend } from "../helper";
import { conditionVariables } from "@ZAPRedux/Slices/workFlowSlice/actions/conditonVariales";
import './styles.scss';
import Search from "@ZAPComponents/Search";
import DrawerModeList from "@ZAPComponents/SearchableDrawerList/DrawerItemList/DrawerModeList";
import DrawerItemList from "@ZAPComponents/SearchableDrawerList/DrawerItemList";
import ActionFieldRenderer from "./ActionFieldRenderer/ActionFieldRenderer";
import TestRun from "./TestRun/TestRun";
import DrawerSearchList from "@ZAPComponents/SearchableDrawerList/DrawerSearchList/DrawerSearchList";

const ActionDrawer = ({
  open,
  context,
  onClose,
  updateNodeData,
  handleAddAction,
  workFlow,
  isFullscreen,
  nodes,
  edges
}) => {
  const {
    source,
    node
  } = context;
  const dispatch = useDispatch();
  const {
    values,
    setFieldValue,
    resetForm
  } = useFormikContext();
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

  const [configErrors, setConfigErrors] = useState({});

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
      setConfigErrors({});
      return setStep("configure");
    }
    if (step === "configure") {
      // Validate required fields
      const errors = {};
      visibleFields.forEach((field) => {
        if (!field.required) return;
        const val = values?.[field.key];
        const isEmpty =
          val === undefined ||
          val === null ||
          val === "" ||
          (Array.isArray(val) && val.length === 0);
        if (isEmpty) errors[field.key] = __("This field is required", "zaplane");
      });

      if (Object.keys(errors).length > 0) {
        setConfigErrors(errors);
        return;
      }

      setConfigErrors({});
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
  return <ZAPDrawer open={open} isFullscreen={isFullscreen} onClose={resetAll}
    arrowClose={['tools', 'app'].includes(mode)}
    maxWidth={['filter', 'if'].includes(values?.actionType) ? 'max-w-[700px]' : 'max-w-[500px]'}
    arrowOnClick={() => {
      setSelectedItem(null);
      setMode(null);
      setStep("select");
      setFieldValue("actionType", "");
    }}
    // closeOnOverlayClick
    title={!mode ? "Add Action" : selectedItem?.name || __('App', 'zaplane')} placement="end"
    // size={["filter", "condition"].includes(values?.actionType) ? "xl" : "md"}
    footer={<div className="flex items-center justify-end gap-3">
      <button
       style={outlineBtn}
        onClick={resetAll}
      >
        {__("Cancel", "zaplane")}
      </button>
      <button
        style={primaryBtn}
        disabled={!values.actionType}
        onClick={handleContinue}
      >
        {step === 'test' ? __('Save', 'zaplane') : __('Continue', 'zaplane')}
      </button>
    </div>}>

    <div className="flex flex-col gap-[16px]">
      {!selectedItem && (
        <>
          <Search placeholder={__("Search apps or tools...", "zaplane")} defaultValue={search} onSearchHandler={value => setSearch(value)} />
          {search && <DrawerSearchList searchList={searchList} setMode={setMode} setSelectedItem={setSelectedItem} setSearch={setSearch} />}
        </>
      )}

      {!mode && !search && !selectedItem && <DrawerModeList setMode={setMode} setSelectedItem={setSelectedItem} isTrigger={isTrigger} source={source} TOOLS={TOOLS} />}

      {mode && !selectedItem && !search && <DrawerItemList list={list} setSelectedItem={setSelectedItem} setMode={setMode} />}

      {selectedItem && <ZAPTab value={step} onChange={values?.actionType ? (newStep) => {
        // Validate required fields before jumping to "test" via tab click
        if (step === "configure" && newStep === "test") {
          const errors = {};
          visibleFields.forEach((field) => {
            if (!field.required) return;
            const val = values?.[field.key];
            const isEmpty = val === undefined || val === null || val === "" || (Array.isArray(val) && val.length === 0);
            if (isEmpty) errors[field.key] = __("This field is required", "zaplane");
          });
          if (Object.keys(errors).length > 0) {
            setConfigErrors(errors);
            return;
          }
          setConfigErrors({});
        }
        setStep(newStep);
      } : undefined} tabs={[{
        value: "select",
        label: "Select",
        content: <SelectTab isTrigger={isTrigger} actionOptions={actionOptions} selectedActionFields={selectedActionFields} values={values} setFieldValue={setFieldValue} dynamicOptions={dynamicOptions} loadingFields={loadingFields} fetchDynamicOptions={fetchDynamicOptions} getKey={getKey} node={node} workFlow={workFlow} selectedIntegration={selectedIntegration} appSlug={selectedItem?.id} />
      }, {
        value: "configure",
        label: "Configure",
        content: <>
          <div className="action-drowar-lists flex flex-col gap-4">
            {selectedActionFields?.length > 0 ? selectedActionFields.map(field => <ActionFieldRenderer key={field.key} field={field} value={values?.[field.key]} setFieldValue={(k, v) => { setFieldValue(k, v); if (configErrors[k]) setConfigErrors(prev => ({ ...prev, [k]: undefined })); }} getKey={getKey} dynamicOptions={dynamicOptions} loadingFields={loadingFields} fetchDynamicOptions={fetchDynamicOptions} nodeId={node?.id} workFlow={workFlow} nodes={nodes} edges={edges} error={configErrors[field.key]} />) : <ZAPLabel label={__("No configuration required for this action.", "zaplane")} type="simple" />}
          </div>
        </>
      }, {
        value: "test",
        label: "Test",
        content: <TestRun nodes={nodes} edges={edges} source={source} node={node} workFlow={workFlow} values={values} />
      }]} />}
    </div>
  </ZAPDrawer>;
};
export default ActionDrawer;
