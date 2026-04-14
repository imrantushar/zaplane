export const mapNodesForBackend = (nodes) => {
  return nodes.map(({
    dragging,
    selected,
    measured,
    data,
    ...node
  }) => {
    const backendType = data?.action?.toLowerCase();
    const cleanedData = { ...data };
    delete cleanedData.action;

    return {
      ...node,
      type: backendType,
      data: cleanedData,
    };
  });
};
export const mapEdgesForBackend = (edges) => {
  return edges.map(({ type, ...edge }) => edge);
};
export const extractIntegrationIcons = (nodes) => {
  const icons = nodes
    .map((node) => node?.data?.icon) 
    .filter(Boolean);

  const uniqueIcons = [...new Set(icons)];

  return uniqueIcons.slice(0, 3); 
};
/**
 * Generate comparable flow hash
 */
export const generateFlowHash = (nodes, edges) => {
  const mapped = {
    nodes: mapNodesForBackend(nodes),
    edges: mapEdgesForBackend(edges),
  };

  return JSON.stringify(mapped);
};