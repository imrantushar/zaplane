import { __ } from '@wordpress/i18n';
import ZAPLoading from '@ZAPComponents/Loading';
import { resetSingleNodeExecution } from '@ZAPRedux/Slices/workFlowSlice/workFlowSlice';
import { useFormikContext } from 'formik';
import React, { useEffect } from 'react';
import ReactJson from 'react-json-view';
import { useDispatch, useSelector } from 'react-redux';
import { zaplaneJsonViewTheme } from '@ZAPUtils/jsonViewTheme';
const TestDetails = ({
  id,
  workFlow,
  source,
  isLoading
}) => {
  const dispatch = useDispatch();
  const {
    singleNodeExecution
  } = useSelector(state => state.workflows);
  const {
    values
  } = useFormikContext();
  const selectedOutput = workFlow?.test_outputs?.[id]?.output || {};
  const inputData = singleNodeExecution[id]?.input || values || {};
  const outputData = singleNodeExecution[id]?.output || selectedOutput;
  const isNode = source === "node";
  useEffect(() => {
    dispatch(resetSingleNodeExecution());
  }, [id, dispatch]);
  if (isLoading) return <ZAPLoading />;
  if (!outputData || Object.keys(outputData).length === 0 && isNode) return null;
  return <div style={{overflow:'hidden'}} className="flex flex-col gap-4">
            <div className="p-3 border border-[var(--zaplane-border-color)] rounded-md bg-[var(--zaplane-gray)]">
                <span className="zaplane-label font-[bold] mb-2">
                    {__('Input', 'zaplane')}
                </span>

                <ReactJson src={inputData} name="root" collapsed={1} enableClipboard={false} displayDataTypes={false} theme={zaplaneJsonViewTheme} />
            </div>

            <div className="p-3 border border-[var(--zaplane-border-color)] rounded-md bg-[var(--zaplane-gray)]">
                <span className="font-[bold] mb-2">
                    {__('Output', 'zaplane')}
                </span>
                <ReactJson src={outputData} name="root" collapsed={2} enableClipboard={false} displayDataTypes={false} theme={zaplaneJsonViewTheme} />
            </div>
        </div>;
};
export default TestDetails;