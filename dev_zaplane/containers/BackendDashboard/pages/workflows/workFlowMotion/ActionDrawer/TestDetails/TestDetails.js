import { VStack, Box, Text } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import ZAPLoading from '@ZAPComponents/Loading';
import { resetSingleNodeExecution } from '@ZAPRedux/Slices/workFlowSlice/workFlowSlice';
import { useFormikContext } from 'formik';
import React, { useEffect } from 'react';
import ReactJson from 'react-json-view';
import { useDispatch, useSelector } from 'react-redux';

const TestDetails = ({ id, workFlow, source,isLoading }) => {
    const dispatch = useDispatch()
    const { singleNodeExecution } = useSelector(
        (state) => state.workflows
    );
    const { values } = useFormikContext();
    const selectedOutput = workFlow?.test_outputs?.[id]?.output || {};
    const inputData = singleNodeExecution?.input || values;
    const outputData = singleNodeExecution?.output?.data || selectedOutput;
    const isNode = source === "node"

    useEffect(() => {
        dispatch(resetSingleNodeExecution());
    }, [id, dispatch]);

    if (isLoading) return <ZAPLoading />
    if (!outputData || Object.keys(outputData).length === 0  && isNode) return null;


    return (

        <VStack spacing="4" align="stretch">
            <Box
                p="3"
                border="1px solid var(--zaplane-border-color)"
                borderRadius="md"
                bg="var(--zaplane-gray)"
            >
                <Text className="zaplane-label" fontWeight="bold" mb="2">
                    {__('Input', 'zaplane')}
                </Text>

                <ReactJson
                    src={inputData}
                    name="root"
                    collapsed={1}
                    enableClipboard={false}
                    displayDataTypes={false}
                />
            </Box>

            <Box
                p="3"
                border="1px solid var(--zaplane-border-color)"
                borderRadius="md"
                bg="var(--zaplane-gray)"
            >
                <Text fontWeight="bold" mb="2">
                    {__('Output', 'zaplane')}
                </Text>
                <ReactJson
                    src={outputData}
                    name="root"
                    collapsed={2}
                    enableClipboard={false}
                    displayDataTypes={false}
                />
            </Box>
        </VStack>
    );
};

export default TestDetails;
