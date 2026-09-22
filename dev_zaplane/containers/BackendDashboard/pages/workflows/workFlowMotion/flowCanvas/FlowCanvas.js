import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from "react";
import { ReactFlow, addEdge, Controls, Background, ControlButton, ConnectionLineType } from "@xyflow/react";
import "@xyflow/react/dist/base.css";
import { __ } from '@wordpress/i18n';
import CustomEdge from "../customEdge/CustomEdge";
import ActionDrawer from "../ActionDrawer/ActionDrawer";
import { useFormikContext } from "formik";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { toggleFullscreenMode, mapGraphFromBackend } from "./helper";
import { canConnect, connectionToCard } from "./connect";
import ZAPLoading from "@ZAPComponents/Loading";
import { useFlowActions } from "@ZAPHooks/useFlowActions/useFlowActions";
import CustomNode from "../customNode/CustomNode";
import './styles.scss';
import { IoSwapHorizontal, IoSwapVerticalOutline } from "react-icons/io5";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import { getSingleWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import FlowTopBar from "./FlowTopBar/FlowTopBar";
import MissingConnections from "./MissingConnections";

// What the node and edge renderers need that changes from render to render. The
// renderers themselves are defined once, below. Building nodeTypes inside the
// component gave React Flow new components on every render, so it remounted
// every node each time the canvas re-rendered — which also reset their hover.
const CanvasContext = createContext(null);

const CanvasNode = props => {
  const canvas = useContext(CanvasContext);
  return <CustomNode {...props} data={{
    ...props.data,
    onOpenDrawer: () => canvas.openDrawerForNode(props),
    openDrawerFromAdd: (port) => canvas.openDrawerFromAdd(props, port),
    deleteNode: () => canvas.deleteNode(props.id),
    resetTrigger: () => canvas.resetTrigger(props.id),
    addTrigger: () => canvas.addTrigger(props.id)
  }} canvasLayout={canvas.canvasLayout} nodes={canvas.nodes} connecting={canvas.connecting} />;
};

const CanvasEdge = props => {
  const canvas = useContext(CanvasContext);
  return <CustomEdge {...props} onEdgeDelete={canvas.onEdgeDelete} onAddNode={canvas.onAddNode} />;
};

const nodeTypes = {
  custom: CanvasNode
};
const edgeTypes = {
  custom: CanvasEdge
};

// A line let go of on empty canvas at least this far (in canvas units) from its
// handle was dragged out on purpose, rather than a click on the handle.
const DRAG_OUT_DISTANCE = 40;

export default function FlowCanvas({
  id,
  nodes,
  setNodes,
  edges,
  setEdges,
  onEdgesChange,
  onNodesChange,
  getNewNodeId,
  workFlow,
  isFlowDirty,
  canvasLayout,
  setCanvasLayOut,
  onNavigateBack,
  renderTopBar
}) {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const {
    values,
    setFieldValue,
    handleSubmit
  } = useFormikContext();
  const [loading, setLoading] = useState(false);
  const containerRef = useRef(null);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [activeDrawer, setActiveDrawer] = useState(null);
  const {
    versions
  } = useSelector(state => state.workflows);
  useEffect(() => {
    if (!workFlow?.graph) return;
    const {
      nodes,
      edges
    } = mapGraphFromBackend(workFlow.graph);
    if (!nodes.length) return;
    setNodes(nodes);
    setEdges(edges);
  }, [workFlow?.graph]);
  const [drawerContext, setDrawerContext] = useState({
    source: null,
    node: null,
    edge: null
  });
  const activeVersionId = versions?.find(v => v.is_active)?.id;
  useEffect(() => {
    setLoading(true);
    dispatch(getSingleWorkFlow(id)).finally(() => setLoading(false));
  }, [id, activeVersionId]);
  const {
    updateNodeData,
    deleteNode,
    resetTrigger,
    addTrigger,
    handleAddAction,
    openDrawerForNode,
    openDrawerFromAdd,
    onLayout
  } = useFlowActions({
    values,
    nodes,
    setNodes,
    edges,
    setEdges,
    drawerContext,
    getNewNodeId,
    setDrawerContext,
    setDrawerOpen,
    setFieldValue,
    canvasLayout,
    setCanvasLayOut
  });
  const onAddNode = edgeId => {
    const edge = edges.find(e => e.id === edgeId);
    setDrawerContext({
      source: "edge",
      node: null,
      edge
    });
    setDrawerOpen(true);
  };
  const onConnect = useCallback(params => setEdges(eds => addEdge({
    ...params,
    type: "custom"
  }, eds)), []);

  // No line into a trigger, from a node to itself, twice, or round in a loop.
  const isValidConnection = connection => canConnect(nodes, edges, connection);

  // The line being dragged, if any: { nodeId, handleId, handleType }.
  const [dragging, setDragging] = useState(null);
  const onConnectStart = (event, { nodeId, handleId, handleType }) => {
    setDragging({ nodeId, handleId: handleId ?? null, handleType });
  };

  // Let go on a handle and onConnect has already joined the line. Let go anywhere
  // on a card and it joins that card. Let go on empty canvas and the step picker
  // opens, with the new step joined to where the line started.
  const onConnectEnd = (event, state) => {
    setDragging(null);
    if (!state || state.isValid || !state.fromNode || !state.fromHandle) return;

    const drag = {
      nodeId: state.fromNode.id,
      handleId: state.fromHandle.id ?? null,
      handleType: state.fromHandle.type
    };
    const point = event?.changedTouches?.[0] || event;
    const card = document.elementFromPoint(point.clientX, point.clientY)?.closest(".react-flow__node");

    if (card) {
      const connection = connectionToCard(nodes, edges, drag, card.getAttribute("data-id"));
      if (canConnect(nodes, edges, connection)) {
        onConnect(connection);
      }
      return;
    }

    const moved = Math.hypot((state.to?.x ?? 0) - (state.from?.x ?? 0), (state.to?.y ?? 0) - (state.from?.y ?? 0));
    if (drag.handleType === "source" && drag.handleId !== "sub_out" && moved > DRAG_OUT_DISTANCE) {
      openDrawerFromAdd({ id: drag.nodeId, data: state.fromNode.data }, drag.handleId ? { id: drag.handleId, type: "source" } : null);
    }
  };

  // Cards ask this, while a line is dragged, whether a drop on them would connect.
  const connecting = dragging ? {
    ...dragging,
    accepts: cardId => canConnect(nodes, edges, connectionToCard(nodes, edges, dragging, cardId))
  } : null;

  const onEdgeDelete = edgeId => {
    setEdges(eds => eds.filter(e => e.id !== edgeId));
  };

  const canvas = {
    nodes,
    canvasLayout,
    connecting,
    openDrawerForNode,
    openDrawerFromAdd,
    deleteNode,
    resetTrigger,
    addTrigger,
    onEdgeDelete,
    onAddNode
  };

  // console.log(nodes, 'all nodes',);
  // console.log(edges, 'all edges');
  return <div ref={containerRef}
    transition="margin-right 0.4s ease"
    className={`zaplane_flowcanvas flex-[1]${dragging ? " is-connecting" : ""}`}
    style={{ display: 'flex', flexDirection: 'column', height: 'calc(100vh - 32px)' }}>

    <FlowTopBar workFlow={workFlow} isFullscreen={isFullscreen} toggleFullscreen={() => toggleFullscreenMode(containerRef, isFullscreen, setIsFullscreen)} id={id} values={values} setFieldValue={setFieldValue} handleSubmit={handleSubmit} activeDrawer={activeDrawer} setActiveDrawer={setActiveDrawer} isFlowDirty={isFlowDirty} onNavigateBack={onNavigateBack} renderTopBar={renderTopBar} />

    <div style={{ flex: 1, overflow: 'hidden', position: 'relative' }}>
    {!loading && <MissingConnections nodes={nodes} status={values?.status || workFlow?.workflow?.status} onOpenStep={openDrawerForNode} />}
    {loading ? <ZAPLoading /> : <CanvasContext.Provider value={canvas}>
    <ReactFlow nodes={nodes} edges={edges} nodeTypes={nodeTypes} edgeTypes={edgeTypes} isValidConnection={isValidConnection} onNodesChange={onNodesChange} onEdgesChange={onEdgesChange} onConnect={onConnect} onConnectStart={onConnectStart} onConnectEnd={onConnectEnd}
      // A line snaps to a handle from this far away, so it needn't land on the dot.
      connectionRadius={36}
      connectionLineType={ConnectionLineType.SmoothStep}
      connectionLineStyle={{
        stroke: "var(--zaplane-primary)",
        strokeWidth: 2,
        strokeDasharray: "6 4"
      }}
      // fitView
      // fitViewOnInit
      //  defaultViewport={{ x: 0, y: 0, zoom: 1 }}
      fitViewOptions={{
        minZoom: 0.5,
        maxZoom: 1
      }} panOnDrag zoomOnScroll zoomOnDoubleClick nodesDraggable nodesConnectable elementsSelectable>

      <Background  />
      <div className="zaplane-canvas-layout-icon flex">
        <ZAPTooltip content={__("Vertical layout", "zaplane")} positioning={{
          placement: "top",
          offset: {
            mainAxis: 8,
            crossAxis: 25
          }
        }}>
          <ControlButton onClick={() => onLayout("TB")} className={`react-flow__controls-button ${canvasLayout === "TB" ? "zaplane-layout-active" : ""}`}>
            <IoSwapVerticalOutline size={16} />
          </ControlButton>
        </ZAPTooltip>
        <ZAPTooltip content={__("Horizontal layout", "zaplane")} positioning={{
          placement: "top",
          offset: {
            mainAxis: 8,
            crossAxis: 25
          }
        }}>
          <ControlButton onClick={() => onLayout("LR")} className={`react-flow__controls-button ${canvasLayout === "LR" ? "zaplane-layout-active" : ""}`}>
            <IoSwapHorizontal size={16} />
          </ControlButton>
        </ZAPTooltip>

      </div>
      <Controls position="top-left" className="zaplane-canvas-controls" />
    </ReactFlow>
    </CanvasContext.Provider>}
    </div>

    <ActionDrawer open={drawerOpen} isFullscreen={isFullscreen} onClose={() => {
      setDrawerOpen(false);
      setDrawerContext({
        source: null,
        node: null,
        edge: null
      });
    }} context={drawerContext} handleAddAction={handleAddAction} updateNodeData={updateNodeData} workFlow={workFlow} nodes={nodes} edges={edges} />

  </div>;
}
