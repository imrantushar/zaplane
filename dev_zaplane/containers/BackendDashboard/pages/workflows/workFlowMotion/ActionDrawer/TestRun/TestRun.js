import { useState, useEffect, useRef } from "react";
import { useDispatch, useSelector } from "react-redux";
import { __ } from "@wordpress/i18n";
import { workFLowSingeNodeExction } from "@ZAPRedux/Slices/workFlowSlice/actions/workflowExctions";
import { workflowNodeListiner, workflowNodeListinerPoll, workflowNodeListinerStop } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowListiner";
import { startApiCountdown, decrementApiCountdown } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import TestDetails from "../TestDetails/TestDetails";
import ZAPAlert from "@ZAPComponents/ZAPAlert";
import { primaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";
const TestRun = ({
  source,
  node,
  workFlow,
  values
}) => {
  const dispatch = useDispatch();
  const { apiRequestRunning } = useSelector(state => state.workflows);
  const [isLoading, setIsLoading] = useState(false);

  const isTrigger = node?.data?.action === "trigger";
  const workflowId = workFlow?.workflow?.id;

  // Track live "listening" state in a ref so timers/cleanup read the current
  // value, not a stale closure.
  const listeningRef = useRef(false);
  useEffect(() => {
    listeningRef.current = apiRequestRunning;
  }, [apiRequestRunning]);

  const pollRef = useRef(null);
  const stopPolling = () => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  };

  // Once listening ends (triggered / timeout / stopped), stop the poll timer.
  useEffect(() => {
    if (!apiRequestRunning) stopPolling();
  }, [apiRequestRunning]);

  // On unmount (tester closed): stop the timer and tell the server to stop
  // listening, so nothing lingers.
  useEffect(() => {
    return () => {
      stopPolling();
      if (listeningRef.current && workflowId) {
        dispatch(workflowNodeListinerStop(workflowId));
      }
    };
  }, [dispatch, workflowId]);

  // Trigger nodes can't be "run" — instead we register a listener and short-poll
  // (one fast request per second) until the real WordPress event fires and its
  // payload is captured as the trigger's sample output.
  const handleListen = async () => {
    if (apiRequestRunning || !workflowId) return;
    dispatch(startApiCountdown(120));
    // Listen for this trigger only; the workflow may have others.
    const res = await dispatch(workflowNodeListiner({ id: workflowId, nodeId: node?.id }));
    // If registering the listener failed, the rejected reducer already cleared
    // the running flag — don't start polling.
    if (res?.meta?.requestStatus !== "fulfilled") return;
    stopPolling();
    pollRef.current = setInterval(() => {
      dispatch(decrementApiCountdown());
      dispatch(workflowNodeListinerPoll(workflowId));
    }, 1000);
  };
  const handleStopListen = () => {
    stopPolling();
    if (!workflowId) return;
    dispatch(workflowNodeListinerStop(workflowId));
  };

  const handleTest = async () => {
    if (isLoading) return;
    setIsLoading(true);
    try {
      const {
        layout,
        ...inputData
      } = values || {};
      const payload = {
        workflow_id: workFlow?.workflow?.id,
        workflow_hash: workFlow?.version?.hash,
        workflow_version_id: workFlow.version.id,
        target_node: {
          data: node?.data,
          type: node?.data?.action,
          id: node?.id
        },
        input: inputData
      };
      await dispatch(workFLowSingeNodeExction(payload));
    } finally {
      setIsLoading(false);
    }
  };

  if (isTrigger) {
    return <>
        {!apiRequestRunning ? <button style={primaryBtn} onClick={handleListen} className="mb-4">
            {__("Test Trigger", "zaplane")}
          </button> : <button onClick={handleStopListen} className="mb-4 flex items-center gap-2 h-9 px-4 bg-red-50 text-[var(--zaplane-danger)] font-medium rounded-[4px] hover:bg-red-100 transition-all">
            {__("Stop Listening", "zaplane")}
          </button>}

        {apiRequestRunning && <ZAPAlert status="info" title={__("Listening for trigger…", "zaplane")} description={__("Go perform the action that fires this trigger (for example, submit the form). The captured data will appear below.", "zaplane")} mt={4} />}

        {source === "node" && <TestDetails id={node?.id} workFlow={workFlow} source={source} isLoading={apiRequestRunning} />}
      </>;
  }

  return <>
      <button style={primaryBtn} onClick={handleTest} className="mb-4">
        {__("Test Action", "zaplane")}
      </button>

      {source === "node" && <TestDetails id={node?.id} workFlow={workFlow} source={source} isLoading={isLoading} />}
    </>;
};
export default TestRun;
