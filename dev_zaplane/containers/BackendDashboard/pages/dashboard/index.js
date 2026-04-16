import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Box, Button, Flex } from '@chakra-ui/react';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { useDispatch, useSelector } from 'react-redux';
import RecentLogs from './RecentLogs';
import ExecutedFlows from './ExecutedFlows';
import { getRunsList } from '@ZAPRedux/Slices/logsSlice/logsSlice';
import TotalExecutions from './TotalExecutions';
import { deshboardSumary, topExecutedFlows } from '@ZAPRedux/Slices/dashboardSlice/dashboardSlice';
import OverviewSection from './OverviewSection/OverviewSection';
import CreateWorkflowModal from '@ZAPComponents/CreateWorkflowModal';
import PageLayout from '@ZAPComponents/PageLayout';

export default function Dashboard() {
    const dispatch = useDispatch();
    const { data = [] } = useSelector((state) => state.logs || {});
    const [isModalOpen, setIsModalOpen] = useState(false);
    useEffect(() => {
        dispatch(getRunsList());
        dispatch(topExecutedFlows());
        dispatch(deshboardSumary());
    }, [dispatch]);

    return (
        <PageLayout
            title="Dashboard"
            actions={
                <Button onClick={() => setIsModalOpen(true)} {...primaryBtn}>
                    {__("Create Workflow", "zaplane")}
                </Button>
            }
        >
            <Flex flexDirection='column' gap="24px">
                <OverviewSection />
                <TotalExecutions />
                <Flex gap="24px">
                    <Box width='40%'>
                        <ExecutedFlows />
                    </Box>
                    <Box width='60%'>
                        <RecentLogs data={data} />
                    </Box>


                </Flex>

            </Flex>
            <CreateWorkflowModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
            />

        </PageLayout>
    );
};
