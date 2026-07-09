/**
 * useActionDrawer
 * ----------------
 * A custom React hook for managing the state and behavior of an Action Drawer in a flow canvas.
 *
 * Features:
 * - Tracks the drawer mode ("app" or "tools") and selected item.
 * - Manages search input and generates filtered search results.
 * - Pre-populates form values based on the selected node (app, event, config).
 * - Auto-sets actionType when a tool has only one action.
 * - Applies default field values when fields become visible.
 * - Provides a resetAll() to close and clear the drawer.
 * - Exposes selectedIntegration as a computed value.
 *
 * Parameters:
 * @param {object} options
 * @param {boolean} options.open           - Whether the drawer is open.
 * @param {object}  options.node           - Current node data (app, event, config).
 * @param {string}  options.source         - Drawer context ("add" or "node").
 * @param {function} options.setFieldValue - Function to update form field values (e.g., Formik).
 * @param {boolean} options.isTrigger      - Whether this drawer is for a trigger action.
 * @param {object}  options.values         - Current form values.
 * @param {function} options.resetForm     - Formik resetForm function.
 * @param {function} options.onClose       - Callback to close the drawer.
 *
 * Returns:
 * @returns {object} - {
 *   mode, setMode, selectedItem, setSelectedItem, search, setSearch,
 *   list, searchList, selectedIntegration, visibleFields, resetAll, step, setStep
 * }
 */
import { useState, useMemo, useEffect } from "react";
import { APPS, TOOLS } from "./helper";
import { integrations } from "@ZAPUtils/helper";
import {
  getIntegration,
  getActionOptions,
  getSelectedActionFields,
  getVisibleFields
} from "@ZAPContainers/BackendDashboard/pages/workflows/workFlowMotion/ActionDrawer/helper";



// A tool sub-node shouldn't be a trigger, the agent itself, memory/model (those
// have their own handles), or a control-flow node — they aren't callable tools.
const SUB_TOOL_BLOCKLIST = new Set([
  "ai-agent", "memory", "ai", "sticky_note", "manual",
  "condition", "filter", "router", "iterator", "repeater", "delay",
  "human_approval", "schedule",
]);

const subInputAllows = (portId, itemId) => {
  if (portId === "ai_memory") return itemId === "memory";
  if (portId === "ai_model") return itemId === "ai";
  if (portId === "ai_tool") return !SUB_TOOL_BLOCKLIST.has(itemId);
  return true;
};

