
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

/**
 * The next `count` node ids for a canvas: numeric strings continuing from the
 * highest id already on it.
 */
export const nextNodeIds = (nodes = [], count = 1) => {
    const highest = nodes.reduce((max, node) => {
        const id = parseInt(node?.id, 10);
        return Number.isFinite(id) && id > max ? id : max;
    }, 0);

    return Array.from({ length: count }, (_, index) => String(highest + index + 1));
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

    // New ids continue from the highest id on the canvas. The page's counter is
    // not seeded from a loaded graph, so it could hand out an id a saved node has.
    const [newNodeId, trueNodeId, falseNodeId] = nextNodeIds(nodes, 3);
    // A "target" sub-handle (AI Agent tools/memory/model) places the new node
    // BELOW the anchor; everything else places it to the side/below as usual.
    const isSubInput = port?.type === "target";
    // Sub-nodes fan out into one column per port (model | memory | tools), so
    // nodes wired to different ports never stack on each other; extra nodes on
    // the same port (e.g. a 2nd tool) continue rightward along that column.
    const SUB_COLUMN_GAP = 250;
    const SUB_PORT_ORDER = ["ai_model", "ai_memory", "ai_tool"];
    const subPortIndex = isSubInput ? Math.max(0, SUB_PORT_ORDER.indexOf(port.id)) : 0;
    const subCount = isSubInput
        ? edges.filter((e) => e.target === sourceNode.id && e.targetHandle === port.id).length
        : 0;
    const newX = isSubInput
        ? sourceNode.position.x + (subPortIndex - 1 + subCount) * SUB_COLUMN_GAP
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
        // Sub-node feeds INTO the anchor's input handle (e.g. agent tools). It
        // connects from its own top handle ("sub_out") for a clean vertical path.
        newEdges.push({ id: `e${newNodeId}-${sourceNode.id}-${port.id}`, source: newNodeId, target: sourceNode.id, sourceHandle: "sub_out", targetHandle: port.id, type: "custom" });
    } else if (port?.type === "source") {
        // Child of a specific output branch (e.g. iterator loop, router path).
        newEdges.push({ id: `e${sourceNode.id}-${newNodeId}-${port.id}`, source: sourceNode.id, target: newNodeId, sourceHandle: port.id, type: "custom" });
    } else {
        newEdges.push({ id: `e${sourceNode.id}-${newNodeId}`, source: sourceNode.id, target: newNodeId, type: "custom" });
    }

    // Condition node support
    if (actionData.app === "condition") {
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