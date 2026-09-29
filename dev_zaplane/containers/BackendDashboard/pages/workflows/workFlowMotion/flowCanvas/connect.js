import { getNodePorts } from "../customNode/helper";

// Handles on an AI Agent that take a sub-node (chat model, memory, tools).
const AI_PORTS = ["ai_tool", "ai_memory", "ai_model"];

const isTriggerNode = node => node?.data?.action === "trigger";

/**
 * Whether `to` can already be reached from `from` by following lines forward.
 * If so, a line from `to` back to `from` would close a loop.
 */
const reaches = (edges, from, to) => {
  const seen = new Set([from]);
  const queue = [from];

  while (queue.length) {
    const id = queue.shift();
    if (id === to) return true;

    edges.forEach(edge => {
      if (edge.source === id && !seen.has(edge.target)) {
        seen.add(edge.target);
        queue.push(edge.target);
      }
    });
  }

  return false;
};

/**
 * Whether a line may be drawn: not into a trigger (it has no input), not from a
 * node to itself, not a second copy of a line that exists, and not a loop.
 */
export const canConnect = (nodes, edges, connection) => {
  if (!connection) return false;

  const { source, target } = connection;
  const sourceHandle = connection.sourceHandle ?? null;
  const targetHandle = connection.targetHandle ?? null;

  if (!source || !target || source === target) return false;

  const targetNode = nodes.find(n => n.id === target);
  if (!targetNode || isTriggerNode(targetNode)) return false;

  // A sub-node's top handle only fits one of an agent's sub-node ports.
  if (sourceHandle === "sub_out" && !AI_PORTS.includes(targetHandle)) return false;

  const exists = edges.some(e =>
    e.source === source &&
    e.target === target &&
    (e.sourceHandle ?? null) === sourceHandle &&
    (e.targetHandle ?? null) === targetHandle
  );
  if (exists) return false;

  return !reaches(edges, target, source);
};

/**
 * A line let go of anywhere on a card, as the connection it stands for. Null when
 * the card has no single handle it could mean.
 *
 * `drag` is where the line started: { nodeId, handleId, handleType }.
 */
export const connectionToCard = (nodes, edges, drag, cardId) => {
  const card = nodes.find(n => n.id === cardId);
  if (!card || !drag || cardId === drag.nodeId) return null;

  if (drag.handleType === "source") {
    // From a sub-node's top handle the line belongs on one of the agent's ports,
    // and dropping on the card doesn't say which.
    if (drag.handleId === "sub_out") return null;

    return { source: drag.nodeId, sourceHandle: drag.handleId, target: cardId, targetHandle: null };
  }

  // Dragged backwards from an input, so the card becomes the source. A card with
  // several outputs (a Router's paths, a Condition's yes/no) doesn't say which.
  if (getNodePorts(card.data).length > 1) return null;

  const toAiPort = AI_PORTS.includes(drag.handleId);
  const cardIsSubNode = edges.some(e => e.source === cardId && AI_PORTS.includes(e.targetHandle));
  if (!toAiPort && cardIsSubNode) return null;

  return {
    source: cardId,
    sourceHandle: toAiPort ? "sub_out" : null,
    target: drag.nodeId,
    targetHandle: drag.handleId,
  };
};
