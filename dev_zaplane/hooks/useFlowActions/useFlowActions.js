import { useReactFlow, useUpdateNodeInternals } from "@xyflow/react";
import { getLayoutedElements } from "./utils/dagreLayout";
import { useCallback } from "react";

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
    setCanvasLayOut

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
        const layoutLR = canvasLayout === "LR";
        const LRGap = 250;
        const TBGap = 98;

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

        const newX = layoutLR
            ? sourceNode.position.x + LRGap
            : sourceNode.position.x;

        const newY = layoutLR
            ? sourceNode.position.y
            : sourceNode.position.y + TBGap;

        const isTools= actionData?.mode === 'tools'
        console.log(isTools,actionData,'a');

        const newNode = {
            id: newNodeId,
            type: "custom",
            position: { x: newX, y: newY },
            data: {
                action: isTools ? actionData.app : "action",
                ...actionData,
            },
        };

        const updatedNodes = nodes.map((n) => {
            if (layoutLR) {
                if (n.position.x >= newX) {
                    return {
                        ...n,
                        position: {
                            ...n.position,
                            x: n.position.x + LRGap,
                        },
                    };
                }
            } else {
                if (n.position.y >= newY) {
                    return {
                        ...n,
                        position: {
                            ...n.position,
                            y: n.position.y + TBGap,
                        },
                    };
                }
            }
            return n;
        });

        let newEdges = [...edges];

        if (edge) {
            newEdges = [
                ...edges.filter((e) => e.id !== edge.id),
                {
                    id: `e${edge.source}-${newNodeId}`,
                    source: edge.source,
                    target: newNodeId,
                    type: "custom",
                },
                {
                    id: `e${newNodeId}-${edge.target}`,
                    source: newNodeId,
                    target: edge.target,
                    type: "custom",
                },
            ];
        } else {
            newEdges.push({
                id: `e${sourceNode.id}-${newNodeId}`,
                source: sourceNode.id,
                target: newNodeId,
                type: "custom",
            });
        }

        /**
         * CONDITION NODE SUPPORT
         */

        if (actionData.app === "Condition") {

            const trueNodeId = getNewNodeId();
            const falseNodeId = getNewNodeId();

            const trueNode = {
                id: trueNodeId,
                type: "custom",
                position: {
                    x: layoutLR ? newX + LRGap : newX,
                    y: layoutLR ? newY - 60 : newY + TBGap,
                },
                data: {
                    action: "action",
                    app: "Select an app",

                },
            };

            const falseNode = {
                id: falseNodeId,
                type: "custom",
                position: {
                    x: layoutLR ? newX + LRGap : newX,
                    y: layoutLR ? newY + 60 : newY + TBGap * 2,
                },
                data: {
                    action: "action",
                    app: "Select an app",
                },
            };

            newEdges.push(
                {
                    id: `e${newNodeId}-${trueNodeId}`,
                    source: newNodeId,
                    target: trueNodeId,
                    sourceHandle: "true",
                    type: "custom",
                },
                {
                    id: `e${newNodeId}-${falseNodeId}`,
                    source: newNodeId,
                    target: falseNodeId,
                    sourceHandle: "false",
                    type: "custom",
                }
            );

            setNodes([...updatedNodes, newNode, trueNode, falseNode]);
            setEdges(newEdges);

        } else {

            setNodes([...updatedNodes, newNode]);
            setEdges(newEdges);

        }

        setDrawerContext({
            source: "node",
            node: newNode,
            edge: null,
        });

        setDrawerOpen(true);
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

    const { fitView } = useReactFlow();
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

                fitView({ padding: 0.2, duration: 300 });
                setCanvasLayOut(direction)
            });
        },
        [nodes, edges, fitView, updateNodeInternals]
    );

    return {
        updateNodeData,
        deleteNode,
        createActionNode,
        onAddNode,
        openDrawerForNode,
        openDrawerFromAdd,
        onLayout,
    };
};