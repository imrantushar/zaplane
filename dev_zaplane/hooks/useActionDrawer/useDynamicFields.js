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

      const select = Array.isArray(field.dynamic.select)
        ? field.dynamic.select
        : ["value", "label"];
      const valueKey = select[0] || "value";
      const labelKey = select[1] || "label";

      const mapped = Object.values(res || {})
        .map((i) => {
          const optionValue = i?.[valueKey] ?? i?.value ?? i?.name;
          if (optionValue === undefined || optionValue === null || optionValue === "") {
            return null;
          }
          return {
            value: optionValue,
            label: String(i?.[labelKey] ?? i?.label ?? optionValue),
          };
        })
        .filter(Boolean);

      setDynamicOptions((p) => ({ ...p, [key]: mapped }));
    } catch (error) {
      console.error("Zaplane dynamic option lookup failed", error);
      setDynamicOptions((p) => ({ ...p, [key]: [] }));
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
   * Auto-fetch independent dynamic fields as soon as an action/trigger is
   * selected. New nodes have no saved field value yet, so waiting for a value
   * meant their live option lists could stay blank until a menu event happened.
   */
  useEffect(() => {
    if (!selectedItem || !values?.actionType) return;

    selectedActionFields.forEach((field) => {
      if (!field.dynamic) return;
      const deps = field.dynamic.depends_on || [];
      if (deps.length > 0) return; // handled by the effect below

      const key = getKey(field);
      const hasOptions = Object.prototype.hasOwnProperty.call(dynamicOptions, key);
      if (!hasOptions && !loadingFields[key]) {
        fetchDynamicOptions(field);
      }
    });
  }, [
    selectedItem,
    selectedActionFields,
    values?.actionType,
    dynamicOptions,
    loadingFields,
    getKey,
    fetchDynamicOptions,
  ]);

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
