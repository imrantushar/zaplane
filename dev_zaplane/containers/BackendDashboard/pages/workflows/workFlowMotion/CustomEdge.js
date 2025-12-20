import React from "react";
import { getBezierPath, getEdgeCenter } from "@xyflow/react";
import { FaTimes, FaPlus } from "react-icons/fa";

const CustomEdge = ({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  style = {},
  markerEnd,
  onEdgeDelete,
  onAddNode, // new callback
}) => {
  const [edgePath] = getBezierPath({
    sourceX,
    sourceY,
    sourcePosition,
    targetX,
    targetY,
    targetPosition,
  });

  const [centerX, centerY] = getEdgeCenter({
    sourceX,
    sourceY,
    targetX,
    targetY,
  });

  return (
    <>
      <path
        id={id}
        style={{ ...style, pointerEvents: "none" }}
        className="react-flow__edge-path"
        d={edgePath}
        markerEnd={markerEnd}
      />

      {/* Delete icon */}
      <foreignObject
        width={24}
        height={24}
        x={centerX - 12}
        y={centerY - 20}
        style={{ overflow: "visible" }}
      >
        <div
          style={{
            width: "24px",
            height: "24px",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            cursor: "pointer",
          }}
          onClick={() => onEdgeDelete(id)}
          title="Delete edge"
        >
          <FaTimes color="red" />
        </div>
      </foreignObject>

      {/* Add node icon */}
      <foreignObject
        width={24}
        height={24}
        x={centerX - 12}
        y={centerY + 4}
        style={{ overflow: "visible" }}
      >
        <div
          style={{
            width: "24px",
            height: "24px",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            cursor: "pointer",
          }}
          onClick={() => onAddNode(id)}
          title="Add node"
        >
          <FaPlus color="green" />
        </div>
      </foreignObject>
    </>
  );
};

export default CustomEdge;
