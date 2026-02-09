import React, { useRef } from "react";
import { ReactFlowProvider, useEdgesState, useNodesState } from "@xyflow/react";
import FlowCanvas from "./flowCanvas/FlowCanvas";
import {Formik } from "formik";
import { mapEdgesForBackend, mapNodesForBackend } from "./helper";
import { useDispatch, useSelector } from "react-redux";
import { createNodeIdGenerator } from "./flowCanvas/helper";
import { Box, Flex } from "@chakra-ui/react";
import { updateWorkFlow, updateWorkFlowStatus } from "@ZAPRedux/Slices/workFlowSlice/actions/workflow";

export default function Workflows({ id }) {
  const nodeIdRef = useRef(createNodeIdGenerator());
  const getNewNodeId = nodeIdRef.current;
   const { data} = useSelector((state) => state.workflows);
    const singleData = data[0]
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
  const onSubmitHandler = async (values, actions) => {
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
  return (
    <ReactFlowProvider>
      <Flex hight='100vh'>
        <Formik
          initialValues={
            {
            }}
          onSubmit={onSubmitHandler}
        >
          {({}) => (
            <Box flex="1" >
              <FlowCanvas setNodes={setNodes} setEdges={setEdges} onEdgesChange={onEdgesChange}
                onNodesChange={onNodesChange} nodes={nodes} edges={edges} getNewNodeId={getNewNodeId} 
                singleData={singleData} id={id} />
            </Box>
          )}

        </Formik>

      </Flex>
    </ReactFlowProvider>
  );
}
