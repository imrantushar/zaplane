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

let id = 0;
const getId = () => `dndnode_${id++}`;
const nodeTypes = {
  custom: CustomNode,
};
export default function FlowCanvas() {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const { screenToFlowPosition } = useReactFlow();

  const onConnect = useCallback(
    (params) => setEdges((eds) => addEdge(params, eds)),
    []
  );

  const onDrop = useCallback(
    (event) => {
      event.preventDefault();

      const type = event.dataTransfer.getData(
        "application/reactflow"
      );
      if (!type) return;

      const position = screenToFlowPosition({
        x: event.clientX,
        y: event.clientY,
      });

      const newNode = {
        id: getId(),
        position,
        data: { label: type },
       type: "custom",
      };

      setNodes((nds) => nds.concat(newNode));
    },
    [screenToFlowPosition]
  );

  const onDragOver = (event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = "move";
  };

  return (
    <div style={{ flex: 1 }}>
      <ReactFlow
        nodes={nodes}
        nodeTypes={nodeTypes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onDrop={onDrop}
        onDragOver={onDragOver}
        proOptions={{ devTools: true }}
        fitView
        fitViewOnInit
        panOnDrag={true}
          preventScrolling={true}
          nodesDraggable={true}
          nodesConnectable={true}
          elementsSelectable={true}
          selectNodesOnDrag={true}
          zoomOnScroll={true}
          zoomOnDoubleClick={true}
          minZoom={1}
          panOnScroll={true}

      >
        <Background />
        <Controls />
      </ReactFlow>
    </div>
  );
}
