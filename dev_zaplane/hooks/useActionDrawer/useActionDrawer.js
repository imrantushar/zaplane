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
