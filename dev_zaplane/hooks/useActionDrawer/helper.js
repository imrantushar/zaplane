import { integrations } from "@ZAPUtils/helper";

// Preprocess apps
export const APPS = Object.entries(integrations.apps || {}).map(([key, value]) => ({
  id: value.slug || key,
  name: value.name,
  icon: value.icon
}));

// Preprocess tools
export const TOOLS = Object.entries(integrations.tools || {}).map(([key, value]) => ({

  id: value.slug || key,
  name: value.name,
  icon: value.icon

}));

