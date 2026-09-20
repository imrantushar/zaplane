import React from "react";
import { getSmoothStepPath, useReactFlow } from "@xyflow/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { IoAddSharp } from "react-icons/io5";
import { nodeHue } from "../customNode/helper";
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
  source,
  data,
  selected,
  style = {},
  markerEnd,
  onEdgeDelete,
  onAddNode
}) => {
  // An edge is drawn in the category hue of the node it leaves, so a Router's
  // branches and an AI agent's sub-nodes read apart without hovering anything.
  const { getNode } = useReactFlow();
  const hue = nodeHue(getNode(source)?.data || {});
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
  const branchColor = isFalse ? "var(--zaplane-danger)" : "var(--zaplane-success)";
  const markerId = `zaplane-arrow-${id}`;
  const classes = ["zaplane-custom-edge"];
  if (selected) classes.push("selected");
  if (data?.running) classes.push("is-running");

  return <g className={classes.join(" ")} style={{ "--zaplane-edge-hue": hue }}>
      <defs>
        {/* refX sits the arrow back from the path's end so it clears the target
            handle instead of landing on top of it. */}
        <marker id={markerId} markerWidth="10" markerHeight="10" refX="9" refY="3.5" orient="auto" markerUnits="strokeWidth">
          <path d="M1,1 L7,3.5 L1,6" fill="none" stroke={hue} strokeOpacity="0.55" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </marker>
      </defs>

      {/* The one visible stroke, with an arrowhead for direction */}
      <path id={id} style={{
      ...style,
      pointerEvents: "none"
    }} className="react-flow__edge-path zaplane-edge-base" d={edgePath} markerEnd={markerEnd || `url(#${markerId})`} />

      {/* Wide invisible hit-area so hover is easy to trigger */}
      <path d={edgePath} className="zaplane-edge-hit" />

      {/* Only painted while the edge is actually carrying a run */}
      {data?.running && <path d={edgePath} className="zaplane-edge-flow" style={{ pointerEvents: "none" }} />}

      {/* YES / NO LABEL */}
      {label && <foreignObject width={30} height={20} x={labelX - 15} y={labelY - 10} style={{
      overflow: "visible"
    }}>
          {/* A branch label is semantic — it says which way the condition went —
              so it uses the success/danger tokens rather than a category hue,
              and stays readable when the surrounding edge is coloured. */}
          <span style={{
            background: `color-mix(in srgb, ${branchColor} 12%, var(--zaplane-background))`,
            color: branchColor,
            border: `1px solid color-mix(in srgb, ${branchColor} 45%, transparent)`,
            display: 'inline-block',
            padding: '2px 6px',
          }} className="text-[12px] text-center rounded-[10px]">
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
