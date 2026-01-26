export const createNodeIdGenerator = () => {
  let current = 0;
  return () => {
    current += 1;
    return `${current}`;
  };
};
export const mapGraphFromBackend = (graph) => {
  // if (!graph) return { nodes: [], edges: [] };
  return {
    nodes: (graph.nodes || []).map((node) => ({
      ...node,
      type: "custom",
      data: {
        ...node.data,
        action: node.type,
      },
    })),
    edges: (graph.edges || []).map((edge) => ({
      ...edge,
      type: "custom",
    })),
  };
};
