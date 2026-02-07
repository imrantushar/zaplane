import { VStack, Box, Text } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import React from 'react';
import ReactJson from 'react-json-view';
import { useSelector } from 'react-redux';

const TestDetails = () => {
    const { singleNodeExecution, isLoading } = useSelector(
        (state) => state.workflows
    );


    if (!singleNodeExecution) {
        return <Text>{__('No data available', 'zaplane')}</Text>;
    }
    const inputData = singleNodeExecution?.input || {};
    const outputData = singleNodeExecution?.output?.data || {};

    return (
        <VStack spacing="4" align="stretch">
            {/* Input */}
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

            {/* Output */}
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
