import { useState } from "react";
import { Handle, Position, NodeToolbar, useReactFlow } from "@xyflow/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { FaRegCopy, FaPlus } from "react-icons/fa";
import FloatingEdge from "../floatingEdge/FloatingEdge";
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

  const handleStyle = {
    width: 8,
    height: 8,
    borderRadius: "50%",
    background: "#6366f1", // purple-dot color
    border: "none",
  };

  return (
    <div className="zaplane-custom-node-wrapper" onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)} style={{ position: 'relative' }}>
      
      {/* NODE LABEL */}
      <div className="zaplane-node-label" style={{ 
        position: 'absolute', 
        top: -25, 
        left: 0, 
        fontSize: '13px', 
        fontWeight: '500', 
        color: '#1f2937' 
      }}>
        {formattedAction || "Action"}
      </div>

      {/* DELETE TOOLBAR */}
      {data.action !== "trigger" && hasPort && (
        <NodeToolbar isVisible={hovered} position={Position.Bottom} align="center" offset={-3}>
          <div style={{ marginTop: '4px', marginLeft: isLR ? '0' : '100px', pointerEvents: 'auto', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)' }} className="flex flex-row items-center bg-[var(--zaplane-border-color)] text-[var(--zaplane-font-color)] p-[6px] rounded-full cursor-pointer">
            <RiDeleteBin5Line style={{ width: "16px", height: "16px" }} onClick={e => {
              e.stopPropagation();
              data?.deleteNode(id);
            }} className="cursor-pointer" />
            {!data?.action && <FaRegCopy style={{ width: "16px", height: "16px" }} />}
          </div>
        </NodeToolbar>
      )}

      {/* NODE BODY */}
      <div 
        style={{
          padding: '12px 16px',
          minWidth: '220px',
          minHeight: '64px',
          background: '#F9FAFB',
          border: '1px solid #E5E7EB',
          borderRadius: '12px',
          boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
          display: 'flex',
          alignItems: 'center',
          cursor: 'pointer',
          transition: 'all 0.2s ease',
        }} 
        onClick={data.onOpenDrawer}
        className="zaplane-node-body hover:border-indigo-400"
      >
        {/* TARGET HANDLE */}
        {data?.action !== "trigger" && (
          <Handle 
            type="target" 
            position={isLR ? Position.Left : Position.Top} 
            style={handleStyle} 
          />
        )}

        {/* NODE CONTENT */}
        <div className="flex flex-row items-center gap-3 w-full">
          <div className="zaplane-node-icon flex-shrink-0">
            {isSelectApp ? (
              <div style={{
                width: '32px',
                height: '32px',
                background: '#FFFFFF',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                borderRadius: '8px',
                border: '1px solid #E5E7EB'
              }}>
                <FaPlus size={14} color="#6B7280" />
              </div>
            ) : (
              <ZAPIcon icon={data?.icon} name={data.app} />
            )}
          </div>
          <div className="text-left flex-grow overflow-hidden">
            <span style={{ 
              fontSize: '14px', 
              fontWeight: '500', 
              color: '#374151',
              display: 'block',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap'
            }}>
              {isSelectApp ? __(data.app, "zaplane") : sprintf(__("%s", "zaplane"), formatLabel(data.event))}
            </span>

            {!isSelectApp && (
              <span style={{ 
                fontSize: '12px', 
                color: '#6B7280',
                display: 'block',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap'
              }} className="zaplane-sub-title">
                {sprintf(__("%s", "zaplane"), data.app)}
              </span>
            )}
          </div>
        </div>

        {/* SOURCE HANDLES */}
        {isCondition ? (
          <>
            <Handle 
              type="source" 
              id="true" 
              position={isLR ? Position.Right : Position.Bottom} 
              style={{
                ...handleStyle,
                top: isLR ? "40%" : undefined,
                left: !isLR ? "40%" : undefined,
              }} 
            />
            <Handle 
              type="source" 
              id="false" 
              position={isLR ? Position.Right : Position.Bottom} 
              style={{
                ...handleStyle,
                top: isLR ? "60%" : undefined,
                left: !isLR ? "60%" : undefined,
              }} 
            />
          </>
        ) : (
          <Handle 
            type="source" 
            position={isLR ? Position.Right : Position.Bottom} 
            style={handleStyle} 
          />
        )}
      </div>

      {/* ADD NODE BUTTON */}
      {!hasOutgoingEdge && !isCondition && (
        <FloatingEdge openDrawerFromAdd={data.openDrawerFromAdd} canvasLayout={canvasLayout} />
      )}
    </div>
  );
}