import { integrations } from '@ZAPUtils/helper';

/**
 * What kind of node this is, for colour.
 *
 * Four categories, because four is what the canvas actually distinguishes: what
 * starts a flow, what acts on the outside world, what shapes data in between,
 * and what thinks. They resolve from the manifest rather than a hand-kept list,
 * so a new integration is categorised the moment it is registered.
 *
 * Order matters: the AI family lives in the manifest's `tools` bucket, so it has
 * to be claimed before the tool check or every AI node would read as a tool.
 */
const AI_APPS = ['ai', 'ai-agent', 'memory', 'knowledge', 'mcp-client'];

export const NODE_CATEGORIES = ['trigger', 'action', 'tool', 'ai'];

export function nodeCategory(data = {}) {
  if (data.action === 'trigger') return 'trigger';

  const app = data.app;
  if (!app) return 'action';

  if (AI_APPS.includes(app)) return 'ai';
  if (integrations?.tools?.[app]) return 'tool';

  return 'action';
}

/** The CSS variable carrying a category's hue. */
export function categoryHue(category) {
  return `var(--zaplane-cat-${NODE_CATEGORIES.includes(category) ? category : 'action'})`;
}

/** A node's hue, straight from its data. */
export function nodeHue(data) {
  return categoryHue(nodeCategory(data));
}

/**
 * Category hue mixed into a surface. Tailwind's `/opacity` syntax does not work
 * against a CSS custom property, so tints go through color-mix.
 */
export function hueMix(category, percent, base = 'transparent') {
  return `color-mix(in srgb, ${categoryHue(category)} ${percent}%, ${base})`;
}

export const CATEGORY_LABELS = {
  trigger: 'Trigger',
  action: 'Action',
  tool: 'Tool',
  ai: 'AI',
};

// Ports (output branches) declared by the node's action in the manifest, e.g.
// router → path_1..fallback, condition → true/false, iterator → loop/done.
export const getNodePorts = (data) => {
  const integ = integrations?.apps?.[data?.app] || integrations?.tools?.[data?.app];
  const outputs = integ?.actions?.[data?.event]?.outputs || [];
  // "main" is the implicit single output — not a branch.
  const branches = outputs.filter((p) => p && p !== "main");

  // Router: one path per configured route (+ fallback), derived from the
  // dynamic `routes` repeater. A fresh router still shows one path to build on.
  if (data?.app === "router") {
    const routes = Array.isArray(data?.config?.routes) ? data.config.routes : [];
    const active = Array.from({ length: Math.max(1, routes.length) }, (_, i) => `path_${i + 1}`);
    return [...active, "fallback"];
  }

  return branches;
};
