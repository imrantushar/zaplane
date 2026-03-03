import { useState, useEffect, useCallback } from "react";
import { fetchDynamic } from "@ZAPRedux/Slices/workFlowSlice/helper";

export const useDynamicFields = ({
  selectedItem,
  mode,
  selectedActionFields,
  values,
}) => {

  const [dynamicOptions, setDynamicOptions] = useState({});
  const [loadingFields, setLoadingFields] = useState({});

  const getKey = useCallback(
    (field) => `${mode}:${selectedItem?.id}:${field.key}`,
    [mode, selectedItem]
  );

  const fetchDynamicOptions = useCallback(async (field) => {
    if (!field.dynamic) return;

    const key = getKey(field);
    if (dynamicOptions[key]) return;

    setLoadingFields(p => ({ ...p, [key]: true }));

    try {
      const res = await fetchDynamic(field.dynamic);

      const mapped = Object.values(res || {}).map(i => ({
        value: i[field.dynamic.select[0]],
        label: i[field.dynamic.select[1]],
      }));

      setDynamicOptions(p => ({
        ...p,
        [key]: mapped,
      }));

    } finally {
      setLoadingFields(p => ({ ...p, [key]: false }));
    }
  }, [dynamicOptions, getKey]);

  //Auto fetch in edit mode
  useEffect(() => {
    if (!selectedItem || !values?.actionType) return;

    selectedActionFields.forEach(field => {
      if (
        field.dynamic &&
        values?.[field.key] &&
        !dynamicOptions[getKey(field)]
      ) {
        fetchDynamicOptions(field);
      }
    });

  }, [selectedActionFields, values?.actionType]);

  return {
    dynamicOptions,
    loadingFields,
    fetchDynamicOptions,
    getKey,
  };
};