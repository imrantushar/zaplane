import React from "react";
import { getBezierPath, getEdgeCenter } from "@xyflow/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { IoAddSharp } from "react-icons/io5";
import "./styles.scss";
const CustomEdge = ({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourceHandleId,
  style = {},
  markerEnd,
  onEdgeDelete,
  onAddNode
}) => {
  const [edgePath] = getBezierPath({
    sourceX,
    sourceY,
    targetX,
    targetY
  });
  const [centerX, centerY] = getEdgeCenter({
    sourceX,
    sourceY,
    targetX,
    targetY
  });
  let label = null;
  if (sourceHandleId === "true") label = "Yes";
  if (sourceHandleId === "false") label = "No";
  const isFalse = label === "No";
  return <g className="zaplane-custom-edge">
      <path id={id} style={{
      ...style,
      pointerEvents: "none"
    }} className="react-flow__edge-path" d={edgePath} markerEnd={markerEnd} />

      {/* YES / NO LABEL */}
      {label && <foreignObject width={30} height={20} x={centerX - 20} y={centerY - 20} style={{
      overflow: "visible"
    }}>
          <span style={{background: isFalse ? '#FEF2F2' : '#defce9', color: isFalse ? '#ef4444' : '#22c55e', border: `1px solid ${isFalse ? '#ef4444' : '#22c55e'}`, margin:'10px 0 0 0', display:'inline-block', padding:'2px 6px'}} className="text-[12px] text-center rounded-[10px]">
            {label}
          </span>
        </foreignObject>}


      {!label && <>
          {/* Delete icon */}
          <foreignObject className="zaplane-edge-actions" width={24} height={24} x={centerX - 12} y={centerY - 20} style={{
        overflow: "visible"
      }}>
            <div style={{marginLeft:'-20px', marginTop:'4px', pointerEvents:'auto', height:'30px', width:'30px', boxShadow:'0 10px 15px -3px rgb(0 0 0 / 0.1)'}} onClick={() => onEdgeDelete(id)} className="flex flex-row items-center bg-white text-gray-700 p-[7px] rounded-full cursor-pointer">
              <RiDeleteBin5Line style={{width:"16px", height:"16px"}} className="cursor-pointer" />
            </div>
          </foreignObject>
          {/* Add node icon */}
          <foreignObject width={24} height={24} x={centerX - 12} y={centerY + 4} className="zaplane-edge-actions" style={{
        overflow: "visible"
      }}>
            <div style={{pointerEvents:'auto', height:'30px', width:'30px', boxShadow:'0 10px 15px -3px rgb(0 0 0 / 0.1)'}} onClick={() => onAddNode(id)} className="flex flex-row items-center bg-white text-gray-700 p-[7px] rounded-full cursor-pointer">
              <IoAddSharp style={{width:"16px", height:"16px"}} className="cursor-pointer" />
            </div>
          </foreignObject></>}

    </g>;
};
export default CustomEdge;