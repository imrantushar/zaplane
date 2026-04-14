import React, { useEffect, useMemo, useRef, useState } from "react";
import { ReactFlowProvider, useEdgesState, useNodesState } from "@xyflow/react";
import FlowCanvas from "./FlowCanvas/FlowCanvas";
import { Formik } from "formik";
import { extractIntegrationIcons, generateFlowHash, mapEdgesForBackend, mapNodesForBackend } from "./helper";
import { useDispatch, useSelector } from "react-redux";
import { Box, Flex } from "@chakra-ui/react";
import { updateWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import NavigationBlocker from "@ZAPComponents/NavigationBlocker";
import { createNodeIdGenerator } from "./FlowCanvas/helper";

export default function Workflows({ id }) {
  const nodeIdRef = useRef(createNodeIdGenerator());
  const getNewNodeId = nodeIdRef.current;
  const [initialHash, setInitialHash] = useState("");
  const { workFlow } = useSelector((state) => state.workflows);
 const [canvasLayout, setCanvasLayOut] = useState(workFlow?.workflow?.layout)
  const [nodes, setNodes, onNodesChange] = useNodesState([
    {
      id: getNewNodeId(),
      type: 'custom',
      data: {
        icon:'plus',
        app: "Select an app",
        action: 'trigger',
        config: {}
      },
      position: { x: 400, y: 300 },
    }
  ]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const dispatch = useDispatch();

  useEffect(() => {
    const defaultNodes = [
      {
        id: getNewNodeId(),
        type: "custom",
        data: {
           icon:'plus',
          app: "Select an app",
          action: "trigger",
          config: {},
        },
        position: { x: 400, y: 300 },
      },
    ];

    setNodes(defaultNodes);
    setEdges([]);

    const hash = generateFlowHash(defaultNodes, []);
    setInitialHash(hash);
  }, [id]);
  useEffect(() => {
  if (workFlow?.workflow?.layout) {
    setCanvasLayOut(workFlow.workflow.layout);
  }
}, [workFlow]);
  const currentHash = useMemo(() => {
    return generateFlowHash(nodes, edges);
  }, [nodes, edges]);
  const isFlowDirty = currentHash !== initialHash;
  const onSubmitHandler = async (values) => {
    const payload = {
      nodes: mapNodesForBackend(nodes),
      edges: mapEdgesForBackend(edges),
      layout: canvasLayout,
      integration_icons: extractIntegrationIcons(nodes),
    }
    await dispatch(
      updateWorkFlow({ id, payload })
    );

    // Reset dirty state after successful save
    setInitialHash(currentHash);
  };

  return (
    <ReactFlowProvider>
      <Flex hight='100vh'>
        <Formik
          enableReinitialize
          initialValues={
            {}}
          onSubmit={onSubmitHandler}
        >
          {({ }) => (
            <Box flex="1" >
              <NavigationBlocker when={isFlowDirty} />
              <FlowCanvas setNodes={setNodes} setEdges={setEdges} onEdgesChange={onEdgesChange}
                onNodesChange={onNodesChange} nodes={nodes} edges={edges} getNewNodeId={getNewNodeId}
                workFlow={workFlow} id={id} isFlowDirty={isFlowDirty} canvasLayout={canvasLayout} setCanvasLayOut={setCanvasLayOut} />
            </Box>
          )}

        </Formik>

      </Flex>
    </ReactFlowProvider>
  );
}
