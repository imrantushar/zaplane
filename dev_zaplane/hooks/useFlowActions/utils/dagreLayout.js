import dagre from "dagre";

// Must track the rendered node card size (CustomNode.js: minWidth 220px,
// minHeight 64px) — if these are smaller than the real card, dagre computes
// gaps assuming a narrower box than what actually renders, so the visible
// space between cards (and the edge/arrow drawn in it) collapses.
const NODE_WIDTH = 240;
const NODE_HEIGHT = 64;

export const getLayoutedElements = (nodes, edges, direction = "TB") => {
  const dagreGraph = new dagre.graphlib.Graph();
  dagreGraph.setDefaultEdgeLabel(() => ({}));

  dagreGraph.setGraph({
    rankdir: direction,
    ranker: "tight-tree",
    ranksep: direction === "LR" ? 140 : 90,
    nodesep: 60,
  });

  nodes.forEach((node) => {
    dagreGraph.setNode(node.id, {
      width: NODE_WIDTH,
      height: NODE_HEIGHT,
    });
  });

  edges.forEach((edge) => {
    dagreGraph.setEdge(edge.source, edge.target, {
      weight: 5,
      minlen: 1,
    });
  });

  dagre.layout(dagreGraph);

  const layoutedNodes = nodes.map((node) => {
    const pos = dagreGraph.node(node.id);

    return {
      ...node,
      position: {
        x: pos.x - NODE_WIDTH / 2,
        y: pos.y - NODE_HEIGHT / 2,
      },
    };
  });

  return { nodes: layoutedNodes, edges };
};
