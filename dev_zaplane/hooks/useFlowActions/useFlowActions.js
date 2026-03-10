import { useReactFlow, useUpdateNodeInternals } from "@xyflow/react";
import { getLayoutedElements } from "./utils/dagreLayout";
import { useCallback } from "react";
import { getBranchNodes } from "./utils/helper";

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

        const isTools = actionData?.mode === 'tools'
        console.log(isTools, actionData, 'a');

        const newNode = {
            id: newNodeId,
            type: "custom",
            position: { x: newX, y: newY },
            data: {
                action: isTools ? actionData.app : "action",
                ...actionData,
            },
        };
        const branchNodes = edge?.sourceHandle
            ? getBranchNodes(edge.target, edges)
            : null;
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


        //CONDITION NODE SUPPORT


        if (actionData.app === "Condition") {

            const trueNodeId = getNewNodeId();
            const falseNodeId = getNewNodeId();
            const extraLRSpace = 80;

            const trueNode = {
                id: trueNodeId,
                parentNodeId: newNodeId,
                type: "custom",
                port: true,
                position: {
                    x: layoutLR ? newX + LRGap + extraLRSpace : newX  ,
                    y: layoutLR ? newY - 60 - 30: newY + TBGap,
                },
                data: {
                    action: "action",
                    app: "Select an app",

                },
            };

            const falseNode = {
                id: falseNodeId,
                parentNodeId: newNodeId,
                type: "custom",
                port: false,
                position: {
                    x: layoutLR ? newX + LRGap + extraLRSpace: newX,
                    y: layoutLR ? newY + 60 + 30: newY + TBGap * 2,
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