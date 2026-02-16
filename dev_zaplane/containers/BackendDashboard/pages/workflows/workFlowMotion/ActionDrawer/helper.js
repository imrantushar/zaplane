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
