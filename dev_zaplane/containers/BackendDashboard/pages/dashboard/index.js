import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import TopBar from '@ZAPComponents/TopBar';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import { Box, Button, Flex, Image, Text } from '@chakra-ui/react';
import { outlineBtn, primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { FiHelpCircle } from 'react-icons/fi';
import { useDispatch, useSelector } from 'react-redux';
import RecentLogs from './RecentLogs';
import ExecutedFlows from './ExecutedFlows';
import { getRunsList } from '@ZAPRedux/Slices/logsSlice/logsSlice';
import TotalExecutions from './TotalExecutions';
import { deshboardSumary, topExecutedFlows } from '@ZAPRedux/Slices/dashboardSlice/dashboardSlice';
import OverviewSection from './OverviewSection/OverviewSection';
import CreateWorkflowModal from '@ZAPComponents/CreateWorkflowModal';
import { IoIosArrowForward } from 'react-icons/io';
import { plugin_root_url } from '@ZAPUtils/helper';
import SubTopBar from '@ZAPComponents/SubTopBar';
import WhatsNew from '@ZAPComponents/WhatsNew/WhatsNew';

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
        <React.Fragment>
            <TopBar
                leftContent={() => (
                    <>
                        <Flex height='40px' width='40px' borderRadius='20px' gap='10px' background='var(--zaplane-second-primary)' alignItems='center' justifyContent='center'>
                            <Image
                                src={`${plugin_root_url}assets/images/zaplane.svg`}
                                boxSize="20px"
                            />
                        </Flex>
                        <IoIosArrowForward />
                        <ZAPLabel
                            as="h2"
                            color="var(--zapplane-font-color)"
                            type="subtitle"
                            fontWeight="medium"
                            label={__('Dashboard', 'zaplane')}
                        />
                    </>
                )}
            />
            <SubTopBar heading={__("Dashboard", "zaplane")}>
                <Button onClick={() => setIsModalOpen(true)} {...primaryBtn}>
                    {__("Create Workflow", "zaplane")}
                </Button>
            </SubTopBar>
            <Flex flexDirection='column' gap="24px" className="zaplane-page-content">
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

        </React.Fragment>
    );
};
