import { useState } from "react";
import { Handle, Position, useReactFlow } from "@xyflow/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { FaRegCopy, FaPlus } from "react-icons/fa";
import FloatingEdge from "../floatingEdge/FloatingEdge";
import { __, sprintf } from "@wordpress/i18n";
import { formatLabel, integrations } from "@ZAPUtils/helper";
import ZAPIcon from "@ZAPComponents/ZAPIcon";

const addPortBtnStyle = {
  display: "inline-flex",
  alignItems: "center",
  justifyContent: "center",
  width: 16,
  height: 16,
  borderRadius: "50%",
  border: "1px solid #9CA3AF",
  background: "#fff",
  color: "#6B7280",
  cursor: "pointer",
  padding: 0,
  pointerEvents: "auto",
  flexShrink: 0,
};

// Ports (output branches) declared by the node's action in the manifest, e.g.
// router → path_1..fallback, condition → true/false, iterator → loop/done.
const getNodePorts = (data) => {
  const integ = integrations?.apps?.[data?.app] || integrations?.tools?.[data?.app];
  const outputs = integ?.actions?.[data?.event]?.outputs || [];
  // "main" is the implicit single output — not a branch.
  const branches = outputs.filter((p) => p && p !== "main");

  // Router: one path per configured route (+ fallback), derived from the
  // dynamic `routes` repeater. A fresh router still shows one path to build on.
  if (data?.app === "router") {
    const routes = Array.isArray(data?.config?.routes) ? data.config.routes : [];
    const active = Array.from({ length: Math.max(1, routes.length) }, (_, i) => `path_${i + 1}`);
    return [...active, "fallback"];
  }

  return branches;
};
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
  const ports = getNodePorts(data);
  const isMultiPort = ports.length > 1;
  // The AI Agent accepts sub-nodes wired into its bottom: a chat model, a memory
  // store, and any number of tool (action) nodes.
  const isAgent = data?.app === "ai-agent";
  // A node wired into an agent's tool/memory/model handle is a "sub-node": it
  // hangs off the agent, not the main flow, and connects from its top.
  const isSubNode = edges.some(
    (e) => e.source === id && (e.targetHandle === "ai_tool" || e.targetHandle === "ai_memory" || e.targetHandle === "ai_model")
  );
  const SUB_PORTS = [
    { id: "ai_model", label: "Chat Model" },
    { id: "ai_memory", label: "Memory" },
    { id: "ai_tool", label: "Tools" },
  ];
  const node = nodes.find(n => n.id === id);
  const hasPort = node?.port === undefined;
  const isTrigger = data?.action === "trigger";
  const canRemove = isTrigger ? !isSelectApp : hasPort;

  const handleStyle = {
    width: 8,
    height: 8,
    borderRadius: "8px",
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

      {/* DELETE BUTTON */}
      {canRemove && hovered && (
        <div
          style={{
            position: 'absolute',
            top: -10,
            right: -10,
            zIndex: 10,
            pointerEvents: 'auto',
            boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)',
          }}
          className="flex flex-row items-center bg-[var(--zaplane-border-color)] text-[var(--zaplane-font-color)] p-[6px] rounded-[4px] cursor-pointer"
        >
          <RiDeleteBin5Line style={{ width: "16px", height: "16px" }} onClick={e => {
            e.stopPropagation();
            if (isTrigger) {
              data?.resetTrigger(id);
            } else {
              data?.deleteNode(id);
            }
          }} className="cursor-pointer" />
          {!data?.action && <FaRegCopy style={{ width: "16px", height: "16px" }} />}
        </div>
      )}

      {/* NODE BODY */}
      <div 
        style={{
          padding: '12px 16px',
          minWidth: '220px',
          minHeight: '64px',
          background: '#F9FAFB',
          border: '1px solid #E5E7EB',
          borderRadius: '8px',
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

        {/* SOURCE HANDLES — one connectable, labelled handle per output branch */}
        {isSubNode ? (
          <Handle type="source" id="sub_out" position={Position.Top} style={{ ...handleStyle, background: "#a855f7" }} />
        ) : isMultiPort ? (
          ports.map((port, i) => {
            const pos = `${((i + 1) / (ports.length + 1)) * 100}%`;
            const portHasEdge = edges.some((e) => e.source === id && e.sourceHandle === port);
            return (
              <div key={port}>
                <Handle
                  type="source"
                  id={port}
                  position={isLR ? Position.Right : Position.Bottom}
                  style={{
                    ...handleStyle,
                    top: isLR ? pos : undefined,
                    left: !isLR ? pos : undefined,
                  }}
                />
                <span
                  className="zaplane-port-label"
                  style={{
                    position: "absolute",
                    fontSize: 10,
                    color: "#6B7280",
                    whiteSpace: "nowrap",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: 6,
                    ...(isLR
                      ? { top: pos, left: "100%", marginLeft: 10, transform: "translateY(-50%)" }
                      : { left: pos, top: "100%", marginTop: 10, transform: "translateX(-50%)" }),
                  }}
                >
                  {formatLabel(port)}
                  {!portHasEdge && (
                    <button
                      type="button"
                      title={__("Add step", "zaplane")}
                      onClick={(e) => { e.stopPropagation(); data.openDrawerFromAdd?.({ id: port, type: "source" }); }}
                      style={addPortBtnStyle}
                    >
                      <FaPlus size={8} />
                    </button>
                  )}
                </span>
              </div>
            );
          })
        ) : (
          <Handle
            type="source"
            position={isLR ? Position.Right : Position.Bottom}
            style={handleStyle}
          />
        )}

        {/* AI AGENT SUB-INPUT HANDLES — wire a chat model / memory / tools here */}
        {isAgent &&
          SUB_PORTS.map((sp, i) => {
            const pos = `${((i + 1) / (SUB_PORTS.length + 1)) * 100}%`;
            return (
              <div key={sp.id}>
                <Handle
                  type="target"
                  id={sp.id}
                  position={Position.Bottom}
                  style={{ ...handleStyle, background: "#a855f7", left: pos }}
                />
                <span
                  style={{
                    position: "absolute",
                    top: "100%",
                    left: pos,
                    transform: "translateX(-50%)",
                    marginTop: 10,
                    fontSize: 9,
                    color: "#a855f7",
                    whiteSpace: "nowrap",
                    display: "inline-flex",
                    flexDirection: "column",
                    alignItems: "center",
                    gap: 4,
                  }}
                >
                  {sp.label}
                  <button
                    type="button"
                    title={__("Add", "zaplane")}
                    onClick={(e) => { e.stopPropagation(); data.openDrawerFromAdd?.({ id: sp.id, type: "target" }); }}
                    style={{ ...addPortBtnStyle, borderColor: "#a855f7", color: "#a855f7" }}
                  >
                    <FaPlus size={8} />
                  </button>
                </span>
              </div>
            );
          })}
      </div>

      {/* ADD NODE BUTTON — single-output nodes get the inline "+"; multi-port
          nodes are wired by dragging from each branch handle. */}
      {!hasOutgoingEdge && !isMultiPort && (
        <FloatingEdge openDrawerFromAdd={data.openDrawerFromAdd} canvasLayout={canvasLayout} />
      )}
    </div>
  );
}
