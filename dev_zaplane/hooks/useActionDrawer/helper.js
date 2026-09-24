import { integrations } from "@ZAPUtils/helper";

// Preprocess apps
export const APPS = Object.entries(integrations?.apps || {}).map(([key, value]) => ({
  id: value.slug || key,
  name: value.name,
  icon:value.icon,
  docs_url: value.docs_url
}));

// Preprocess tools
export const TOOLS = Object.entries(integrations?.tools || {}).map(([key, value]) => ({
  id: value.slug || key,
  name: value.name,
  icon:value.icon,
  docs_url: value.docs_url
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

// An AI-Agent tool sub-node shouldn't be a trigger, the agent itself,
// memory/model (those have their own dedicated handles), or a control-flow node
// — none of those are callable tools.
export const SUB_TOOL_BLOCKLIST = new Set([
  "ai-agent", "memory", "ai", "sticky_note", "manual",
  "condition", "filter", "router", "iterator", "repeater", "delay",
  "human_approval", "schedule",
]);

// Which apps/tools may be wired into a given AI-Agent sub-input handle.
//   ai_model  → only the AI (Chat Model) app
//   ai_memory → only the Memory tool
//   ai_tool   → any action-bearing node except the blocklist above
export const subInputAllows = (portId, itemId) => {
  if (portId === "ai_memory") return itemId === "memory";
  if (portId === "ai_model") return itemId === "ai";
  if (portId === "ai_tool") return !SUB_TOOL_BLOCKLIST.has(itemId);
  return true;
};

