import { getBezierPath } from "@xyflow/react";
import { FaPlus } from "react-icons/fa";

const FloatingEdge = ({ sourceX, sourceY, openDrawerFromAdd }) => {
  const targetX = sourceX + 140;
  const targetY = sourceY;

  const [edgePath] = getBezierPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition: "right",
    targetPosition: "left",
  });
  return (
    <>
      <path
        d={edgePath}
        fill="none"
        stroke="#bdbdbd"
        strokeWidth={2}
        strokeDasharray="6 6"
      />

      <foreignObject
        width={32}
        height={32}
        x={targetX - 16}
        y={targetY - 16}
      >
        <div
          onClick={openDrawerFromAdd}
          style={{
            width: 32,
            height: 32,
            borderRadius: "50%",
            border: "2px dashed #bdbdbd",
            background: "#fff",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            cursor: "pointer",
            position: "absolute",
            top: 0,          
            right: -80,
          }}
        >
          <FaPlus />
        </div>
      </foreignObject>
    </>
  );
};

export default FloatingEdge;
