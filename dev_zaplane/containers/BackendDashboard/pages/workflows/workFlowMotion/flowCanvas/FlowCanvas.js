import React, { useCallback, useEffect, useRef, useState } from "react";
import {
    ReactFlow,
    addEdge,
    Controls,
    Background,
    ControlButton,
} from "@xyflow/react";
import "@xyflow/react/dist/base.css";
import { __ } from '@wordpress/i18n';
import CustomEdge from "../CustomEdge/CustomEdge";
import ActionDrawer from "../ActionDrawer/ActionDrawer";
import { useFormikContext } from "formik";
import {
    Box,
    Flex,
} from "@chakra-ui/react";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { toggleFullscreenMode, mapGraphFromBackend, formatTime } from "./helper";
import ZAPLoading from "@ZAPComponents/Loading";
import { statusOptions } from "../../helper";
import { useFlowActions } from "@ZAPHooks/useFlowActions/useFlowActions";
import CustomNode from "../customNode/CustomNode";
import './styles.scss'
import { IoSwapHorizontal, IoSwapVerticalOutline } from "react-icons/io5";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import { getSingleWorkFlow, updateWorkFlowLayout, updateWorkFlowStatus, updateWorkFlowTitle } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import FlowTopBar from "./FlowTopBar/FlowTopBar";

