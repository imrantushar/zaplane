import React, { useCallback, useEffect, useRef, useState } from "react";
import {
    ReactFlow,
    addEdge,
    useNodesState,
    useEdgesState,
    Controls,
    Background,
    useReactFlow,
} from "@xyflow/react";
import "@xyflow/react/dist/base.css";
import { __ } from '@wordpress/i18n';


import CustomEdge from "../customEdge/CustomEdge";
import ActionDrawer from "../actionDrawer/ActionDrawer";
import { useFormikContext } from "formik";
import TopBar from "@ZAPComponents/TopBar";
import { FaChevronRight } from "react-icons/fa";
import {
    Box,
    Flex,
    Button,
    Checkbox,
    Table,
    Badge,
    HStack,
    Text,
    Tabs
} from "@chakra-ui/react";
import {
    FiArrowLeft,
    FiRefreshCw,
    FiHelpCircle,
} from "react-icons/fi";
import { useNavigate } from "react-router-dom";
import { getAllVersion, getRunWorkFlow, getSingleWorkFlow, liveMonitor, updateWorkFlow, updateWorkFlowStatus } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { useDispatch, useSelector } from "react-redux";
import { use } from "react";
import { showNotification } from "@ZAPRedux/Slices/notificationSlice/notificationSlice";
import CustomNode from "../customNoe/CustomNode";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { getRunLive, getRunTimeline, replayWorkflowRun, stopRun } from "@ZAPRedux/Slices/executionSlice/executionSlice";
import { LucideHistory } from "lucide-react";
import RunsTable from "./RunsTable/RunsTable";
import VersionHistoryTable from "./VersionHistoryTable/VersionHistoryTable";
import { LuFullscreen, LuMinimize } from "react-icons/lu";
import { toggleFullscreenMode } from "../helper";
import Select from "react-select";
;
export default function FlowCanvas({ id }) {

    const nodeIdRef = useRef(0);
    const getNewNodeId = () => {
        nodeIdRef.current += 1;
        return `${nodeIdRef.current}`;
    };
    const [nodes, setNodes, onNodesChange] = useNodesState([
        {
            id: getNewNodeId(),
            type: 'custom',
            data: {
                app: "Select an app",
                action: 'trigger',
                config: {}
            },
            position: { x: 125, y: 300 },
        }
    ]);
    const dispatch = useDispatch();
    const navigate = useNavigate()
    const [edges, setEdges, onEdgesChange] = useEdgesState([]);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [selectedNode, setSelectedNode] = useState(null);
    const [activeEdgeId, setActiveEdgeId] = useState(null);
    const [selectedApp, setSelectedApp] = useState(null);
    const [selectedEvent, setSelectedEvent] = useState(null);
    const { values, setFieldValue } = useFormikContext()
    const [loading, setLoading] = useState(false);
    const { data, runs, versions } = useSelector((state) => state.workflows);
    const singleData = data[0]
    const isFlowLoaded = useRef(false);
    const GAP = 220;
    const containerRef = useRef(null);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [activeDrawer, setActiveDrawer] = useState(null);
    console.log(singleData, 'singledata');
    useEffect(() => {
        if (!singleData?.graph?.nodes?.length) return;
        const mappedNodes = (singleData.graph.nodes || []).map((node) => ({
            ...node,
            type: "custom",
            data: {
                ...node.data,
                action: node.type,
            },
        }));

        const mappedEdges = (singleData.graph.edges || []).map((edge) => ({
            ...edge,
            type: "custom",
        }));
        setNodes(mappedNodes);
        setEdges(mappedEdges);
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

    const onSubmitHandler = async () => {

        const mapNodesForBackend = (nodes) => {
            return nodes.map(({
                dragging,
                selected,
                measured,
                data,
                ...node
            }) => {
                const backendType = data?.action?.toLowerCase();
                const cleanedData = { ...data };
                delete cleanedData.action;

                return {
                    ...node,
                    type: backendType,
                    data: cleanedData,
                };
            });
        };
        const mapEdgesForBackend = (edges) => {
            return edges.map(({ type, ...edge }) => edge);
        };
        const payload = {
            nodes: mapNodesForBackend(nodes)
            , edges: mapEdgesForBackend(edges),
        }
        const statusPaylod = {
            status: values?.status,
            id: id,
        }
        if (values.status && values.status !== singleData?.workflow.status) {
            await dispatch(updateWorkFlowStatus(statusPaylod));
        }
        await dispatch(
            updateWorkFlow({ id, payload })
        );



    };
    const { screenToFlowPosition } = useReactFlow();
    const openDrawerForNode = (node) => {
        setDrawerContext({
            source: "node",
            node,
            edge: null,
        });
        setDrawerOpen(true);
    };
    const onAddNode = (edgeId) => {
        setActiveEdgeId(edgeId);
        const edge = edges.find((e) => e.id === edgeId);
        setDrawerContext({
            source: "edge",
            node: null,
            edge,
        })
        setDrawerOpen(true);
    };
    const openDrawerFromAdd = (node) => {
        ;
        setDrawerContext({
            source: "add",
            node: node,
            edge: null,
        });
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

    const createConditionNode = ({ conditions }) => {
        const { edge, node } = drawerContext;

        let sourceNode = null;
        let targetNode = null;

        if (edge) {
            sourceNode = nodes.find((n) => n.id === edge.source);
            targetNode = nodes.find((n) => n.id === edge.target);
            if (!sourceNode || !targetNode) return;
        }

        if (!edge && node) {
            sourceNode = nodes.find((n) => n.id === node.id);
            if (!sourceNode) return;
        }

        const newX = sourceNode.position.x + GAP;
        const newY = sourceNode.position.y;

        const newNodeId = getNewNodeId();

        const newNode = {
            id: newNodeId,
            type: "custom",
            position: { x: newX, y: newY },
            data: {
                app: "Logic",
                action: "logic",
                // order: nodes.length + 1,
                logic: {
                    groups: conditions.map((group) => ({
                        id: group.id,
                        type: group.type || "AND",
                        rules: group.rules.map((rule) => ({
                            id: rule.id,
                            field: rule.field,
                            operator: rule.operator,
                            value: rule.value,
                        })),
                    })),
                },
            },
        };
        const updatedNodes = nodes.map((n) => {
            if (n.position.x >= newX) {
                return {
                    ...n,
                    position: {
                        ...n.position,
                        x: n.position.x + GAP,
                    },
                };
            }
            return n;
        });

        let newEdges = [...edges];

        if (edge) {
            newEdges = [
                ...edges.filter((e) => e.id !== edge.id),
                {
                    id: `e${edge.source}-${newNodeId}`,
                    source: edge.source,
                    target: newNodeId,
                    type: "custom",
                },
                {
                    id: `e${newNodeId}-${edge.target}`,
                    source: newNodeId,
                    target: edge.target,
                    type: "custom",
                },
            ];
        } else {
            newEdges = [
                ...edges,
                {
                    id: `e${sourceNode.id}-${newNodeId}`,
                    source: sourceNode.id,
                    target: newNodeId,
                    type: "custom",
                },
            ];
        }

        setNodes([...updatedNodes, newNode]);
        setEdges(newEdges);
    };


    const createActionNode = (actionData) => {
        const { edge, node } = drawerContext;
        let sourceNode = null;
        let targetNode = null;
        if (edge) {
            sourceNode = nodes.find((n) => n.id === edge.source);
            targetNode = nodes.find((n) => n.id === edge.target);
            if (!sourceNode || !targetNode) return;
        }

        if (!edge && node) {
            sourceNode = nodes.find((n) => n.id === node.id);
            if (!sourceNode) return;
        }
        const newX = sourceNode.position.x + GAP;
        const newY = sourceNode.position.y;

        const newNodeId = getNewNodeId();
        const newNode = {
            id: newNodeId,
            type: "custom",
            position: { x: newX, y: newY },
            data: {
                action: "action",
                // order: nodes.length + 1,
                ...actionData,
            },
        };
        const updatedNodes = nodes.map((n) => {
            if (n.position.x >= newX) {
                return {
                    ...n,
                    position: {
                        ...n.position,
                        x: n.position.x + GAP,
                    },
                };
            }
            return n;
        });

        let newEdges = [...edges];

        if (edge) {
            newEdges = [
                ...edges.filter((e) => e.id !== edge.id),
                {
                    id: `e${edge.source}-${newNodeId}`,
                    source: edge.source,
                    target: newNodeId,
                    type: "custom",
                },
                {
                    id: `e${newNodeId}-${edge.target}`,
                    source: newNodeId,
                    target: edge.target,
                    type: "custom",
                },
            ];
        } else {
            newEdges = [
                ...edges,
                {
                    id: `e${sourceNode.id}-${newNodeId}`,
                    source: sourceNode.id,
                    target: newNodeId,
                    type: "custom",
                },
            ];
        }

        setNodes([...updatedNodes, newNode]);
        setEdges(newEdges);
    };

    const updateNodeData = (updatedData) => {
        setNodes((nds) =>
            nds.map((n) => {
                if (n.id === drawerContext.node?.id) {
                    return {
                        ...n,
                        data: {
                            ...n.data,
                            ...updatedData,
                        },
                    };
                }
                return n;
            })
        );
    };
    const deleteNode = useCallback((nodeId) => {
        console.log(nodeId, 'nodeid');
        setNodes((nds) => nds.filter((n) => n.id !== nodeId));

        setEdges((eds) =>
            eds.filter(
                (e) => e.source !== nodeId && e.target !== nodeId
            )
        );
    }, []);

    console.log(nodes, 'all nodes');
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
    const statusOptions = [
        { value: "active", label: "Active" },
        { value: "paused", label: "Paused" },
        { value: "draft", label: "draft" },
    ];
    console.log(values, 'valuess');
    return (
        <div
            ref={containerRef}
            className="zaplane_flowcanvas"
            style={{
                flex: 1, height: "100vh",
                marginRight: activeDrawer ? "497px" : "0px",
                transition: "margin-right 0.4s ease",

            }}
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
                    </>
                )}
                middleContent={() => (
                    <Tabs.Root defaultValue="Editor" variant="plain">
                        <Tabs.List bg="bg.muted" rounded="l3" p="1">
                            <Tabs.Trigger value="Editor">
                                Editor
                            </Tabs.Trigger>
                            <Tabs.Trigger value="Executions">
                                <ZAPDrawer
                                    title="Execution"
                                    placement='start'
                                    trigger={
                                        <Text margin="0" size="sm"
                                        >

                                            {__("Execution ", "zaplane")}
                                        </Text>
                                    }>


                                </ZAPDrawer>
                            </Tabs.Trigger>
                            <Tabs.Indicator rounded="l2" />
                        </Tabs.List>

                    </Tabs.Root>
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
                            title="Log History"
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
                                    onClick={() => dispatch(replayWorkflowRun(id))}>
                                    {__("🔄 Replay ", "zaplane")}
                                </Button>
                            </Flex>
                            <RunsTable
                                runs={runs}
                            />


                        </ZAPDrawer>
                        <ZAPDrawer
                            title="Version History "
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
                            color="white"
                            _hover={{ bg: "gray.800" }}
                            onClick={() => onSubmitHandler()}
                        >
                            {__("Update", "zaplane")}
                        </Button>
                    </>
                )}
            />
            <Box>

            </Box>
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
                    setActiveEdgeId(null);
                    setDrawerContext({ source: null, node: null, edge: null });
                }}
                setSelectedNode={setSelectedNode}
                createConditionNode={createConditionNode}
                context={drawerContext}
                createActionNode={createActionNode}
                updateNodeData={updateNodeData}

            />
        </div>
    );
}
