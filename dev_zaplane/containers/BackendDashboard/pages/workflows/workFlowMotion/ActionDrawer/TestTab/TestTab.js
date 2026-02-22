import { Button } from "@chakra-ui/react";
import { useState } from "react";
import { useDispatch } from "react-redux";
import { __ } from "@wordpress/i18n";
import { workFLowSingeNodeExction } from "@ZAPRedux/Slices/workFlowSlice/actions/workflowExctions";
import TestDetails from "../TestDetails/TestDetails";
import ZAPAlert from "@ZAPComponents/ZAPAlert";
import { primaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";

const TestTab = ({ source, node, workFlow, values }) => {
  const dispatch = useDispatch();
  const [showWarning, setShowWarning] = useState(false);

  const handleTest = () => {
    if (source !== "node") {
      setShowWarning(true);
      return;
    }

    setShowWarning(false);

    dispatch(
      workFLowSingeNodeExction({
        workflow_hash: workFlow?.version?.hash,
        node_key: node?.id,
        input: values,
      })
    );
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
        />
      )}
    </>
  );
};

export default TestTab;