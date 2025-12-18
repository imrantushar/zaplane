import { useState, useCallback } from "react";
import {
  ReactFlow,
  addEdge,
  applyEdgeChanges,
  applyNodeChanges,
} from "@xyflow/react";
import "@xyflow/react/dist/base.css";
import "./styles.scss";
import AutomationNode from "./AutomationNode";

import { __ } from "@wordpress/i18n";


const nodeTypes = {
  automation: AutomationNode,
};

const WorkflowMotion = () => {


  const [nodes, setNodes] = useState([
    {
      id: "hello",
      type: "automation",
      position: { x: 10, y: 20 },
      data: {
        title: __("Hello", "zencrm"),
        buttonText: __("Set Lists", "zencrm"),
        headerColor: "#e3e7ff",
      },
    },
    
          {
            id: "applyTagNode",
            type: "automation",
            position: { x: 10, y: 200 },
            data: {
              title: "Apply Tag",
              description: "Template",
              headerColor: "#fff",
              onDelete: () => deleteNode("yesBranchContainer", "applyTagNode"),
            },
          },
  
  ]);

  const [edges, setEdges] = useState([
    { id: "e0", source: "hello", target: "applyTagNode" },
  
  ]);
  const onDrop = useCallback(
  (event) => {
    event.preventDefault();

    const reactFlowBounds = event.currentTarget.getBoundingClientRect();
    const nodeData = JSON.parse(event.dataTransfer.getData("application/reactflow"));

    if (!nodeData) return;

    const position = {
      x: event.clientX - reactFlowBounds.left,
      y: event.clientY - reactFlowBounds.top,
    };

    const newNode = {
      id: `node_${+new Date()}`,
      type: nodeData.type,
      position,
      data: { title: nodeData.title, headerColor: "#e3e7ff" },
    };

    setNodes((nds) => nds.concat(newNode));
  },
  [setNodes]
);


  return (
    <>
      <div style={{ height: "850px", width: "100%" }}>
        <ReactFlow
          nodes={nodes}
          edges={edges}
          nodeTypes={nodeTypes}
          fitView
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
          proOptions={{ devTools: true }}
        />
      </div>
    
    </>
  );
};
export default WorkflowMotion;
