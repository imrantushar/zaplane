import React from 'react';
import { Box, Text, Flex, Stack } from "@chakra-ui/react";
import { __ } from '@wordpress/i18n';
import ZAPDivider from '@ZAPComponents/ZAPDivider';
import {  useSelector } from 'react-redux';

import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
const ExecutedFlows = () => {
  const { topExecutedFlows: flows } = useSelector((state) => state.dashboard);
  return (
    <Box
      bg="var(--zaplane-background)"
      borderRadius="lg"
      boxShadow="md"
      w="100%"
    >
      <Text className='zaplane-label' fontSize='14px' p={"24px"} >
        {__('Top Executed Flows', 'zaplane')}

      </Text>
      <ZAPDivider />
      <Stack spacing={2}>
        <Flex flexDirection='column' gap='6px' p="24px" >
          {
            flows.map((f) => 
              <>
              <Flex justifyContent="space-between">
                <ZAPLabel label={f.title} type='simple'/>
                <ZAPLabel label={f.total_runs} type='simple'/>
                
              </Flex>
              </>
            )
          }

        </Flex>
      </Stack>
    </Box>
  );
};

export default ExecutedFlows;