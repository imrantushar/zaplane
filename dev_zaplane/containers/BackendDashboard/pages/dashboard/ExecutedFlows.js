import React from 'react';
import { Box, Text, Flex, Stack } from "@chakra-ui/react";
import { __ } from '@wordpress/i18n';
const ExecutedFlows = () => {
    return (
         <Box
      bg="var(--zaplane-background)"
      borderRadius="lg"
      boxShadow="md"
      p={4}
      w="100%"
      maxW="400px"
    >
      <Text className='zaplane-label' mb={3}>
        {__('Top Executed Flows', 'zaplane')}

      </Text>
      <Stack spacing={2}>
        <Flex justify="space-between" align="center">
          <Text className='zaplane-label'>{__('Untitled Flow', 'zaplane')}</Text>
          <Text className='zaplane-label'>{__('0', 'zaplane')}</Text>
        </Flex>
      </Stack>
    </Box>
    );
};

export default ExecutedFlows;