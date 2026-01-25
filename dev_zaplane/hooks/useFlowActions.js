export const useFlowActions = ({
    nodes,
    setNodes,
    edges,
    setEdges,
    drawerContext,
    setDrawerContext,   // ← add this
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
