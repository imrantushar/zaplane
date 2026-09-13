import ZAPDrawer from "@ZAPComponents/Drawer";
import { integrations } from "@ZAPUtils/helper";
import Teaser from "@ZAPComponents/Teaser";
import { useFormikContext } from "formik";
import { useEffect, useMemo, useState, useCallback } from "react";
import { useDispatch } from "react-redux";
import ZAPTab from "@ZAPComponents/Tab";
import { __ } from "@wordpress/i18n";
import { outlineBtn, primaryBtn } from "../../../../../../../assets/scss/chakra/recipe";
import { useActionDrawer } from "@ZAPHooks/useActionDrawer/useActionDrawer";
import { TOOLS } from "@ZAPHooks/useActionDrawer/helper";
import { buildContinuePayload } from "../ActionDrawer/helper";
import SelectTab from "../ActionDrawer/SelectTab/SelectTab";


import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { useDynamicFields } from "@ZAPHooks/useActionDrawer/useDynamicFields";
import { mapEdgesForBackend, mapNodesForBackend } from "../helper";
import { conditionVariables } from "@ZAPRedux/Slices/workFlowSlice/actions/conditonVariales";
import './styles.scss';
import Search from "@ZAPComponents/Search";
import DrawerModeList from "@ZAPComponents/SearchableDrawerList/DrawerItemList/DrawerModeList";
import DrawerItemList from "@ZAPComponents/SearchableDrawerList/DrawerItemList";
import ActionFieldRenderer from "../ActionDrawer/ActionFieldRenderer/ActionFieldRenderer";
import TestRun from "../ActionDrawer/TestRun/TestRun";
import TriggerFieldMap from "./TriggerFieldMap/TriggerFieldMap";
import DrawerSearchList from "@ZAPComponents/SearchableDrawerList/DrawerSearchList/DrawerSearchList";

