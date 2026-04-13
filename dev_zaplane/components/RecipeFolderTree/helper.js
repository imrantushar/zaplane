export const collectExpandableIds = (nodes = [], ids = {}) => {
  nodes.forEach((node) => {
    if (Array.isArray(node.children) && node.children.length > 0) {
      ids[node.id] = true;
      collectExpandableIds(node.children, ids);
    }
  });
  return ids;
};
