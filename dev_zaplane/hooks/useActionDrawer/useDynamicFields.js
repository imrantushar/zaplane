import { useState, useEffect, useCallback, useMemo } from "react";
import { fetchDynamic } from "@ZAPRedux/Slices/workFlowSlice/helper";

export const useDynamicFields = ({
  selectedItem,
  mode,
  selectedActionFields,
  values,
}) => {

  const [dynamicOptions, setDynamicOptions] = useState({});
  const [loadingFields, setLoadingFields] = useState({});

  /**
   * Cache key:
   * - No depends_on  → stable key, cached forever until item/mode changes
   * - Has depends_on → key includes current dep values, so changing a dep
   *                    field produces a new key and triggers a fresh fetch
   */
  const getKey = useCallback(
    (field, search = "") => {
      const deps = field.dynamic?.depends_on || [];
      const base = deps.length === 0
        ? `${mode}:${selectedItem?.id}:${field.key}`
        : `${mode}:${selectedItem?.id}:${field.key}:${deps
            .map((dep) => `${dep}=${values?.[dep] ?? ""}`)
            .join(":")}`;
      
      return search ? `${base}:search:${search}` : base;
    },
    [mode, selectedItem, values]
  );

  const fetchDynamicOptions = useCallback(async (field, search = "") => {
    if (!field.dynamic) return;

    const key = getKey(field, search);
    // Remove early return for !search to allow fresh "reset" fetches
    // if (!search && dynamicOptions[key]) return; 

    setLoadingFields((p) => ({ ...p, [key]: true }));

    try {
      const deps = field.dynamic.depends_on || [];
      const depParams = {};
      deps.forEach((dep) => {
        if (values?.[dep]) depParams[dep] = values[dep];
      });

      const res = await fetchDynamic({ 
        ...field.dynamic, 
        ...depParams, 
        search,
        limit: 20 
      });

      const mapped = Object.values(res || {}).map((i) => ({
        value: i[field.dynamic.select[0]],
        label: i[field.dynamic.select[1]],
      }));

      setDynamicOptions((p) => ({ ...p, [key]: mapped }));
    } finally {
      setLoadingFields((p) => ({ ...p, [key]: false }));
    }
  }, [dynamicOptions, getKey, values]);

  /**
   * Snapshot of every depends_on value across all fields.
   * Used as a stable effect dependency — changes only when a dep value changes.
   */
  const dependsOnSnapshot = useMemo(() => {
    const snap = {};
    selectedActionFields.forEach((field) => {
      (field.dynamic?.depends_on || []).forEach((dep) => {
        snap[dep] = values?.[dep];
      });
    });
    return JSON.stringify(snap);
  }, [selectedActionFields, values]);

  /**
   * Auto-fetch on edit mode (field already has a saved value).
   * Runs on actionType change only — for fields without depends_on.
   */
  useEffect(() => {
    if (!selectedItem || !values?.actionType) return;

    selectedActionFields.forEach((field) => {
      if (!field.dynamic) return;
      const deps = field.dynamic.depends_on || [];
      if (deps.length > 0) return; // handled by the effect below
      if (values?.[field.key] && !dynamicOptions[getKey(field)]) {
        fetchDynamicOptions(field);
      }
    });
  }, [selectedActionFields, values?.actionType]);

  /**
   * Auto-fetch for fields that have depends_on.
   * Runs whenever any depends_on value changes.
   * Only fetches when every required dep has a value.
   */
  useEffect(() => {
    if (!selectedItem || !values?.actionType) return;

    selectedActionFields.forEach((field) => {
      if (!field.dynamic) return;
      const deps = field.dynamic.depends_on || [];
      if (deps.length === 0) return; // handled by the effect above
      const allDepsFilled = deps.every((dep) => values?.[dep]);
      if (!allDepsFilled) return;
      if (!dynamicOptions[getKey(field)]) {
        fetchDynamicOptions(field);
      }
    });
  }, [selectedActionFields, values?.actionType, dependsOnSnapshot]);

  return {
    dynamicOptions,
    loadingFields,
    fetchDynamicOptions,
    getKey,
  };
};
