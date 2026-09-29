import { useReactFlow, useUpdateNodeInternals } from "@xyflow/react";
import { getLayoutedElements } from "./utils/dagreLayout";
import { useCallback } from "react";
import { createActionNode, getBranchNodes, nextNodeIds } from "./utils/helper";

export const useFlowActions = ({
    nodes,
    setNodes,
    edges,
    setEdges,
    drawerContext,
    setDrawerContext,
    setDrawerOpen,
    getNewNodeId,
    setFieldValue,
    canvasLayout,
    setCanvasLayOut,
    values

}) => {

    const updateNodeData = (updatedData) => {
        setNodes((nds) =>
            nds.map((n) =>
                n.id === drawerContext.node?.id
                    ? { ...n, data: { ...n.data, ...updatedData } }
                    : n
            )
        );
        setDrawerContext((prev) => ({
            ...prev,
            node: {
                ...prev.node,
                data: {
                    ...prev.node.data,
                    ...updatedData,
                },
            },
        }));
    };

    const resetTrigger = (nodeId) => {
        setNodes((nds) =>
            nds.map((n) =>
                n.id === nodeId
                    ? { ...n, data: { icon: "plus", app: "Select an app", action: "trigger", config: {} } }
                    : n
            )
        );
    };

    // Add another trigger. It joins the steps the trigger it was added from leads
    // to, so either one starts the same flow, and the trigger picker opens for it.
    const addTrigger = (fromNodeId) => {
        const triggers = nodes.filter((n) => n.data?.action === "trigger");
        const from = nodes.find((n) => n.id === fromNodeId) || triggers[triggers.length - 1];
        if (!from) return;

        const [id] = nextNodeIds(nodes, 1);
        const position = canvasLayout === "LR"
            ? { x: from.position.x, y: Math.max(...triggers.map((n) => n.position.y)) + 140 }
            : { x: Math.max(...triggers.map((n) => n.position.x)) + 300, y: from.position.y };

        const node = {
            id,
            type: "custom",
            position,
            data: { icon: "plus", app: "Select an app", action: "trigger", config: {} },
        };

        const joined = edges
            .filter((e) => e.source === from.id && !e.sourceHandle)
            .map((e) => ({ id: `e${id}-${e.target}`, source: id, target: e.target, type: "custom" }));

        setNodes((nds) => [...nds, node]);
        if (joined.length) {
            setEdges((eds) => [...eds, ...joined]);
        }

        openDrawerForNode(node);
    };

    const deleteNode = (nodeId) => {
        const childNodes = nodes.filter((n) => n.parentNodeId === nodeId);
        const childIds = childNodes.map((n) => n.id);
        const allDeleteIds = [nodeId, ...childIds];

        setNodes((nds) =>
            nds.filter((n) => !allDeleteIds.includes(n.id))
        );

        setEdges((eds) =>
            eds.filter(
                (e) =>
                    !allDeleteIds.includes(e.source) &&
                    !allDeleteIds.includes(e.target)
            )
        );
    };

    const handleAddAction = (actionData) => {
        createActionNode({
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
        });
    };
    const onAddNode = (edgeId) => {
        const edge = edges.find((e) => e.id === edgeId);
        return edge;
    };

    const openDrawerForNode = (node) => {
        setDrawerContext({ source: "node", node, edge: null });
      setFieldValue("nodeClick", !values.nodeClick);
        setDrawerOpen(true);
    };

    // `port` (optional) targets a specific branch/sub-handle:
    //   { id, type: "source" } → new node is a child of this node's output port
    //   { id, type: "target" } → new node feeds INTO this node's input handle
    //                            (AI Agent tool/memory/model sub-nodes)
    const openDrawerFromAdd = (node, port = null) => {
        setDrawerContext({ source: "add", node, edge: null, port });
        setDrawerOpen(true);
    };

    const { fitView, getZoom } = useReactFlow();
    const updateNodeInternals = useUpdateNodeInternals();

    const onLayout = useCallback(
        (direction) => {
            const { nodes: layoutedNodes, edges: layoutedEdges } =
                getLayoutedElements(nodes, edges, direction);

            setNodes(layoutedNodes);
            setEdges(layoutedEdges);

            requestAnimationFrame(() => {

                layoutedNodes.forEach((node) => {
                    updateNodeInternals(node.id);
                });

                const currentZoom = getZoom();
                fitView({ padding: 0.2, duration: 300, minZoom: currentZoom, maxZoom: currentZoom });
                setCanvasLayOut(direction)
            });
        },
        [nodes, edges, fitView, updateNodeInternals]
    );

    return {
        updateNodeData,
        deleteNode,
        resetTrigger,
        addTrigger,
        handleAddAction,
        onAddNode,
        openDrawerForNode,
        openDrawerFromAdd,
        onLayout,
    };
};