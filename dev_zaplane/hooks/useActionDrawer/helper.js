import { integrations } from "@ZAPUtils/helper";

// Preprocess apps
export const APPS = Object.entries(integrations?.apps || {}).map(([key, value]) => ({
  id: value.slug || key,
  name: value.name,
  icon:value.icon
}));

// Preprocess tools
export const TOOLS = Object.entries(integrations?.tools || {}).map(([key, value]) => ({
  id: value.slug || key,
  name: value.name,
  icon:value.icon
}));

const hasEntries = (obj) => obj && Object.keys(obj).length > 0;

// Tools usable as actions (most of them) vs. as triggers (e.g. Schedule). A
// tool can appear in either list, or both, depending on what it exposes.
export const ACTION_TOOLS = TOOLS.filter(
  (t) => hasEntries(integrations?.tools?.[t.id]?.actions)
);
export const TRIGGER_TOOLS = TOOLS.filter(
  (t) => hasEntries(integrations?.tools?.[t.id]?.triggers)
);

