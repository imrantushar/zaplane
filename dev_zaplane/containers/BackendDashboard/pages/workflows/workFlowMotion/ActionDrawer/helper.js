import { integrations } from "@ZAPUtils/helper";

export const getIntegration = (mode, selectedItem) => {
    if (!selectedItem?.id) return null;
    return mode === "tools"
        ? integrations.tools?.[selectedItem.id]
        : integrations.apps?.[selectedItem.id];
};