import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import TopBar from '@ZAPComponents/TopBar';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import { Box, Button, Flex, Text } from '@chakra-ui/react';
import { outlineBtn } from '../../../../../assets/scss/chakra/recipe';
import { FiHelpCircle } from 'react-icons/fi';
import { useDispatch, useSelector } from 'react-redux';
import RecentLogs from './RecentLogs';
import ExecutedFlows from './ExecutedFlows';
import { getRunsList } from '@ZAPRedux/Slices/logsSlice/logsSlice';
import TotalExecutions from './TotalExecutions';

export default function Dashboard() {
      const dispatch = useDispatch();
     const { data } = useSelector((state) => state.logs || {});
      useEffect(() => {
             dispatch(getRunsList());
         }, [dispatch]);
    return (
        <React.Fragment>
            <TopBar
                leftContent={() => (
                    <ZAPLabel
                        label={__('Dashboard', 'zaplane')}
                        variant="primary"
                    />
                )}
                rightContent={() => (
                    <Flex gap={3} alignItems="center">
                        <Button
                            {...outlineBtn}
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--zaplane-font-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><g transform="scale(0.9) translate(1.5,1.5)"><path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"></path><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"></path><path d="M8 6v8"></path></g></svg>
                            {__("What's New")}
                        </Button>
                        <Button
                            {...outlineBtn}
                            onClick={() => {
                                window.open('https://zaplane.com/', '_blank');
                            }}
                        >
                            <FiHelpCircle color='var(--zaplane-font-color)' />
                            {__("Help")}
                        </Button>
                    </Flex>
                )}
            />
            <Box className="zaplane-page-content">
                <Flex>
                    <Box width="40%">
                        <ExecutedFlows />
                    </Box>
                    <Box width="60%">
                       <TotalExecutions />
                    </Box>
                </Flex>
                <RecentLogs data={data} />
            </Box>

        </React.Fragment>
    );
};
