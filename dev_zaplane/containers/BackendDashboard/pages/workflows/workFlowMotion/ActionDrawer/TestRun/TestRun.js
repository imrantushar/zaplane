import { Button } from "@chakra-ui/react";
import { useState } from "react";
import { useDispatch } from "react-redux";
import { __ } from "@wordpress/i18n";
import { workFLowSingeNodeExction } from "@ZAPRedux/Slices/workFlowSlice/actions/workflowExctions";
import TestDetails from "../TestDetails/TestDetails";
import ZAPAlert from "@ZAPComponents/ZAPAlert";
import { primaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";
import { mapEdgesForBackend, mapNodesForBackend } from "../../helper";

const TestRun = ({ source, node, workFlow, values, nodes, edges }) => {
  const dispatch = useDispatch();
  const [showWarning, setShowWarning] = useState(false);
  const [isLoading, setIsLoading] = useState(false);


  const handleTest = async () => {
    if (isLoading) return;
    if (source !== "node") {
      setShowWarning(true);
      return;
    }

    setShowWarning(false);
    setIsLoading(true);
    try {
      const { layout, ...inputData } = values || {};
      const payload = {
        workflow_id: workFlow?.workflow?.id,      
        node_key: node?.id,             
        input: inputData,                  
        graph: {
          nodes: mapNodesForBackend(nodes),  
          edges: mapEdgesForBackend(edges) 
        }
      };
      await dispatch(
        workFLowSingeNodeExction(payload)
      );
    } finally {
      setIsLoading(false);
    }


  };

  return (
    <>
      <Button
        mb={4}
        {...primaryBtn}
        onClick={handleTest}
      >
        {__("Test Action", "zaplane")}
      </Button>

      {showWarning && (
        <ZAPAlert
          status="warning"
          title={__("Action Submit Required", "zaplane")}
          description={__(
            "Submit node first, then test again.",
            "zaplane"
          )}
          mt={4}
        />
      )}

      {source === "node" && (
        <TestDetails
          id={node?.id}
          workFlow={workFlow}
          source={source}
          isLoading={isLoading}
        />
      )}
    </>
  );
};

export default TestRun;