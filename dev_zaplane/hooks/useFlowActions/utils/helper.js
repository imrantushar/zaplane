
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
    const newX = layoutLR ? sourceNode.position.x + LRGap : sourceNode.position.x;
    const newY = layoutLR ? sourceNode.position.y : sourceNode.position.y + TBGap;

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