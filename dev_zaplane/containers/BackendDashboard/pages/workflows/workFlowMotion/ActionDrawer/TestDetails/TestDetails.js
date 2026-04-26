import { __ } from '@wordpress/i18n';
import ZAPLoading from '@ZAPComponents/Loading';
import { resetSingleNodeExecution } from '@ZAPRedux/Slices/workFlowSlice/workFlowSlice';
import { useFormikContext } from 'formik';
import React, { useEffect } from 'react';
import ReactJson from 'react-json-view';
import { useDispatch, useSelector } from 'react-redux';
import ZAPAlert from "@ZAPComponents/ZAPAlert";

const TestDetails = ({
  id,
  workFlow,
  source,
  isLoading,
  nodeType
}) => {
  const dispatch = useDispatch();
  const {
    singleNodeExecution
  } = useSelector(state => state.workflows);
  const {
    values
  } = useFormikContext();
  
  const selectedOutput = workFlow?.test_outputs?.[id]?.output || null;
  const inputData = singleNodeExecution[id]?.input || values || {};
  const outputData = singleNodeExecution[id]?.output || selectedOutput;
  const isNode = source === "node";
  
  useEffect(() => {
    dispatch(resetSingleNodeExecution());
  }, [id, dispatch]);

  if (isLoading) return <ZAPLoading />;
  
  if (!outputData && isNode) {
    if (nodeType === "trigger") {
      return (
        <div className="mt-4 text-center text-gray-500 py-4 border border-dashed rounded-md">
          {__("No test data yet — click Test to simulate", "zaplane")}
        </div>
      );
    }
    return null;
  }

  const isFilteredOut = outputData && Object.keys(outputData).length === 0;

  if (isFilteredOut && isNode) {
    if (nodeType === "trigger") {
      return (
        <div className="mt-4">
          <ZAPAlert 
            status="warning" 
            title={__("Trigger Filtered", "zaplane")} 
            description={__("Trigger would not fire for this data — check your trigger configuration.", "zaplane")} 
          />
        </div>
      );
    }
    return null;
  }

  return (
    <div style={{overflow:'hidden'}} className="flex flex-col gap-4 mt-4">
      <div className="p-3 border rounded-md bg-var(--zaplane-gray)">
          <span className="zaplane-label font-[bold] mb-2">
              {__('Input', 'zaplane')}
          </span>

          <ReactJson src={inputData} name="root" collapsed={1} enableClipboard={false} displayDataTypes={false} />
      </div>

      <div className="p-3 border rounded-md bg-var(--zaplane-gray)">
          <span className="font-[bold] mb-2">
              {__('Output', 'zaplane')}
          </span>
          <ReactJson src={outputData} name="root" collapsed={2} enableClipboard={false} displayDataTypes={false} />
      </div>
    </div>
  );
};
export default TestDetails;