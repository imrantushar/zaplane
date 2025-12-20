import React from "react";
import { ReactFlowProvider } from "@xyflow/react";
import Sidebar from "./Sidebar";
import FlowCanvas from "./FlowCanvas";

export default function Workflows() {
  return (
    <ReactFlowProvider>
      <div style={{ display: "flex", height: "100vh" }}>
        <Sidebar />
        <FlowCanvas />
      </div>
    </ReactFlowProvider>
  );
}
