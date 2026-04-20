import { useState } from "react";
import { Handle, Position, NodeToolbar, useReactFlow } from "@xyflow/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { FaRegCopy } from "react-icons/fa";
import FloatingEdge from "../FloatingEdge/FloatingEdge";
import { __, sprintf } from "@wordpress/i18n";
import { formatLabel } from "@ZAPUtils/helper";
import ZAPIcon from "@ZAPComponents/ZAPIcon";
export default function CustomNode({
  id,
  data,
  canvasLayout,
  nodes
}) {
  const [hovered, setHovered] = useState(false);
  const {
    getEdges
  } = useReactFlow();
  const edges = getEdges();
  const hasOutgoingEdge = edges.some(e => e.source === id);
  const isLR = canvasLayout === "LR";
  const isSelectApp = data.app === "Select an app";
  const formattedAction = data?.action?.charAt(0).toUpperCase() + data?.action?.slice(1);
  const isCondition = data?.app === "condition";
  const node = nodes.find(n => n.id === id);
  const hasPort = node?.port === undefined;
  return <div style={{position:'relative'}} onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)}>
      {/* TOP TOOLBAR */}
      <NodeToolbar isVisible={true} position={Position.Top} align="start" offset={10}>
        <div className="flex flex-row items-center">
          <span className="zaplane-label rounded-full p-[4px 8px] font-[medium]">
            {formattedAction || "Action"}
          </span>
        </div>
      </NodeToolbar>

      {/* DELETE TOOLBAR */}
      {data.action !== "trigger" && hasPort && <NodeToolbar isVisible={hovered} position={Position.Bottom} align="center" offset={-3}>
          <div style={{marginTop:'4px', marginLeft: isLR ? '0' : '100px', pointerEvents:'auto', boxShadow:'0 10px 15px -3px rgb(0 0 0 / 0.1)'}} className="flex flex-row items-center bg-[var(--zaplane-border-color)] text-[var(--zaplane-font-color)] p-[6px] rounded-full cursor-pointer">
            <RiDeleteBin5Line style={{width:"16px", height:"16px"}} onClick={e => {
          e.stopPropagation();
          data?.deleteNode(id);
        }} className="cursor-pointer" />

            {!data?.action && <FaRegCopy style={{width:"16px", height:"16px"}} />}
          </div>
        </NodeToolbar>}

      {/* NODE BODY */}
      <div style={{padding:'16px 28px 16px 14px', width:'180px', height:'60px', boxShadow:'0 1px 3px 0 rgb(0 0 0 / 0.1)', background:'var(--zaplane-body-background)'}} onClick={data.onOpenDrawer} className="rounded-md flex text-center">
        {/* TARGET HANDLE */}
        {data?.action !== "trigger" && <Handle type="target" position={isLR ? Position.Left : Position.Top} style={{
        width: 10,
        height: 10,
        borderRadius: "50%",
        background: "var(--zaplane-primary)",
        border: "2px solid var(--zaplane-background)"
      }} />}

        {/* NODE CONTENT */}
        <div align="center" className="flex flex-row items-center gap-3">
          <ZAPIcon icon={data?.icon} name={data.app} />
        <div className="text-left flex-[1] w-[105px]">
            <span style={{overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap', display:'block'}}>
              {isSelectApp ? __(data.app, "zaplane") : sprintf(__("%s", "zaplane"), formatLabel(data.event))}
            </span>

            {!isSelectApp && <span style={{overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap', display:'block'}} className="zaplane-sub-title text-[14px] text-#454F59">
                {sprintf(__("%s", "zaplane"), data.app)}
              </span>}
          </div>
        </div>

        {/* SOURCE HANDLES */}

        {isCondition ? <>
            <Handle type="source" id="true" position={isLR ? Position.Right : Position.Bottom} style={{
          top: isLR ? "50%" : undefined,
          left: !isLR ? "50%" : undefined,
          width: 10,
          height: 10,
          borderRadius: "50%",
          background: "var(--zaplane-primary)",
          border: "2px solid var(--zaplane-background)"
        }} />
            <Handle type="source" id="false" position={isLR ? Position.Right : Position.Bottom} style={{
          top: isLR ? "50%" : undefined,
          left: !isLR ? "50%" : undefined,
          width: 10,
          height: 10,
          borderRadius: "50%",
          background: "var(--zaplane-primary)",
          border: "2px solid var(--zaplane-background)"
        }} />
          </> : <Handle type="source" position={isLR ? Position.Right : Position.Bottom} style={{
        width: 10,
        height: 10,
        borderRadius: "50%",
        background: "var(--zaplane-primary)",
        border: "2px solid var(--zaplane-background)"
      }} />}
      </div>

      {/* ADD NODE BUTTON */}
      {!hasOutgoingEdge && !isCondition && <FloatingEdge openDrawerFromAdd={data.openDrawerFromAdd} canvasLayout={canvasLayout} />}
    </div>;
}