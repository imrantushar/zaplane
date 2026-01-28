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
 * -canvas flow layout maintai LR and TB
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
 * @param {function} onLayout             - changing layout LR and LB
 *
 * 
 * Returns:
 * @returns {object} - {
 *   updateNodeData, deleteNode, createActionNode, onAddNode, openDrawerForNode, openDrawerFromAdd,onLayout
 * }
 */

import { useReactFlow, useUpdateNodeInternals } from "@xyflow/react";
import { getLayoutedElements } from "hooks/useFlowActions/Helper/dagreLayout";
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
    setCanvasLayout,
    canvasLayout
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
        const layoutLR = canvasLayout === 'LR';
        const LRGap = 250
        const TBGap = 98
        console.log(layoutLR, 'layot');
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
            if (layoutLR) {
                // Only shift nodes to the right of new node
                if (n.position.x >= newX) {
                    return { ...n, position: { ...n.position, x: n.position.x + LRGap } };
                }
            } else {
                // Only shift nodes below the new node
                if (n.position.y >= newY) {
                    return { ...n, position: { ...n.position, y: n.position.y + TBGap } };
                }
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
    //meanagin layout flow canvas 
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
                setCanvasLayout(direction);
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
        onLayout
    };
};
