import React, { useCallback, useEffect, useState } from "react";
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
import { createWorkflows, getWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { useDispatch, useSelector } from "react-redux";
import { use } from "react";
;

let id = 0;
const getId = () => `dndnode_${id++}`;



export default function FlowCanvas() {
    const [nodes, setNodes, onNodesChange] = useNodesState([
        {
            id: '123',
            type: 'custom',
            data: { id: "123", label: "Select an app", icon: "", action: 'Trigger' },
            position: { x: 125, y: 500 },
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
    const workflowTitle = useSelector((state) => state.workflows.workflow_Title);
    console.log(workflowTitle, "hiiii");

    const [drawerContext, setDrawerContext] = useState({
        source: null,
        node: null,
        edge: null,
    });
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

    const onDrop = useCallback(
        (event) => {
            event.preventDefault();
            const type = event.dataTransfer.getData("application/reactflow");
            if (!type) return;

            const position = screenToFlowPosition({
                x: event.clientX,
                y: event.clientY,
            });

            const newNode = {
                id: getId(),
                position,
                type: "custom",
                data: {
                    label: type,
                    order: nodes.length + 1
                },
            };

            setNodes((nds) => nds.concat(newNode));
        },
        [screenToFlowPosition, setNodes, nodes.length]
    );

    const onDragOver = (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
    };

    const isValidConnection = (connection) => {
        return !edges.some(
            (edge) =>
                edge.source === connection.source && edge.target === connection.target
        );
    };

    const onEdgeDelete = (edgeId) => {
        setEdges((eds) => eds.filter((e) => e.id !== edgeId));
    };
    const createRouterNode = () => {
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
        const position = edge
            ? {
                x: (sourceNode.position.x + targetNode.position.x) / 2,
                y: (sourceNode.position.y + targetNode.position.y) / 2,
            }
            : {
                x: sourceNode.position.x + 220,
                y: sourceNode.position.y,
            };

        const routerId = getId();
        const routerNode = {
            id: routerId,
            type: "custom",
            position,
            data: {
                label: "Router",
                action: "Router",
                order: nodes.length + 1,
                routes: [
                    {
                        id: "1",
                        title: "Route 1",
                    },
                ],
            },
        };
        let updatedEdges = [...edges];

        if (edge) {
            updatedEdges = [
                ...edges.filter((e) => e.id !== edge.id),
                {
                    id: `edge-${edge.source}-${routerId}`,
                    source: edge.source,
                    target: routerId,
                    type: "custom",
                },
                {
                    id: `edge-${routerId}-${edge.target}`,
                    source: routerId,
                    target: edge.target,
                    type: "custom",
                },
            ];
        }
        if (!edge && sourceNode) {
            updatedEdges = [
                ...edges,
                {
                    id: `edge-${sourceNode.id}-${routerId}`,
                    source: sourceNode.id,
                    target: routerId,
                    type: "custom",
                },
            ];
        }

        setNodes((nds) => nds.concat(routerNode));
        setEdges(updatedEdges);
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

        const position = edge
            ? {
                x: (sourceNode.position.x + targetNode.position.x) / 2,
                y: (sourceNode.position.y + targetNode.position.y) / 2,
            }
            : {
                x: sourceNode.position.x + 220,
                y: sourceNode.position.y,
            };

        const newNodeId = getId();

        const newNode = {
            id: newNodeId,
            type: "custom",
            position,
            data: {
                label: "Condition",
                action: "Condition",
                order: nodes.length + 1,
                logic: {
                    groups: conditions.map((group) => ({
                        id: group.id,
                        type: group.type || 'AND',
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

        let newEdges = [...edges];

        if (edge) {
            newEdges = [
                ...edges.filter((e) => e.id !== edge.id),
                {
                    id: `edge-${edge.source}-${newNodeId}`,
                    source: edge.source,
                    target: newNodeId,
                    type: "custom",
                },
                {
                    id: `edge-${newNodeId}-${edge.target}`,
                    source: newNodeId,
                    target: edge.target,
                    type: "custom",
                },
            ];
        }

        if (!edge && sourceNode) {
            newEdges = [
                ...edges,
                {
                    id: `edge-${sourceNode.id}-${newNodeId}`,
                    source: sourceNode.id,
                    target: newNodeId,
                    type: "custom",
                },
            ];
        }

        setNodes((nds) => nds.concat(newNode));
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
        const position = edge
            ? {
                x: (sourceNode.position.x + targetNode.position.x) / 2,
                y: (sourceNode.position.y + targetNode.position.y) / 2,
            }
            : {
                x: sourceNode.position.x + 220,
                y: sourceNode.position.y,
            };

        const newNodeId = getId();

        const newNode = {
            id: newNodeId,
            type: "custom",
            position,
            data: {
                label: actionData.actionName,
                action: "Action",
                order: nodes.length + 1,
                ...actionData,
            },
        };

        let newEdges = [...edges];
        if (edge) {
            newEdges = [
                ...edges.filter((e) => e.id !== edge.id),
                {
                    id: `edge-${edge.source}-${newNodeId}`,
                    source: edge.source,
                    target: newNodeId,
                    type: "custom",
                },
                {
                    id: `edge-${newNodeId}-${edge.target}`,
                    source: newNodeId,
                    target: edge.target,
                    type: "custom",
                },
            ];
        }

        if (!edge && sourceNode) {
            newEdges = [
                ...edges,
                {
                    id: `edge-${sourceNode.id}-${newNodeId}`,
                    source: sourceNode.id,
                    target: newNodeId,
                    type: "custom",
                },
            ];
        }

        setNodes((nds) => nds.concat(newNode));
        setEdges(newEdges);
    };
    const updateTriggerNode = (triggerData) => {
        ;
        setNodes((nds) =>
            nds.map((node) => {
                if (
                    drawerContext.source === "node" &&
                    node.id === drawerContext.node.id &&
                    node.data.action === "Trigger"
                ) {
                    return {
                        ...node,
                        data: {
                            ...node.data,
                            label: triggerData.label,
                            eventType: triggerData.eventType,
                            event: triggerData.event,
                            connection: triggerData.connection,
                            app: triggerData.app,
                        },
                    };
                }
                return node;
            })
        );
    };

    const addCondition = (nodeId) => {
        setNodes((nds) =>
            nds.map((node) => {
                if (node.id !== nodeId) return node;

                const count = node.data.conditions?.length || 0;

                return {
                    ...node,
                    data: {
                        ...node.data,
                        conditions: [
                            ...(node.data.conditions || []),
                            {
                                id: `${node.data.order}.${count + 1}`,
                                title: `Untitled Condition ${count + 1}`,
                            },

                        ],
                    },
                };
            })
        );
    };

    const deleteCondition = (nodeId, conditionId) => {
        setNodes((nds) =>
            nds.map((node) => {
                if (node.id !== nodeId) return node;

                return {
                    ...node,
                    data: {
                        ...node.data,
                        conditions: node.data.conditions.filter(
                            (c) => c.id !== conditionId
                        ),
                    },
                };
            })
        );
    };

    const updateCondition = (nodeId, conditionId, value) => {
        setNodes((nds) =>
            nds.map((node) => {
                if (node.id !== nodeId) return node;

                return {
                    ...node,
                    data: {
                        ...node.data,
                        conditions: node.data.conditions.map((c) =>
                            c.id === conditionId
                                ? { ...c, title: value }
                                : c
                        ),
                    },
                };
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
                    onAddCondition: () => addCondition(props.id),
                    onDeleteCondition: (cid) =>
                        deleteCondition(props.id, cid),
                    onEditCondition: (cid) => {
                        const title = prompt("Edit Condition");
                        if (title)
                            updateCondition(props.id, cid, title);
                    },
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
                            {workflowTitle || __("Untitled Workflow", "zaplane")}
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
                onDrop={onDrop}
                onDragOver={onDragOver}
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
                createRouterNode={createRouterNode}
                createActionNode={createActionNode}
                updateTriggerNode={updateTriggerNode}

            />
        </div>
    );
}