export default function FlowCanvas({ id, nodes, setNodes, edges, setEdges, onEdgesChange, onNodesChange, getNewNodeId, workFlow }) {
    const dispatch = useDispatch();
    const navigate = useNavigate()
    const [drawerOpen, setDrawerOpen] = useState(false);
    const { values, setFieldValue, handleSubmit, } = useFormikContext()
    const [loading, setLoading] = useState(false);
    const containerRef = useRef(null);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [activeDrawer, setActiveDrawer] = useState(null);
    const { versions } = useSelector((state) => state.workflows);
    //store layout 
    const canvasLayout = values?.layout

    useEffect(() => {
        if (!workFlow?.graph) return;
        const { nodes, edges } = mapGraphFromBackend(workFlow.graph);
        if (!nodes.length) return
        setNodes(nodes);
        setEdges(edges);
    }, [workFlow?.graph]);

    const [drawerContext, setDrawerContext] = useState({
        source: null,
        node: null,
        edge: null,
    });
    // layout update
    useEffect(() => {
        if (canvasLayout !== workFlow.workflow?.layout) {
            const updateLayout = async () => {
                try {
                    await dispatch(updateWorkFlowLayout({ id, layout: canvasLayout }));
                } catch (error) {
                    console.error("Failed to update layout:", error);
                }
            };

            updateLayout();
        }
    }, [canvasLayout]);

    const activeVersionId = versions?.find(v => v.is_active)?.id;

    useEffect(() => {
        setLoading(true);
        dispatch(getSingleWorkFlow(id)).finally(() => setLoading(false));

    }, [id, activeVersionId]);
    const {
        updateNodeData,
        deleteNode,
        createActionNode,
        openDrawerForNode,
        openDrawerFromAdd,
        onLayout
    } = useFlowActions({ nodes, setNodes, edges, setEdges, drawerContext, getNewNodeId, setDrawerContext, setDrawerOpen, setFieldValue, canvasLayout });

    const onAddNode = (edgeId) => {
        const edge = edges.find((e) => e.id === edgeId);
        setDrawerContext({
            source: "edge",
            node: null,
            edge,
        })
        setDrawerOpen(true);
    };
    const onConnect = useCallback(
        (params) => setEdges((eds) => addEdge({ ...params, type: "custom" }, eds)),
        []
    );
    const isValidConnection = (connection) => {
        return !edges.some(
            (edge) =>
                edge.source === connection.source && edge.target === connection.target
        );
    };

    const onEdgeDelete = (edgeId) => {
        setEdges((eds) => eds.filter((e) => e.id !== edgeId));
    };

    console.log(nodes, 'all nodes',);
    console.log(edges, 'all edges');
    const nodeTypes = {
        custom: (props) => (
            <CustomNode
                {...props}
                data={{
                    ...props.data,
                    onOpenDrawer: () => openDrawerForNode(props),
                    openDrawerFromAdd: () => openDrawerFromAdd(props),
                    deleteNode: () => deleteNode(props.id),

                }}
                canvasLayout={canvasLayout}
            />
        ),
    };
    const edgeTypes = {
        custom: (props) => (
            <CustomEdge
                {...props}
                onEdgeDelete={onEdgeDelete}
                onAddNode={onAddNode}
            />
        ),
    };
    //listiner

    // useEffect(() => {
    //     const interval = setInterval(() => {
    //         dispatch(getRunWorkFlow());
    //     }, 5000);

    //     return () => clearInterval(interval);
    // }, []);  
    return (
        <Box
            ref={containerRef}
            className="zaplane_flowcanvas"
            flex="1"
            height="100vh"
            marginRight={activeDrawer ? "497px" : "0px"}
            transition="margin-right 0.4s ease"
        >

            <FlowTopBar
                navigate={navigate}
                workFlow={workFlow}
                isFullscreen={isFullscreen}
                toggleFullscreen={() =>
                    toggleFullscreenMode(containerRef, isFullscreen, setIsFullscreen)
                }
                id={id}
                values={values}
                setFieldValue={setFieldValue}
                handleSubmit={handleSubmit}
                activeDrawer={activeDrawer}
                setActiveDrawer={setActiveDrawer}
            />

            {
                loading ? <ZAPLoading /> : <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    nodeTypes={nodeTypes}
                    edgeTypes={edgeTypes}
                    isValidConnection={isValidConnection}
                    onNodesChange={onNodesChange}
                    onEdgesChange={onEdgesChange}
                    onConnect={onConnect}
                    fitView
                    fitViewOnInit
                    panOnDrag
                    zoomOnScroll
                    zoomOnDoubleClick
                    nodesDraggable
                    nodesConnectable
                    elementsSelectable
                    minZoom={0.5}
                >

                    <Background />
                    <Flex className="zaplane-canvas-layout-icon">
                        <ZAPTooltip content={__("Vertical layout", "zaplane")

                        }
                            positioning={{
                                placement: "top",
                                offset: {
                                    mainAxis: 8,
                                    crossAxis: 25,
                                }
                            }}>
                            <ControlButton
                                onClick={() => onLayout("TB")}
                                className={`react-flow__controls-button ${canvasLayout === "TB" ? "zaplane-layout-active" : ""
                                    }`}

                            >
                                <IoSwapVerticalOutline size={16} />
                            </ControlButton>
                        </ZAPTooltip>
                        <ZAPTooltip content={__("Horizontal layout", "zaplane")}
                            positioning={{
                                placement: "top",
                                offset: {
                                    mainAxis: 8,
                                    crossAxis: 25,
                                }
                            }}>
                            <ControlButton
                                onClick={() => onLayout("LR")}
                                className={`react-flow__controls-button ${canvasLayout === "LR" ? "zaplane-layout-active" : ""
                                    }`}
                            >
                                <IoSwapHorizontal size={16} />
                            </ControlButton>
                        </ZAPTooltip >

                    </Flex>
                    <Controls
                        position="top-left"
                        className="zaplane-canvas-controls"
                    />
                </ReactFlow>
            }

            <ActionDrawer
                open={drawerOpen}
                isFullscreen={isFullscreen}
                onClose={() => {
                    setDrawerOpen(false)
                    setDrawerContext({ source: null, node: null, edge: null });
                }}
                context={drawerContext}
                createActionNode={createActionNode}
                updateNodeData={updateNodeData}
                workFlow={workFlow}
                nodes={nodes}
                edges={edges}

            />

        </Box>
    );
}
