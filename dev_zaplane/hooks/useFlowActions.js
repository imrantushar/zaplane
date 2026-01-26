/**
 * useFlowActions
 * A custom React hook for managing nodes, edges, and drawer actions in a workflow React Flow canvas.
 * 
 * Features:
 * - Updates selected node’s data.
 * - Deletes a node along with its connected edges.
 * - Creates a new action node, shifts other nodes if needed, and manages edges.
 * - Opens drawer for a node or for adding a new node.
 * - Provides helper for getting an edge by ID.
 * 
 * Usage:
 * const { updateNodeData, deleteNode, createActionNode, onAddNode, openDrawerForNode, openDrawerFromAdd } =
 *    useFlowActions({ nodes, setNodes, edges, setEdges, drawerContext, setDrawerContext, setDrawerOpen, getNewNodeId, GAP });
 * 
 * Parameters:
 * @param {array} nodes                  - Current array of nodes.
 * @param {function} setNodes            - Setter for nodes state.
 * @param {array} edges                  - Current array of edges.
 * @param {function} setEdges            - Setter for edges state.
 * @param {object} drawerContext         - Current drawer context (selected node/edge).
 * @param {function} setDrawerContext    - Setter for drawer context.
 * @param {function} setDrawerOpen       - Function to open/close drawer.
 * @param {function} getNewNodeId        - Function to generate unique node IDs.
 * @param {number} GAP                   - Optional spacing between nodes (default 250).
 * 
 * Returns:
 * @returns {object} - {
 *   updateNodeData, deleteNode, createActionNode, onAddNode, openDrawerForNode, openDrawerFromAdd
 * }
 */

export const useFlowActions = ({
    nodes,
    setNodes,
    edges,
    setEdges,
    drawerContext,
    setDrawerContext,
    setDrawerOpen,
    getNewNodeId,
    GAP = 250,
}) => {
    const updateNodeData = (updatedData) => {
        setNodes((nds) =>
            nds.map((n) =>
                n.id === drawerContext.node?.id
                    ? { ...n, data: { ...n.data, ...updatedData } }
                    : n
            )
        );
    };

    const deleteNode = (nodeId) => {
        setNodes((nds) => nds.filter((n) => n.id !== nodeId));
        setEdges((eds) =>
            eds.filter((e) => e.source !== nodeId && e.target !== nodeId)
        );
    };

    const createActionNode = (actionData) => {
        const { edge, node } = drawerContext;
        let sourceNode = null;
        let targetNode = null;

        if (edge) {
            sourceNode = nodes.find((n) => n.id === edge.source);
            targetNode = nodes.find((n) => n.id === edge.target);
            if (!sourceNode || !targetNode) return;
        } else if (node) {
            sourceNode = nodes.find((n) => n.id === node.id);
            if (!sourceNode) return;
        }

        const newNodeId = getNewNodeId();
        const newX = sourceNode.position.x + GAP;
        const newY = sourceNode.position.y;

        const newNode = {
            id: newNodeId,
            type: "custom",
            position: { x: newX, y: newY },
            data: {
                action: "action",
                ...actionData,
            },
        };

        // Shift nodes if they are after newX
        const updatedNodes = nodes.map((n) => {
            if (n.position.x >= newX) {
                return { ...n, position: { ...n.position, x: n.position.x + GAP } };
            }
            return n;
        });

        let newEdges = [...edges];

        if (edge) {
            newEdges = [
                ...edges.filter((e) => e.id !== edge.id),
                { id: `e${edge.source}-${newNodeId}`, source: edge.source, target: newNodeId, type: "custom" },
                { id: `e${newNodeId}-${edge.target}`, source: newNodeId, target: edge.target, type: "custom" },
            ];
        } else {
            newEdges.push({ id: `e${sourceNode.id}-${newNodeId}`, source: sourceNode.id, target: newNodeId, type: "custom" });
        }

        setNodes([...updatedNodes, newNode]);
        setEdges(newEdges);
    };

    const onAddNode = (edgeId) => {
        const edge = edges.find((e) => e.id === edgeId);
        return edge;
    };

    const openDrawerForNode = (node) => {
        setDrawerContext({ source: "node", node, edge: null });
        setDrawerOpen(true);
    };

    const openDrawerFromAdd = (node) => {
        setDrawerContext({ source: "add", node, edge: null });
        setDrawerOpen(true);
    };

    return {
        updateNodeData,
        deleteNode,
        createActionNode,
        onAddNode,
        openDrawerForNode,
        openDrawerFromAdd,
    };
};
