import { useState, useEffect } from "react";
import { Handle, Position, useReactFlow, useUpdateNodeInternals } from "@xyflow/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { FaPlus } from "react-icons/fa";
import FloatingEdge from "../floatingEdge/FloatingEdge";
import { __, sprintf } from "@wordpress/i18n";
import { formatLabel, integrations } from "@ZAPUtils/helper";
import ZAPIcon from "@ZAPComponents/ZAPIcon";
import { CATEGORY_LABELS, hueMix, nodeCategory, nodeHue } from "./helper";

const addPortBtnStyle = {
  display: "inline-flex",
  alignItems: "center",
  justifyContent: "center",
  width: 16,
  height: 16,
  borderRadius: "50%",
  border: "1px solid var(--zaplane-border-color)",
  background: "var(--zaplane-background)",
  color: "var(--zaplane-font-secondary-color)",
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

  const category = nodeCategory(data);
  const hue = nodeHue(data);

  const handleStyle = {
    width: 8,
    height: 8,
    borderRadius: "8px",
    background: hue,
    border: "none",
  };

  // React Flow caches each handle's measured position. When our handle set
  // changes shape — a node turning into a sub-node (its source handle moves to
  // the top `sub_out`), the agent's model/memory/tool ports appearing, ports
  // changing, or the layout flipping — that cache goes stale and edges attach at
  // the old spot. Re-measure whenever any of those inputs change.
  const updateNodeInternals = useUpdateNodeInternals();
  useEffect(() => {
    updateNodeInternals(id);
  }, [id, isAgent, isSubNode, isLR, ports.length, updateNodeInternals]);

  return (
    <div className="zaplane-custom-node-wrapper" onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)} style={{ position: 'relative', '--zaplane-node-hue': hue }}>
      
      {/* REMOVE CONTROL — a real button, so the whole target is clickable and it
          can be reached from the keyboard. The click used to sit on the icon
          itself, leaving the padding around it dead, and the chip filled with
          --zaplane-border-color: a border value used as a fill, which is why it
          read as a muddy grey square against the card. */}
      {canRemove && (
        <button
          type="button"
          aria-label={isTrigger ? __("Reset trigger", "zaplane") : __("Delete step", "zaplane")}
          title={isTrigger ? __("Reset trigger", "zaplane") : __("Delete step", "zaplane")}
          onClick={e => {
            e.stopPropagation();
            if (isTrigger) {
              data?.resetTrigger(id);
            } else {
              data?.deleteNode(id);
            }
          }}
          className="zaplane-node-remove"
          style={{
            position: 'absolute',
            top: -10,
            right: -10,
            zIndex: 10,
            // Kept mounted so it can take focus, but an invisible control must
            // not swallow clicks aimed at the card corner behind it.
            opacity: hovered ? 1 : 0,
            pointerEvents: hovered ? 'auto' : 'none',
          }}
        >
          <RiDeleteBin5Line style={{ width: "14px", height: "14px" }} />
        </button>
      )}

      {/* NODE BODY */}
      <div
        style={{
          minWidth: '236px',
          // A surface, not a rectangle of border. The card used to fill with
          // --zaplane-secondary-color, which is the same value the canvas paints,
          // so the border was the only thing separating the two.
          background: 'var(--zaplane-background)',
          border: `1px solid ${hueMix(category, 26)}`,
          borderRadius: '9px',
          boxShadow: '0 1px 2px 0 rgba(16, 24, 40, 0.05)',
          overflow: 'hidden',
          cursor: 'pointer',
          transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
        }}
        onClick={data.onOpenDrawer}
        className="zaplane-node-body"
      >
        {/* TARGET HANDLE */}
        {data?.action !== "trigger" && (
          <Handle 
            type="target" 
            position={isLR ? Position.Left : Position.Top} 
            style={handleStyle} 
          />
        )}

        {/* CATEGORY STRIP — what the node is, in a word and a hue. Replaces the
            floating label that used to sit above the card and collide with
            whatever was laid out there. Shown on an empty node too: it is still
            a trigger or an action, and that is the one thing worth knowing
            before it has been filled in. */}
        <div
            className="flex items-center gap-[6px]"
            style={{
              padding: '5px 11px',
              background: hueMix(category, 9, 'var(--zaplane-background)'),
              borderBottom: `1px solid ${hueMix(category, 18)}`,
              fontSize: '9.5px',
              fontWeight: 600,
              letterSpacing: '0.09em',
              textTransform: 'uppercase',
              color: hueMix(category, 78, 'var(--zaplane-font-color)'),
            }}
          >
            <span style={{ width: 5, height: 5, borderRadius: '50%', background: hue, display: 'block' }} />
            {__(CATEGORY_LABELS[category], "zaplane")}
        </div>

        {/* NODE CONTENT */}
        <div className="flex flex-row items-center gap-3 w-full" style={{ padding: '10px 11px' }}>
          <div className="zaplane-node-icon flex-shrink-0">
            {isSelectApp ? (
              <div style={{
                width: '32px',
                height: '32px',
                background: 'var(--zaplane-background)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                borderRadius: '8px',
                border: '1px dashed var(--zaplane-border-color)'
              }}>
                <FaPlus size={14} color="var(--zaplane-font-secondary-color)" />
              </div>
            ) : (
              <ZAPIcon icon={data?.icon} name={data.app} />
            )}
          </div>
          <div className="text-left flex-grow overflow-hidden">
            <span style={{ 
              fontSize: '14px', 
              fontWeight: '500', 
              color: 'var(--zaplane-font-color)',
              display: 'block',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap'
            }}>
              {isSelectApp ? __(data.app, "zaplane") : sprintf(__("%s", "zaplane"), formatLabel(data.event))}
            </span>

            <span style={{
              fontSize: '12px',
              color: 'var(--zaplane-font-secondary-color)',
              display: 'block',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap'
            }} className="zaplane-sub-title">
              {isSelectApp
                ? (isTrigger ? __("Choose a trigger", "zaplane") : __("Choose an action", "zaplane"))
                : sprintf(__("%s", "zaplane"), data.app)}
            </span>
          </div>
        </div>

        {/* SOURCE HANDLES — one connectable, labelled handle per output branch */}
        {isSubNode ? (
          <Handle type="source" id="sub_out" position={Position.Top} style={{ ...handleStyle, background: "var(--zaplane-cat-ai)" }} />
        ) : isMultiPort ? (
          ports.map((port, i) => {
            // Fixed spacing centred on the node so ports never overlap, however
            // many there are (they extend past the node body when needed).
            const spacing = 30;
            const offset = (i - (ports.length - 1) / 2) * spacing;
            const along = isLR
              ? { top: `calc(50% + ${offset}px)` }
              : { left: `calc(50% + ${offset}px)` };
            const portHasEdge = edges.some((e) => e.source === id && e.sourceHandle === port);
            return (
              <div key={port}>
                <Handle
                  type="source"
                  id={port}
                  position={isLR ? Position.Right : Position.Bottom}
                  style={{ ...handleStyle, ...along }}
                />
                <span
                  className="zaplane-port-label"
                  style={{
                    position: "absolute",
                    fontSize: 10,
                    color: "var(--zaplane-font-secondary-color)",
                    whiteSpace: "nowrap",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: 6,
                    background: "var(--zaplane-background)",
                    border: "1px solid var(--zaplane-border-color)",
                    borderRadius: 6,
                    padding: "1px 6px",
                    boxShadow: "0 1px 2px rgba(0,0,0,0.05)",
                    ...(isLR
                      ? { ...along, left: "100%", marginLeft: 14, transform: "translateY(-50%)" }
                      : { ...along, top: "100%", marginTop: 14, transform: "translateX(-50%)" }),
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

        {/* AI AGENT SUB-INPUT HANDLES — wire a chat model / memory / tools here.
            Each empty port renders as a column hanging off its handle dot:
            dashed stem → "+" button → label, so the affordance reads as attached
            to the node instead of floating. Once wired, only the label remains
            (the incoming sub-node edge replaces the stem and button). */}
        {isAgent &&
          SUB_PORTS.map((sp, i) => {
            const pos = `${((i + 1) / (SUB_PORTS.length + 1)) * 100}%`;
            const connected = edges.some((e) => e.target === id && e.targetHandle === sp.id);
            return (
              <div key={sp.id}>
                <Handle
                  type="target"
                  id={sp.id}
                  position={Position.Bottom}
                  style={{ ...handleStyle, background: "var(--zaplane-cat-ai)", left: pos }}
                />
                <div
                  style={{
                    position: "absolute",
                    top: "100%",
                    left: pos,
                    transform: "translateX(-50%)",
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "center",
                    pointerEvents: "none",
                  }}
                >
                  {!connected && (
                    <>
                      <span style={{ width: 0, height: 14, borderLeft: "1.5px dashed color-mix(in srgb, var(--zaplane-cat-ai) 45%, transparent)" }} />
                      <button
                        type="button"
                        title={sprintf(__("Add %s", "zaplane"), sp.label)}
                        onClick={(e) => { e.stopPropagation(); data.openDrawerFromAdd?.({ id: sp.id, type: "target" }); }}
                        style={{
                          display: "inline-flex",
                          alignItems: "center",
                          justifyContent: "center",
                          width: 22,
                          height: 22,
                          borderRadius: "50%",
                          border: "1px dashed var(--zaplane-cat-ai)",
                          background: "var(--zaplane-background)",
                          color: "var(--zaplane-cat-ai)",
                          cursor: "pointer",
                          padding: 0,
                          pointerEvents: "auto",
                          boxShadow: "0 1px 2px rgba(0,0,0,0.05)",
                        }}
                      >
                        <FaPlus size={9} />
                      </button>
                    </>
                  )}
                  <span
                    style={{
                      marginTop: connected ? 8 : 5,
                      fontSize: 10,
                      fontWeight: 500,
                      color: "var(--zaplane-cat-ai)",
                      whiteSpace: "nowrap",
                      // Was a hardcoded white, which reads as a light chip on the
                      // dark canvas. The label sits over the canvas, so it takes
                      // the canvas colour.
                      background: "var(--zaplane-canvas)",
                      borderRadius: 4,
                      padding: "0 4px",
                    }}
                  >
                    {sp.label}
                  </span>
                </div>
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
