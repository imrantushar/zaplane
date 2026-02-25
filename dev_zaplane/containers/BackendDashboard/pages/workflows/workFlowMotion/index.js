import React, { useEffect, useRef, useState } from "react";
import { ReactFlowProvider, useEdgesState, useNodesState } from "@xyflow/react";
import FlowCanvas from "./flowCanvas/FlowCanvas";
import { Formik } from "formik";
import { mapEdgesForBackend, mapNodesForBackend } from "./helper";
import { useDispatch, useSelector } from "react-redux";
import { createNodeIdGenerator } from "./flowCanvas/helper";
import { Box, Flex } from "@chakra-ui/react";
import { updateWorkFlow, updateWorkFlowLayout, updateWorkFlowStatus, updateWorkFlowTitle } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";

export default function Workflows({ id }) {
  const nodeIdRef = useRef(createNodeIdGenerator());
  const getNewNodeId = nodeIdRef.current;
  const { workFlow } = useSelector((state) => state.workflows);
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
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const dispatch = useDispatch();


  useEffect(() => {
    setNodes([
      {
        id: getNewNodeId(),
        type: "custom",
        data: {
          app: "Select an app",
          action: "trigger",
          config: {},
        },
        position: { x: 125, y: 300 },
      },
    ]);

    setEdges([]);
  }, [id]);

  const onSubmitHandler = async (values) => {
    const payload = {
      nodes: mapNodesForBackend(nodes)
      , edges: mapEdgesForBackend(edges),
    }
    await dispatch(
      updateWorkFlow({ id, payload })
    );
  };
  return (
    <ReactFlowProvider>
      <Flex hight='100vh'>
        <Formik
          initialValues={
            {
              layout:'LR'
            }}
          onSubmit={onSubmitHandler}
        >
          {({ }) => (
            <Box flex="1" >
              <FlowCanvas setNodes={setNodes} setEdges={setEdges} onEdgesChange={onEdgesChange}
                onNodesChange={onNodesChange} nodes={nodes} edges={edges} getNewNodeId={getNewNodeId}
                workFlow={workFlow} id={id}  />
            </Box>
          )}

        </Formik>

      </Flex>
    </ReactFlowProvider>
  );
}
