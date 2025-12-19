import React, { useCallback } from "react";
import {
    ReactFlow,
    addEdge,
    useNodesState,
    useEdgesState,
    Controls,
    Background,
    useReactFlow,
} from "@xyflow/react";
import "@xyflow/react/dist/base.css";

import CustomNode from "./CustomNode";
import CustomEdge from "./CustomEdge";

let id = 0;
const getId = () => `dndnode_${id++}`;

const nodeTypes = {
    custom: CustomNode,
};

export default function FlowCanvas() {
    const [nodes, setNodes, onNodesChange] = useNodesState([
        {
            id: '123',
            type: 'custom',
            data: { id: "123", label: "Select an app", icon: "",action:'Trigger' },
            position: { x: 125, y: 500 },
        }
    ]);

    const [edges, setEdges, onEdgesChange] = useEdgesState([]);
    const { screenToFlowPosition } = useReactFlow();

    const onConnect = useCallback(
        (params) => setEdges((eds) => addEdge({ ...params, type: "custom" }, eds)),
        []
    );

    const onDrop = useCallback(
        (event) => {
            event.preventDefault();
            const type = event.dataTransfer.getData("application/reactflow");
            if (!type) return;

            const position = screenToFlowPosition({
                x: event.clientX,
                y: event.clientY,
            });

            const newNode = {
                id: getId(),
                position,
                type: "custom",
                data: {
                    label: type,
                    order: nodes.length + 1
                },
            };

            setNodes((nds) => nds.concat(newNode));
        },
        [screenToFlowPosition, setNodes]
    );

    const onDragOver = (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
    };

    const isValidConnection = (connection) => {
        return !edges.some(
            (edge) =>
                edge.source === connection.source && edge.target === connection.target
        );
    };

    const onEdgeDelete = (edgeId) => {
        setEdges((eds) => eds.filter((e) => e.id !== edgeId));
    };

    const onAddNode = (edgeId) => {
        const edge = edges.find((e) => e.id === edgeId);
        if (!edge) return;

        const sourceNode = nodes.find((n) => n.id === edge.source);
        const targetNode = nodes.find((n) => n.id === edge.target);

        if (!sourceNode || !targetNode) return;
        const newNodePosition = {
            x: (sourceNode.position.x + targetNode.position.x) / 2,
            y: (sourceNode.position.y + targetNode.position.y) / 2,
        };

        const newNodeId = getId();
        const newNode = {
            id: newNodeId,
            type: "custom",
            position: newNodePosition,
            data: {
                label: "New Node",
                order: nodes.length + 1
            },
        };

        const newEdges = [
            ...edges.filter((e) => e.id !== edgeId),
            {
                id: `edge-${edge.source}-${newNodeId}`,
                source: edge.source,
                target: newNodeId,
                type: "custom",
            },
            {
                id: `edge-${newNodeId}-${edge.target}`,
                source: newNodeId,
                target: edge.target,
                type: "custom",
            },
        ];

        setNodes((nds) => nds.concat(newNode));
        setEdges(newEdges);
    };

    const edgeTypes = {
        custom: (props) => (
            <CustomEdge
                {...props}
                onEdgeDelete={onEdgeDelete}
                onAddNode={onAddNode}
            />
        ),
    };

    return (
        <div style={{ flex: 1, height: "100vh" }}>
            <ReactFlow
                nodes={nodes}
                edges={edges}
                nodeTypes={nodeTypes}
                edgeTypes={edgeTypes}
                isValidConnection={isValidConnection}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                onDrop={onDrop}
                onDragOver={onDragOver}
                fitView
                fitViewOnInit
                panOnDrag
                zoomOnScroll
                zoomOnDoubleClick
                nodesDraggable
                nodesConnectable
                elementsSelectable
                minZoom={0.5}
            >
                <Background />
                <Controls />
            </ReactFlow>
        </div>
    );
}
