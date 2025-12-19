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
    { id: "123form", label: "123FormBuilder", icon: "🧾" },
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
        data: { label: type, order: nodes.length + 1 },
      };

      setNodes((nds) => nds.concat(newNode));
    },
    [screenToFlowPosition, setNodes, nodes]
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

  const edgeTypes = {
    custom: (props) => <CustomEdge {...props} onEdgeDelete={onEdgeDelete} />,
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
