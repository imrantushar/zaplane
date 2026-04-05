import React, { useEffect, useState } from "react";
import {
    Text,
    Box,
    HStack,
    Icon,
    Flex,
    Image,
    Button,

} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { __ } from "@wordpress/i18n";

import {
    getRunsList,
} from "@ZAPRedux/Slices/logsSlice/logsSlice";

import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";

import LogDetails from "@ZAPComponents/LogDetails";
import TopBar from "@ZAPComponents/TopBar";
import ZAPDrawer from "@ZAPComponents/Drawer";
import ListTable from "@ZAPComponents/ListTable";
import { formatDateTime, formatLabel, getDuration, plugin_root_url } from "@ZAPUtils/helper";
import { HistoryIcon } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { IoIosArrowForward } from "react-icons/io";
import { outlineBtn } from "../../../../../assets/scss/chakra/recipe";
import { FiHelpCircle } from "react-icons/fi";
import SubTopBar from "@ZAPComponents/SubTopBar";

const Logs = () => {
    const dispatch = useDispatch();
    const [activeRunId, setActiveRunId] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const { data = [], currentPage, perPage, itemPerPage, totalItems } = useSelector((state) => state.logs || {});
    const [loading, setLoading] = useState(data.length === 0);

    const handleRefresh = async (page = 1, per_page = 20) => {
        setLoading(true)
        await dispatch(getRunsList({ page, per_page }));
        setLoading(false)
    };

    useEffect(() => {
        handleRefresh()
    }, []);

    const handlePageChange = (newPage) => {
        handleRefresh(newPage, perPage)
    };

    const handlePerPageChange = (itemsPerPage) => {
        handleRefresh(currentPage, itemsPerPage)
    };

    const columns = [
        {
            name: (
                <Text className="zaplane-label">
                    {__("App Name", "zaplane")}
                </Text>

            ),
            cell: (row) => {
                return (
                    <Box >
                        <ZAPLabel label={row?.node?.app} type={"simple"} />
                        <Text className="zaplane-sub-title" color="var(--zaplane-text-muted)">
                            {__(formatLabel(row?.node?.event), 'zaplane')}
                        </Text>
                    </Box>
                );
            },
            // columnWidth: "180px",
            textAlign: "start",
        },
        {
            name: (
                <Text className="zaplane-label" ml='-33px'>
                    {__("Created At", "zaplane")}
                </Text>
            ),
            cell: (row) => {
                const { date, time } = formatDateTime(row.started_at);

                return (
                    <Box >
                        <ZAPLabel label={date} type={"simple"} />
                        <Text className="zaplane-sub-title" ml='-38px' color="var(--zaplane-text-muted)">
                            {__(time, 'zaplane')}
                        </Text>
                    </Box>
                );
            },
            // columnWidth: "180px",
            textAlign: "center",
        },
        {
            name: (
                <Text className="zaplane-label" ml='-33px'>
                    {__("Updated At", "zaplane")}
                </Text>

            ),
            cell: (row) => {
                const { date, time } = formatDateTime(row.finished_at);

                return (
                    <Box>
                        <ZAPLabel label={date} type={"simple"} />
                        <Text className="zaplane-sub-title" ml='-38px' color="var(--zaplane-text-muted)">
                            {__(time, 'zaplane')}
                        </Text>
                    </Box>
                );
            },
            // columnWidth: "160px",
            textAlign: "center",
        },
        {
            name: (

                <Text className="zaplane-label">
                    {__("DURATION", "zaplane")}
                </Text>

            ),
            cell: (row) => (
                <ZAPLabel label={getDuration(row.started_at, row.finished_at)} type={"simple"} />
            ),
            // columnWidth: "150px",
        },
        {
            name: (
                <Text className="zaplane-label">
                    {__("Node Count", "zaplane")}
                </Text>

            ),
            cell: (row) => (
                <ZAPLabel label={row.node_count} type={"simple"} />
            ),
            // columnWidth: "150px",
        },
        {
            name: (
                <Text className="zaplane-label">
                    {__("Status", "zaplane")}
                </Text>
            ),
            cell: (row) => (
                <HStack spacing={2} justifyContent={"center"}>
                    <Box
                        w="8px"
                        h="8px"
                        borderRadius="full"
                        bg={row.status === 'completed' ? "green.500" : "red.500"}
                    />
                    <ZAPLabel label={row.status === 'completed'
                        ? __("Success", "zaplane")
                        : __("Failed", "zaplane")} type={"simple"} />
                </HStack>
            ),
            // columnWidth: "120px",
        },
        {
            name: (
                <Text className="zaplane-label">
                    {__("Action", "zaplane")}
                </Text>),
            cell: (row) => (
                <HStack justify="flex-end" spacing="1" justifyContent={"center"}>
                    <ZAPTooltip content={__("Details", 'zaplane')}>
                        <Box
                            display="flex"
                            p={"5px 6px"}
                            justifyContent="center"
                            alignItems="center"
                            borderRadius="2.917px"
                            border="1px solid var(--zaplane-border-color)"
                            onClick={() => {
                                setActiveRunId(row.id);
                                setDrawerOpen(true);
                                dispatch(nodeLogsRunDetails(row.id));
                            }}
                        >
                            <Icon
                                height="20px"
                                width="20px"
                                as={HistoryIcon}
                            />
                        </Box>
                    </ZAPTooltip>
                </HStack>


            ),
            // columnWidth: "100px",
            textAlign: "center",
        },
    ];

    // if (isLoading) {
    //     return <ZAPLoading />;
    // }

    return (
        <>
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
                            label={__('Wokflows Logs', 'zaplane')}
                        />
                    </>
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
            <SubTopBar heading={__("Workflows Logs", "zaplane")} />

            <div className="zaplane-page-content">
                <ListTable
                    columns={columns}
                    isRowSelectable={false}
                    data={data || []}
                    showSubHeader={false}
                    showColumnFilter={false}
                    showPagination={totalItems >= 20}
                    noDataText={__("No logs found", "zaplane")}
                    totalItems={totalItems}
                    dataFetchingStatus={loading}
                    suffix="logs-table"
                    currentPageNumber={currentPage}
                    perPage={perPage}
                    rowsPerPage={itemPerPage}
                    onChangePage={handlePageChange}
                    onChangeItemsPerPage={handlePerPageChange}
                />
            </div>

            <ZAPDrawer
                open={drawerOpen}
                arrowClose
                onClose={() => {
                    setDrawerOpen(false);
                    setActiveRunId(null);
                }}
                closeOnOverlayClick
                title={__("Run Details", "zaplane")}
                placement="end"
                size="md"
            >
                {activeRunId && (
                    <LogDetails
                        runId={activeRunId}
                        onBack={() => {
                            setDrawerOpen(false);
                            setActiveRunId(null);
                        }}
                    />
                )}
            </ZAPDrawer>
        </>
    );
};

export default Logs;
