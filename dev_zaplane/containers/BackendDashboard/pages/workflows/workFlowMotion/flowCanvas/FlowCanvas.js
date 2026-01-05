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
    const { data } = useSelector((state) => state.workflows);
    const { runs } = useSelector((state) => state.workflows);
    console.log(runs,'runss');
    const { versions } = useSelector((state) => state.workflows);
    console.log(versions, 'v');
    const singleData = data[0]
    const isFlowLoaded = useRef(false);
    const GAP = 220;
    console.log(singleData);
    useEffect(() => {
        if (!singleData?.graph || isFlowLoaded.current) return;
        if (singleData.graph.nodes?.length) {
            const mappedNodes = singleData.graph.nodes.map((node) => ({
                ...node,
                type: "custom",
                data: {
                    ...node.data,
                    action: node.type,
                },
            }));

            setNodes(mappedNodes);
        }

        if (singleData.graph.edges?.length) {
            const mappedEdges = singleData.graph.edges.map((edge) => ({
                ...edge,
                type: "custom",
            }));

            setEdges(mappedEdges);
        }

        isFlowLoaded.current = true;
    }, [singleData]);





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

        if (id) {
            const { payload: data } = await dispatch(
                updateWorkFlow({ id, payload })
            );


        }
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

    const statusOptions = [
        { value: "active", label: "Active" },
        { value: "paused", label: "Paused" },
        { value: "draft", label: "draft" },
    ];
    return (
        <div style={{ flex: 1, height: "100vh" }}>
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
                                            onClick={() => dispatch(getRunLive(id))}>

                                            {__("Execution ", "zaplane")}
                                        </Text>
                                    }>


                                </ZAPDrawer>
                            </Tabs.Trigger>
                            <Tabs.Indicator rounded="l2" />
                        </Tabs.List>
                        {/* <Tabs.Content value="members">Manage your team members</Tabs.Content>
                        <Tabs.Content value="projects">Manage your projects</Tabs.Content> */}

                    </Tabs.Root>
                )}
                rightContent={() => (
                    <>
                        <ZAPDrawer
                            title="Version History "
                            trigger={
                                <Text margin='0' cursor="pointer" onClick={() => dispatch(getAllVersion(id))}> <LucideHistory /></Text>

                            }>

                            <VersionHistoryTable
                                versions={versions}
                                id={id}
                                
                            />

                        </ZAPDrawer>


                        <Button size="sm" variant="outline"
                        >
                            {__(singleData?.workflow?.status, "zaplane")}
                        </Button>
                        <Button size="sm" variant="outline"
                            onClick={() => dispatch(getRunWorkFlow())}>
                            {__("Runs", "zaplane")}
                        </Button>
                        <ZAPDrawer
                            title="Log History"
                            size="xl"
                            trigger={
                                <Button size="sm" variant="outline" onClick={() => dispatch(getRunWorkFlow())}>
                                    {__("Logs ", "zaplane")}
                                </Button>
                            }>
                                <Button size="sm" variant="outline"
                                    onClick={() => dispatch(replayWorkflowRun(id))}>
                                    {__("🔄 Replay ", "zaplane")}
                                </Button>
                            <RunsTable
                                runs={runs?.runs}
                            />


                        </ZAPDrawer>
                        <Button
                            size="sm"
                            bg="black"
                            color="white"
                            _hover={{ bg: "gray.800" }}
                            onClick={() => onSubmitHandler()}
                        >
                            {__("Publish", "zaplane")}
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
