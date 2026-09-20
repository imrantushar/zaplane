import { integrations } from "@ZAPUtils/helper";

export const getIntegration = (mode, selectedItem) => {
  if (!selectedItem?.id) return null;
  return mode === "tools"
    ? integrations.tools?.[selectedItem.id]
    : integrations.apps?.[selectedItem.id];
};

//get action hook 
export const getActionHook = ({ mode, selectedItem, actionKey }) => {
  if (!mode || !selectedItem || !actionKey) return "";

  const integration = getIntegration(mode, selectedItem);
  if (!integration?.triggers) return "";

  const trigger = Object.values(integration.triggers)
    .find(item => item.key === actionKey);

  return trigger?.hook || "";
};

// Get action options for the dropdown based on mode and selected item.

export const getActionOptions = (mode, selectedItem, isTrigger) => {
  const integration = getIntegration(mode, selectedItem);
  if (!integration) return [];

  // Triggers come from `triggers`, actions from `actions` — regardless of
  // whether the integration is an app or a tool (tools like Schedule can be
  // triggers too).
  const list = isTrigger
    ? Object.values(integration.triggers || {})
    : Object.values(integration.actions || {});

  // Fall back to the key when a trigger/action was saved without a label, so it
  // never renders as a blank, unselectable option.
  return list.map(i => ({ label: i.label || i.key, value: i.key, hook: i.hook }));
};


  // Get schema fields for the selected action type.

export const getSelectedActionFields = (mode, selectedItem, actionType, isTrigger) => {
  const integration = getIntegration(mode, selectedItem);
  if (!integration || !actionType) return [];
  if (isTrigger) return integration.triggers?.[actionType]?.schema || [];
  return integration.actions?.[actionType]?.schema || [];
};

// Filter fields based on depends_on conditions.

export const getVisibleFields = (fields, values) => {
  return fields.filter((f) => {
    if (!f.depends_on) return true;
    // A dependency value may be a single value or a list of accepted values,
    // e.g. depends_on: { operation: ['truncate', 'substring', 'pad'] }.
    return Object.entries(f.depends_on).every(([k, v]) =>
      Array.isArray(v) ? v.includes(values[k]) : values[k] === v
    );
  });
};

// Build payload for continue action.
export const buildContinuePayload = (selectedItem, values, visibleFields) => {
  return {
    icon: selectedItem.icon,
    app: selectedItem.id,
    name: selectedItem.name,
    event: values.actionType,
    config: visibleFields.reduce((acc, f) => {
      acc[f.key] = values[f.key];
      return acc;
    }, {}),
    ...(selectedItem.mode && { mode: selectedItem.mode }),
    ...(values.hook && { hook: values.hook }),
    ...(values.connection_id && { connection_id: values.connection_id }),
    // Set only by a trigger's field matching; null clears a match.
    ...(values.field_map !== undefined && { field_map: values.field_map }),
  };
};