export const useActionDrawer = ({
  open, node, source, port, setFieldValue, isTrigger, values, resetForm, onClose,
}) => {
  const [mode, setMode] = useState(null);
  const isSubInput = port?.type === "target";
  const [selectedItem, setSelectedItem] = useState(null);
  const [search, setSearch] = useState("");
  const [step, setStep] = useState("select");

  // ── Reset on "add" source / pre-populate on "node" source ──
  useEffect(() => {
    if (!open) return;

    if (source === "add") {
      setMode(null);
      setSelectedItem(null);
      setSearch("");
      return;
    }

    if (!node?.data) return;

    const detectedItem = APPS.concat(TOOLS).find(
      i => i.name === node.data.app || i.id === node.data.app
    );
    if (detectedItem) {
      setMode(TOOLS.includes(detectedItem) ? "tools" : "app");
      setSelectedItem(detectedItem);
    }

    if (node.data.event) setFieldValue("actionType", node.data.event);
    if (node.data.connection_id) setFieldValue("connection_id", node.data.connection_id);
    if (node.data.config) {
      Object.entries(node.data.config).forEach(([k, v]) => setFieldValue(k, v));
    }
  }, [open, node?.data, values.nodeClick, source]);

  // ── Reset step + form when opened via "add" ──
  useEffect(() => {
    if (open && source === "add") {
      setStep("select");
      resetForm();
      setFieldValue("actionType", "");
    }
  }, [open, source]);

  // ── Preset the tab for a sub-input add so its one valid item is visible ──
  useEffect(() => {
    if (!open || source !== "add" || !isSubInput) return;
    if (port.id === "ai_memory") setMode("tools");
    else if (port.id === "ai_model") setMode("app");
    // ai_tool spans both Apps and Tools — leave the tab choice to the user.
  }, [open, source, isSubInput, port?.id]);

  // ── Auto-set actionType if the integration exposes only one item ──
  // For a trigger drawer that means a single trigger (e.g. Schedule's
  // "On a Schedule" or Webhook's "Catch Webhook"), otherwise a single action.
  // Applies to both apps and tools so a lone required option is never left
  // blank for the user to hunt for.
  useEffect(() => {
    if (!selectedItem) return;
    const integration = mode === "tools"
      ? integrations.tools?.[selectedItem.id]
      : integrations.apps?.[selectedItem.id];
    const items = Object.values((isTrigger ? integration?.triggers : integration?.actions) || {});
    if (items.length === 1) setFieldValue("actionType", items[0].key);
  }, [mode, selectedItem, isTrigger, setFieldValue]);

  // ── Derived: integration object ──
  const selectedIntegration = useMemo(
    () => getIntegration(mode, selectedItem),
    [mode, selectedItem]
  );

  // ── Derived: action options ──
  const actionOptions = useMemo(
    () => getActionOptions(mode, selectedItem, isTrigger),
    [mode, selectedItem, isTrigger]
  );

  // ── Derived: schema fields for selected action ──
  const selectedActionFields = useMemo(
    () => getSelectedActionFields(mode, selectedItem, values?.actionType, isTrigger),
    [mode, selectedItem, values?.actionType, isTrigger]
  );

  // ── Derived: visible fields (filtered by depends_on) ──
  const visibleFields = useMemo(
    () => getVisibleFields(selectedActionFields, values),
    [selectedActionFields, values]
  );

  // ── Apply default values when fields become visible ──
  useEffect(() => {
    if (!visibleFields.length) return;
    visibleFields.forEach((field) => {
      const currentValue = values?.[field.key];
      if (
        field.default !== undefined &&
        (currentValue === undefined || currentValue === "")
      ) {
        setFieldValue(field.key, field.default);
      }
    });
  }, [visibleFields, setFieldValue]);

  // ── Filtered item list ──
  const list = useMemo(() => {
    const base = mode === "app" ? APPS : mode === "tools" ? TOOLS : [];

    if (isTrigger) {
      // Look in the registry matching the current mode so tool triggers (e.g.
      // Schedule) surface under Tools, and app triggers under Apps.
      const registry = mode === "tools" ? integrations.tools : integrations.apps;
      return base.filter((item) => {
        const triggers = registry?.[item.id]?.triggers;
        if (!triggers) return false;
        if (Array.isArray(triggers)) return triggers.length > 0;
        return Object.keys(triggers).length > 0;
      });
    }

    return base.filter((item) => {
      if (isSubInput && !subInputAllows(port.id, item.id)) return false;

      const integration =
        integrations.apps?.[item.id] ||
        integrations.tools?.[item.id];

      return integration?.actions && Object.keys(integration.actions).length > 0;
    });
  }, [mode, isTrigger, isSubInput, port?.id]);

  // ── Search results ──
  const searchList = useMemo(() => {
    if (!search) return [];
    const q = search.toLowerCase();
    const hasEntries = (registry, id, kind) => {
      const items = registry?.[id]?.[kind];
      if (!items) return false;
      if (Array.isArray(items)) return items.length > 0;
      return Object.keys(items).length > 0;
    };
    // Only surface integrations that actually have a matching option, so the
    // user can never land on an empty Trigger/Action Type select (e.g. Webhook
    // has no actions, most tools have no triggers).
    const combined = isTrigger
      ? APPS.filter((item) => hasEntries(integrations.apps, item.id, "triggers")).concat(
          TOOLS.filter((item) => hasEntries(integrations.tools, item.id, "triggers"))
            .map(t => ({ ...t, type: "tools" }))
        )
      : APPS.filter((item) => hasEntries(integrations.apps, item.id, "actions")).concat(
          TOOLS.filter((item) => hasEntries(integrations.tools, item.id, "actions"))
            .map(t => ({ ...t, type: "tools" }))
        );

    return combined
      .filter(item => !isSubInput || subInputAllows(port.id, item.id))
      .filter(item => item.name.toLowerCase().includes(q));
  }, [search, isTrigger, isSubInput, port?.id]);

  // ── resetAll: close drawer and clear all state ──
  const resetAll = () => {
    setMode(null);
    setStep("select");
    setSelectedItem(null);
    setSearch("");
    resetForm();
    onClose();
  };

  return {
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
  };
};
