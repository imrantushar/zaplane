/**
 * useActionDrawer
 * ----------------
 * A custom React hook for managing the state and behavior of an Action Drawer in a flow canvas .
 * 
 * Features:
 * - Tracks the drawer mode ("app" or "tools") and selected item.
 * - Manages search input and generates filtered search results.
 * - Pre-populates form values based on the selected node (app, event, config).
 * - Provides convenient setters for mode, selected item, and search.
 * 
 * 
 * Parameters:
 * @param {boolean} open         - Whether the drawer is open.
 * @param {object} node           - Current node data (app, event, config).
 * @param {string} source         - Drawer context ("add" or "node").
 * @param {function} setFieldValue- Function to update form field values (e.g., Formik).
 * @param {boolean} isTrigger     - Whether this drawer is for a trigger action.
 * 
 * Returns:
 * @returns {object} - {
 *   mode, setMode, selectedItem, setSelectedItem, search, setSearch, list, searchList
 * }
 */
import { useState, useMemo, useEffect } from "react";
import { APPS, TOOLS } from "./helper";

export const useActionDrawer = (open, node, source, setFieldValue, isTrigger) => {
  const [mode, setMode] = useState(null);
  const [selectedItem, setSelectedItem] = useState(null);
  const [search, setSearch] = useState("");

  useEffect(() => {
    if (!open || !node?.data || source === "add") return;

    const detectedItem = APPS.concat(TOOLS).find(
      i => i.name === node.data.app || i.id === node.data.app
    );

    if (detectedItem) {
      setMode(TOOLS.includes(detectedItem) ? "tools" : "app");
      setSelectedItem(detectedItem);
    }

    if (node.data.event) setFieldValue("actionType", node.data.event);
    if (node.data.config) {
      Object.entries(node.data.config).forEach(([k, v]) => setFieldValue(k, v));
    }
  }, [open, node?.data]);

  const list = useMemo(() => (mode === "app" ? APPS : mode === "tools" ? TOOLS : []), [mode]);

  const searchList = useMemo(() => {
    if (!search) return [];
    const q = search.toLowerCase();
    const combined = isTrigger
      ? APPS
      : APPS.concat(TOOLS.map(t => ({ ...t, type: "tools" })));

    return combined.filter(item => item.name.toLowerCase().includes(q));
  }, [search, isTrigger]);


  return { mode, setMode, selectedItem, setSelectedItem, search, setSearch, list, searchList };
};
