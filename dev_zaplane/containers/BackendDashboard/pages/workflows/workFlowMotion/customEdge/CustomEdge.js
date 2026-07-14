import React from "react";
import { getSmoothStepPath } from "@xyflow/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { IoAddSharp } from "react-icons/io5";
import "./styles.scss";
const CustomEdge = ({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  sourceHandleId,
  style = {},
  markerEnd,
  onEdgeDelete,
  onAddNode
}) => {
  const [edgePath, labelX, labelY] = getSmoothStepPath({
    sourceX,
    sourceY,
    sourcePosition,
    targetX,
    targetY,
    targetPosition,
    borderRadius: 10
  });
  let label = null;
  if (sourceHandleId === "true") label = "Yes";
  if (sourceHandleId === "false") label = "No";
  const isFalse = label === "No";
  const markerId = `zaplane-arrow-${id}`;
  return <g className="zaplane-custom-edge">
      <defs>
        <marker id={markerId} markerWidth="12" markerHeight="12" refX="8" refY="4" orient="auto" markerUnits="strokeWidth">
          <path d="M1,1 L8,4 L1,7" fill="none" stroke="var(--zaplane-primary)" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
        </marker>
      </defs>

      {/* Base (soft) path with an arrowhead showing direction */}
      <path id={id} style={{
      ...style,
      pointerEvents: "none"
    }} className="react-flow__edge-path zaplane-edge-base" d={edgePath} markerEnd={markerEnd || `url(#${markerId})`} />

      {/* Wide invisible hit-area so hover is easy to trigger */}
      <path d={edgePath} className="zaplane-edge-hit" />

      {/* Animated flow overlay — moving dashes show the data direction */}
      <path d={edgePath} className="zaplane-edge-flow" style={{ pointerEvents: "none" }} />

      {/* YES / NO LABEL */}
      {label && <foreignObject width={30} height={20} x={labelX - 15} y={labelY - 10} style={{
      overflow: "visible"
    }}>
          <span style={{background: isFalse ? '#FEF2F2' : '#defce9', color: isFalse ? '#ef4444' : '#22c55e', border: `1px solid ${isFalse ? '#ef4444' : '#22c55e'}`, display:'inline-block', padding:'2px 6px'}} className="text-[12px] text-center rounded-[10px]">
            {label}
          </span>
        </foreignObject>}

      {/* Delete + add-step controls, grouped into a single pill so they can't overlap */}
      {!label && <foreignObject className="zaplane-edge-actions" width={68} height={32} x={labelX - 34} y={labelY - 16} style={{
      overflow: "visible"
    }}>
          <div style={{pointerEvents:'auto'}} className="flex items-center h-8 bg-white border border-[var(--zaplane-border-color)] rounded-[4px] [box-shadow:var(--zaplane-shadow)]">
            <button type="button" onClick={() => onEdgeDelete(id)} aria-label="Delete connection" className="flex items-center justify-center w-8 h-8 rounded-[4px] text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-danger)] transition-colors cursor-pointer">
              <RiDeleteBin5Line size={14} />
            </button>
            <span className="w-px h-4 bg-[var(--zaplane-border-color)]" />
            <button type="button" onClick={() => onAddNode(id)} aria-label="Add step" className="flex items-center justify-center w-8 h-8 rounded-[4px] text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-primary)] transition-colors cursor-pointer">
              <IoAddSharp size={16} />
            </button>
          </div>
        </foreignObject>}

    </g>;
};
export default CustomEdge;