// Pragmatic email check for UI validation (not RFC-exhaustive): non-empty local
// part, "@", and a dotted domain, with no spaces.
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Validate a literal recipient value: a single email, or a comma/semicolon
// separated list where every non-empty part is a valid email.
const isValidEmailList = (value) => {
  const parts = String(value)
    .split(/[,;]/)
    .map((p) => p.trim())
    .filter((p) => p !== "");
  if (parts.length === 0) return false;
  return parts.every((p) => EMAIL_RE.test(p));
};

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
    node,
    edge,
    port
  } = context;
  const dispatch = useDispatch();
  const {
    values,
    setFieldValue,
    setFieldError,
    errors,
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
    open, node, source, port, setFieldValue, isTrigger, values, resetForm, onClose,
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
 
  // Validate required visibleFields using Formik state
  const validateRequiredFields = () => {
    let hasError = false;
    visibleFields.forEach((field) => {
      if (field.type === 'condition_group') {
        const groups = values?.[field.key] || [];
        groups.forEach((group, gIndex) => {
          (group || []).forEach((rule, rIndex) => {
            (field.fields || []).forEach((rf) => {
              if (rf.type === 'select') return;
              const val = rule[rf.key];
              if (!val || String(val).trim() === '') {
                setFieldError(`${field.key}[${gIndex}][${rIndex}].${rf.key}`, __('This field is required', 'zaplane'));
                hasError = true;
              }
            });
          });
        });
        return;
      }
      const val = values?.[field.key];
      const isEmpty =
        val === undefined ||
        val === null ||
        val === "" ||
        (Array.isArray(val) && val.length === 0);

      if (field.required && isEmpty) {
        setFieldError(field.key, __("This field is required", "zaplane"));
        hasError = true;
        return;
      }

      // Format checks apply to present values only. Skip anything that uses
      // dynamic data ({{...}} tokens) — those resolve at run time, so we can't
      // (and shouldn't) validate their literal shape here.
      if (!isEmpty && typeof val === "string") {
        const usesDynamicData = /\{\{[\s\S]*?\}\}/.test(val);
        if (!usesDynamicData && field.type === "email" && !isValidEmailList(val)) {
          setFieldError(field.key, __("Enter a valid email address", "zaplane"));
          hasError = true;
        }
      }
    });
    return hasError;
  };

  // Apps that require a connection (e.g. AI) must have one picked before moving
  // past the Select step. Returns true when a required connection is missing.
  const validateConnection = () => {
    if (selectedIntegration?.requires_connection === true && !values?.connection_id) {
      setFieldError("connection_id", __("A connection is required", "zaplane"));
      return true;
    }
    return false;
  };

  const handleContinue = () => {
    if (step === "select") {
      if (validateConnection()) return;
      return setStep("configure");
    }
    if (step === "configure") {
      if (validateRequiredFields()) return;

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

  // Load the "@" dynamic variables available to this node/action. The picker's
  // fields come from upstream nodes in the graph. The backend excludes the
  // *target* node from its own variables, so:
  //  - Opening an existing node  → target = that node (its ancestors show).
  //  - Adding a NEW action       → the new node doesn't exist yet, so we splice
  //    in a temporary target wired right after the node/edge it's being added
  //    from. That makes the node it's added after (e.g. the trigger) count as
  //    upstream — otherwise a fresh action after the trigger showed nothing.
  // We wait for the graph to load (nodes.length) so we never fetch an empty
  // graph, which would return empty data and clobber a good result.
  useEffect(() => {
    if (!open || !nodes?.length) return;

    const baseNodes = mapNodesForBackend(nodes);
    const baseEdges = mapEdgesForBackend(edges);
    const TEMP_TARGET = 999999;

    let targetKey = null;
    let graphNodes = baseNodes;
    let graphEdges = baseEdges;

    if (source === "node" && node?.id) {
      targetKey = node.id;
    } else if (source === "add" && node?.id) {
      targetKey = TEMP_TARGET;
      graphNodes = [...baseNodes, { id: TEMP_TARGET, type: "action", data: {} }];
      graphEdges = [...baseEdges, { source: node.id, target: TEMP_TARGET }];
    } else if (source === "edge" && edge?.source != null) {
      targetKey = TEMP_TARGET;
      graphNodes = [...baseNodes, { id: TEMP_TARGET, type: "action", data: {} }];
      graphEdges = [...baseEdges, { source: edge.source, target: TEMP_TARGET }];
    }

    if (targetKey == null) return;

    dispatch(conditionVariables({
      workflow_id: workFlow?.workflow?.id,
      workflow_hash: workFlow?.version?.hash,
      workflow_version_id: workFlow?.version?.id,
      target_node_key: targetKey,
      graph: { nodes: graphNodes, edges: graphEdges },
    }));
  }, [
    open,
    source,
    node?.id,
    edge?.id,
    workFlow?.workflow?.id,
    workFlow?.version?.id,
    workFlow?.version?.hash,
    nodes?.length,
    edges?.length,
  ]);
  return <ZAPDrawer open={open} isFullscreen={isFullscreen} onClose={resetAll}
    arrowClose={['tools', 'app'].includes(mode)}
    maxWidth={['filter', 'if'].includes(values?.actionType) ? 'max-w-[700px]' : 'max-w-[500px]'}
    arrowOnClick={() => {
      setSelectedItem(null);
      setMode(null);
      setStep("select");
      setFieldValue("actionType", "");
    }}
    closeOnOverlayClick
    title={!mode ? (isTrigger ? __('Add Trigger', 'zaplane') : __('Add Action', 'zaplane')) : selectedItem?.name || __('App', 'zaplane')} placement="end"
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

      {!mode && !search && !selectedItem && <DrawerModeList setMode={setMode} setSelectedItem={setSelectedItem} isTrigger={isTrigger} source={source} TOOLS={TOOLS} port={port} />}

      {mode && !selectedItem && !search && <DrawerItemList list={list} setSelectedItem={(item) => setSelectedItem(mode === "tools" ? { ...item, mode: "tools" } : item)} setMode={setMode} />}

      {/* The picked node's module may not be switched on. Nodes stay usable
          either way, so this is the only place that says so — and it offers to
          switch it on without leaving the half-built workflow. */}
      {selectedItem && <Teaser app={selectedItem?.id} />}

      {selectedItem && <ZAPTab value={step} onChange={values?.actionType ? (newStep) => {
        if (step === "select" && newStep !== "select") {
          if (validateConnection()) return;
        }
        if (step === "configure" && newStep === "test") {
          if (validateRequiredFields()) return;
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
            {selectedActionFields?.length > 0 ? visibleFields.map(field => <ActionFieldRenderer key={field.key} field={field} value={values?.[field.key]} setFieldValue={setFieldValue} getKey={getKey} dynamicOptions={dynamicOptions} loadingFields={loadingFields} fetchDynamicOptions={fetchDynamicOptions} nodeId={node?.id} workFlow={workFlow} nodes={nodes} edges={edges} />) : <ZAPLabel label={__("No configuration required for this action.", "zaplane")} type="simple" />}
          </div>
          {isTrigger && (
            <TriggerFieldMap
              node={node}
              nodes={nodes}
              workFlow={workFlow}
              app={selectedItem?.id}
              event={values?.actionType}
              config={visibleFields.reduce((acc, f) => ({ ...acc, [f.key]: values?.[f.key] }), {})}
              value={values?.field_map}
              onChange={fieldMap => setFieldValue("field_map", fieldMap)}
            />
          )}
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
