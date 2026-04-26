import { useState } from "react";
import { useDispatch } from "react-redux";
import { __ } from "@wordpress/i18n";
import { workFLowSingeNodeExction } from "@ZAPRedux/Slices/workFlowSlice/actions/workflowExctions";
import TestDetails from "../TestDetails/TestDetails";
import ZAPAlert from "@ZAPComponents/ZAPAlert";
import { primaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";
import ZAPInput from "@ZAPComponents/ZAPInput";

const TestRun = ({
  source,
  node,
  workFlow,
  values
}) => {
  const dispatch = useDispatch();
  const [showWarning, setShowWarning] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [triggerArgs, setTriggerArgs] = useState("[\n  1\n]");

  const handleTest = async () => {
    if (isLoading) return;
    
    setShowWarning(false);
    setIsLoading(true);
    try {
      const {
        layout,
        ...inputData
      } = values || {};

      let parsedArgs = [];
      if (node?.data?.action === "trigger") {
        try {
          parsedArgs = JSON.parse(triggerArgs);
          if (!Array.isArray(parsedArgs)) {
            parsedArgs = [parsedArgs];
          }
        } catch (e) {
          parsedArgs = [1];
        }
      }

      const payload = {
        workflow_id: workFlow?.workflow?.id,
        workflow_hash: workFlow?.version?.hash,
        workflow_version_id: workFlow.version.id,
        target_node: {
          data: node?.data,
          type: node?.data?.action,
          id: node?.id
        },
        input: node?.data?.action === "trigger" ? parsedArgs : inputData
      };
      await dispatch(workFLowSingeNodeExction(payload));
    } finally {
      setIsLoading(false);
    }
  };

  return <>
      {node?.data?.action === "trigger" && (
        <div className="mb-4">
          <ZAPInput
            type="textarea"
            label={__("Mock Hook Arguments (JSON Array)", "zaplane")}
            value={triggerArgs}
            onChange={(e) => setTriggerArgs(e.target.value)}
            inputStyle={{ fontFamily: "monospace", minHeight: "100px" }}
          />
          <p className="text-gray-500 text-xs mt-1">
            {__("Provide a JSON array representing the positional arguments fired by the webhook/hook.", "zaplane")}
          </p>
        </div>
      )}

      <button style={primaryBtn} onClick={handleTest} className="mb-4">
        {node?.data?.action === "trigger" ? __("Test Trigger", "zaplane") : __("Test Action", "zaplane")}
      </button>

      {source === "node" && <TestDetails id={node?.id} workFlow={workFlow} source={source} isLoading={isLoading} nodeType={node?.data?.action} />}
    </>;
};

export default TestRun;