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

import CustomNode from "./CustomNode";
import CustomEdge from "./CustomEdge";
import ActionDrawer from "./ActionDrawer";
import { useFormikContext } from "formik";
import TopBar from "@ZAPComponents/TopBar";
import { FaChevronRight } from "react-icons/fa";
import {
    Box,
    Flex,
    Text,
    Button,
    IconButton,
    HStack,
    Badge,
    Checkbox,
    useSelect,
} from "@chakra-ui/react";
import {
    FiArrowLeft,
    FiRefreshCw,
    FiHelpCircle,
} from "react-icons/fi";
import { useNavigate } from "react-router-dom";
import { createWorkflows, getSingleWorkFlow, getWorkFlow, updateWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { useDispatch, useSelector } from "react-redux";
import { use } from "react";
import { showNotification } from "@ZAPRedux/Slices/notificationSlice/notificationSlice";
import { parseFlowJson } from "./helper";
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
            zpType: 'Trigger',
            data: {
                app: "Select an app", icon: "", action: 'Trigger',
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
    const singleData = data[0]
    const isFlowLoaded = useRef(false);
    const GAP = 220;
    useEffect(() => {
        if (!singleData?.nodes || isFlowLoaded.current) return;

        if (singleData?.nodes?.length) {
            const mappedNodes = singleData.nodes.map((node) => ({
                ...node,
                zpType: node.type,
                type: "custom",
            }));

            setNodes(mappedNodes);
        }

        setEdges(singleData.edges || []);
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
                zpType,
                dragging,
                selected,
                measured,
                ...node
            }) => ({
                ...node,
                type: zpType?.toLowerCase(),
            }));
        };


        const payload = {
            nodes: mapNodesForBackend(nodes)
            , edges,
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
            zpType: 'logic',
            position: { x: newX, y: newY },
            data: {
                app: "Condition",
                action: "Condition",
                order: nodes.length + 1,
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
            zpType: "action",
            position: { x: newX, y: newY },
            data: {
                action: "Action",
                order: nodes.length + 1,
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


    return (
        <div style={{ flex: 1, height: "100vh" }}>
            <TopBar
                leftContent={() => (
                    <>
                        <Button variant="outline" onClick={() => navigate(-1)}>
                            <FiArrowLeft />
                        </Button>
                        <Text fontSize="md" fontWeight="medium">
                            {singleData?.title || __("Untitled Workflow", "zaplane")}
                        </Text>
                    </>
                )}
                rightContent={() => (
                    <>
                        <Checkbox.Root
                            padding="7px 9px"
                            borderRadius="4px"
                            border="1px solid var(--zaplane-border-color)"
                        >
                            <Checkbox.HiddenInput />
                            <Checkbox.Control />
                            <Checkbox.Label>show runs</Checkbox.Label>
                        </Checkbox.Root>
                        <Button size="sm" variant="outline">
                            {__("inactive", "zaplane")}
                        </Button>
                        <Button size="sm" variant="outline">
                            {__("Save Draft", "zaplane")}
                        </Button>
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
