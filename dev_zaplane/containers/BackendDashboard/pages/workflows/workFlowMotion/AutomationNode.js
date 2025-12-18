import React from "react";
import { Handle, Position } from "@xyflow/react";

const AutomationNode = ({ data, id, parentNode }) => {
  return (
    <div
      className="auto-node"
      style={{
        border: data.borderColor ? `2px solid ${data.borderColor}` : "1px solid #e0e0e0",
        borderRadius: "6px",
        position: "relative",
        background: "white",
      }}
    >


      {/* Header */}
      <div
        className="auto-node-header"
        style={{
          backgroundColor: data.headerColor || "#e3e7ff",
          borderRadius: data.isContainer ? "6px" : "6px 6px 0 0",
          padding: "8px 10px",
        }}
      >
        <div className="auto-node-title">{data.title}</div>
        {data.buttonText && (
          <button className="auto-node-btn" onClick={data.onClick}>
            {data.buttonText}
          </button>
        )}
      </div>

      {/* Body */}
      <div className="auto-node-body">
       <div className="auto-node-desc">{data.description}</div>
      </div>

      {/* Handles (non-container nodes) */}

        <>
          <Handle type="target" position={Position.Top} className="auto-handle" />
          <Handle type="source" position={Position.Bottom} className="auto-handle" />
        </>
    </div>
  );
};
export default AutomationNode;
