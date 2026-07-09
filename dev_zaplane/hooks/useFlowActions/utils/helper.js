
export const getBranchNodes = (startId,edges) => {
    const branch = new Set();
    const stack = [startId];

    while (stack.length) {
        const current = stack.pop();
        branch.add(current);

        edges.forEach((e) => {
            if (e.source === current) {
                stack.push(e.target);
            }
        });
    }

    return branch;
};

export const createActionNode = ({
    nodes,
    edges,
    drawerContext,
    canvasLayout,
    getNewNodeId,
    setNodes,
    setEdges,
    setDrawerContext,
    setDrawerOpen,
    actionData,
}) => {
    const layoutLR = canvasLayout === "LR";
    const LRGap = 300;
    const TBGap = 140;

    const { edge, node, port } = drawerContext;

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
    // A "target" sub-handle (AI Agent tools/memory/model) places the new node
    // BELOW the anchor; everything else places it to the side/below as usual.
    const isSubInput = port?.type === "target";
    // Spread stacked sub-nodes so a 2nd/3rd tool doesn't land on the first.
    const subCount = isSubInput
        ? edges.filter((e) => e.target === sourceNode.id && e.targetHandle === port.id).length
        : 0;
    const newX = isSubInput
        ? sourceNode.position.x + subCount * 220
        : (layoutLR ? sourceNode.position.x + LRGap : sourceNode.position.x);
    const newY = isSubInput
        ? sourceNode.position.y + 180
        : (layoutLR ? sourceNode.position.y : sourceNode.position.y + TBGap);

    const isTools = actionData?.mode === "tools";

    const newNode = {
        id: newNodeId,
        type: "custom",
        position: { x: newX, y: newY },
        data: {
            action: isTools ? actionData.app : "action",
            ...actionData,
        },
    };

    const branchNodes = edge ? getBranchNodes(edge.target, edges) : null;

    const updatedNodes = nodes.map((n) => {
        if (!branchNodes || !branchNodes.has(n.id)) return n;

        if (layoutLR) {
            return { ...n, position: { ...n.position, x: n.position.x + LRGap } };
        }
        return { ...n, position: { ...n.position, y: n.position.y + TBGap } };
    });

    let newEdges = [...edges];

    if (edge) {
        newEdges = [
            ...edges.filter((e) => e.id !== edge.id),
            { id: `e${edge.source}-${newNodeId}`, source: edge.source, target: newNodeId, type: "custom" },
            { id: `e${newNodeId}-${edge.target}`, source: newNodeId, target: edge.target, type: "custom" },
        ];
    } else if (port?.type === "target") {
        // Sub-node feeds INTO the anchor's input handle (e.g. agent tools).
        newEdges.push({ id: `e${newNodeId}-${sourceNode.id}-${port.id}`, source: newNodeId, target: sourceNode.id, targetHandle: port.id, type: "custom" });
    } else if (port?.type === "source") {
        // Child of a specific output branch (e.g. iterator loop, router path).
        newEdges.push({ id: `e${sourceNode.id}-${newNodeId}-${port.id}`, source: sourceNode.id, target: newNodeId, sourceHandle: port.id, type: "custom" });
    } else {
        newEdges.push({ id: `e${sourceNode.id}-${newNodeId}`, source: sourceNode.id, target: newNodeId, type: "custom" });
    }

    // Condition node support
    if (actionData.app === "condition") {
        const trueNodeId = getNewNodeId();
        const falseNodeId = getNewNodeId();
        const extraLRSpace = 80;

        const trueNode = {
            id: trueNodeId,
            parentNodeId: newNodeId,
            type: "custom",
            port: true,
            position: {
                x: layoutLR ? newX + LRGap + extraLRSpace : newX,
                y: layoutLR ? newY - 90 : newY + TBGap,
            },
            data: { action: "action", app: "Select an app",icon:'plus' },
        };

        const falseNode = {
            id: falseNodeId,
            parentNodeId: newNodeId,
            type: "custom",
            port: false,
            position: {
                x: layoutLR ? newX + LRGap + extraLRSpace : newX,
                y: layoutLR ? newY + 90 : newY + TBGap * 2,
            },
            data: { action: "action", app: "Select an app",icon:'plus' },
        };

        newEdges.push(
            { id: `e${newNodeId}-${trueNodeId}`, source: newNodeId, target: trueNodeId, sourceHandle: "true", type: "custom" },
            { id: `e${newNodeId}-${falseNodeId}`, source: newNodeId, target: falseNodeId, sourceHandle: "false", type: "custom" }
        );

        setNodes([...updatedNodes, newNode, trueNode, falseNode]);
        setEdges(newEdges);
    } else {
        setNodes([...updatedNodes, newNode]);
        setEdges(newEdges);
    }

    setDrawerContext({ source: "node", node: newNode, edge: null });
    setDrawerOpen(true);
};