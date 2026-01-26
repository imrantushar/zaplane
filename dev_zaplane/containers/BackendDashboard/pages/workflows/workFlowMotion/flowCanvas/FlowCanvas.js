import React, { useCallback, useEffect, useRef, useState } from "react";
import {
    ReactFlow,
    addEdge,
    Controls,
    Background,
    Panel,
} from "@xyflow/react";
import "@xyflow/react/dist/base.css";
import { __ } from '@wordpress/i18n';


import CustomEdge from "../CustomEdge/CustomEdge";
import ActionDrawer from "../ActionDrawer/ActionDrawer";
import { useFormikContext } from "formik";
import TopBar from "@ZAPComponents/TopBar";
import {
    Box,
    Flex,
    Button,
    Text,
} from "@chakra-ui/react";
import {
    FiArrowLeft
} from "react-icons/fi";
import { useNavigate } from "react-router-dom";
import { getAllVersion, getRunWorkFlow, getSingleWorkFlow, workFLowExction, workflowNodeListiner, workflowNodeListinerStop } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { useDispatch, useSelector } from "react-redux";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { LucideHistory } from "lucide-react";
import RunsTable from "./RunsTable/RunsTable";
import VersionHistoryTable from "./VersionHistoryTable/VersionHistoryTable";
import { LuFullscreen, LuMinimize } from "react-icons/lu";
import { toggleFullscreenMode } from "../helper";
import Select from "react-select";
import ZAPLoading from "@ZAPComponents/Loading";
import { statusOptions } from "../../helper";
import { mapGraphFromBackend } from "./helper";
import { useFlowActions } from "../../../../../../hooks/useFlowActions";
import CustomNode from "../customNode/CustomNode";

export default function FlowCanvas({ id, nodes, setNodes, edges, setEdges, onEdgesChange, onNodesChange, getNewNodeId,singleData }) {
    const dispatch = useDispatch();
    const navigate = useNavigate()
    const [drawerOpen, setDrawerOpen] = useState(false);
    const { values, setFieldValue, handleSubmit } = useFormikContext()
    const [loading, setLoading] = useState(false);
    const {runs, versions } = useSelector((state) => state.workflows);
    const containerRef = useRef(null);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [activeDrawer, setActiveDrawer] = useState(null);

    useEffect(() => {
        if (!singleData?.graph) return;
        const { nodes, edges } = mapGraphFromBackend(singleData.graph);
        if (nodes.length === 0) return;
        setNodes(nodes);
        setEdges(edges);
    }, [singleData?.graph]);

    const [drawerContext, setDrawerContext] = useState({
        source: null,
        node: null,
        edge: null,
    });
    useEffect(() => {
        setLoading(true);
        dispatch(getSingleWorkFlow(id)).finally(() => setLoading(false));

    }, [id]);
    const {
        updateNodeData,
        deleteNode,
        createActionNode,
        openDrawerForNode,
        openDrawerFromAdd,
    } = useFlowActions({ nodes, setNodes, edges, setEdges, drawerContext, getNewNodeId, setDrawerContext, setDrawerOpen });

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
    console.log(nodes, 'all nodes');
    console.log(edges, 'all edges');
    if (loading) {
        return <ZAPLoading />
    }

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

            <TopBar
                leftContent={() => (
                    <>
                        <Button variant="outline" onClick={() => navigate(-1)}>
                            <FiArrowLeft />
                        </Button>
                        <Text fontSize="md" fontWeight="medium">
                            {singleData?.workflow?.title || __("Untitled Workflow", "zaplane")}
                        </Text>
                        <Button size="sm" variant="outline"
                            onClick={() => dispatch(workflowNodeListiner(id))}>
                            {__("Runs ", "zaplane")}
                        </Button>
                        <Button size="sm" variant="outline"
                            onClick={() => dispatch(workflowNodeListinerStop(id))}>
                            {__("Stop", "zaplane")}
                        </Button>
                    </>
                )}
                rightContent={() => (
                    <>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => toggleFullscreenMode(containerRef, isFullscreen, setIsFullscreen)}
                        >
                            {isFullscreen ? <LuMinimize /> : <LuFullscreen />}
                        </Button>
                         <ZAPDrawer
                            title={__("Log History", "Zaplane")}
                            size="md"
                            open={activeDrawer === "logs"}
                            onClose={() => setActiveDrawer(null)}
                            trigger={
                                <Button size="sm" variant="outline"
                                    onClick={() => {
                                        dispatch(getRunWorkFlow(id))
                                        setActiveDrawer("logs")
                                    }}

                                >
                                    {__("Logs ", "zaplane")}
                                </Button>
                            }>
                            <Flex gap="5px">
                                <Button size="sm" variant="outline"
                                    onClick={() => dispatch(getRunWorkFlow(id))}>
                                    {__("🔄 Refresh ", "zaplane")}
                                </Button>
                                <Button size="sm" variant="outline"
                                    onClick={() => {
                                        const paylod = {
                                            workflow_hash: singleData?.version?.hash,
                                        }

                                        dispatch(workFLowExction(paylod))
                                    }}>
                                    {__("🔄 Replay ", "zaplane")}
                                </Button>
                            </Flex>
                            <RunsTable
                                runs={runs}
                            />


                        </ZAPDrawer>
                        <ZAPDrawer
                            title={__("Version History", 'zaplane')}
                            open={activeDrawer === "history"}
                            onClose={() => setActiveDrawer(null)}
                            trigger={
                                <Text margin='0' cursor="pointer" onClick={() => {
                                    setActiveDrawer("history");
                                    dispatch(getAllVersion(id))
                                }
                                }> <LucideHistory /></Text>

                            }>

                            <VersionHistoryTable
                                versions={versions}
                                id={id}

                            />

                        </ZAPDrawer>
                        <Select
                            options={statusOptions}
                            value={
                                values?.status
                                    ? statusOptions.find(opt => opt.value === values.status)
                                    : statusOptions.find(opt => opt.value === singleData?.workflow?.status)
                            }

                            onChange={(selected) =>
                                setFieldValue('status', selected.value)}
                            isClearable={false}
                            isSearchable={false}
                            placeholder="Select status"
                        />
                        <Button
                            size="sm"
                            bg="black"
                            color="var(--zaplane-background)"
                            onClick={handleSubmit}
                        >
                            {__("Update", "zaplane")}
                        </Button>
                       
                    </>
                )}
            />
            <ReactFlow
                nodes={nodes}
                edges={edges}
                nodeTypes={nodeTypes}
                edgeTypes={edgeTypes}
                isValidConnection={isValidConnection}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                // fitView
                // fitViewOnInit
                panOnDrag
                zoomOnScroll
                zoomOnDoubleClick
                nodesDraggable
                nodesConnectable
                elementsSelectable
                minZoom={0.5}
            >
                <Background />
                <Controls />
            </ReactFlow>
            <ActionDrawer
                open={drawerOpen}
                onClose={() => {
                    setDrawerOpen(false)
                    setDrawerContext({ source: null, node: null, edge: null });
                }}
                context={drawerContext}
                createActionNode={createActionNode}
                updateNodeData={updateNodeData}
                singleData={singleData}

            />

        </Box>
    );
}
