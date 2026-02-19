import React from 'react';
import { Box, Text, Flex, Stack } from "@chakra-ui/react";
import { __ } from '@wordpress/i18n';
import ZAPDivider from '@ZAPComponents/ZAPDivider';
const ExecutedFlows = () => {
    return (
         <Box
      bg="var(--zaplane-background)"
      borderRadius="lg"
      boxShadow="md"
      // p={"24px"}
      w="100%"
      // maxW="400px"
    >
      <Text className='zaplane-label' p={"24px"} >
        {__('Top Executed Flows', 'zaplane')}

      </Text>
      <ZAPDivider/>
      <Stack spacing={2}>
        <Box p="24px">
          <Text className='zaplane-label'>{__('Untitled Flow', 'zaplane')}</Text>
          <Text className='zaplane-label'>{__('Untitled Flow', 'zaplane')}</Text>
          <Text className='zaplane-label'>{__('0', 'zaplane')}</Text>
        </Box>
      </Stack>
    </Box>
    );
};

export default ExecutedFlows;