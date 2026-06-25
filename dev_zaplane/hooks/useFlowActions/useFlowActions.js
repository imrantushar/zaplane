import { useReactFlow, useUpdateNodeInternals } from "@xyflow/react";
import { getLayoutedElements } from "./utils/dagreLayout";
import { useCallback } from "react";
import { createActionNode, getBranchNodes } from "./utils/helper";

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

    const openDrawerFromAdd = (node) => {
        setDrawerContext({ source: "add", node, edge: null });
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
        handleAddAction,
        onAddNode,
        openDrawerForNode,
        openDrawerFromAdd,
        onLayout,
    };
};