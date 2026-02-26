import React, { useEffect, useMemo, useState } from "react";
import {
    Text,
    Box,
    Button,
    Badge,
    HStack,
    Icon,
    Flex,
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { __, sprintf } from "@wordpress/i18n";

import {
    getRunsList,
} from "@ZAPRedux/Slices/logsSlice/logsSlice";

import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";

import LogDetails from "@ZAPComponents/LogDetails";
import ZAPLoading from "@ZAPComponents/Loading";
import TopBar from "@ZAPComponents/TopBar";
import ZAPDrawer from "@ZAPComponents/Drawer";
import ListTable from "@ZAPComponents/ListTable";
import { formatDateTime, formatLabel, getDuration } from "@ZAPUtils/helper";
import { statusStyle } from "../workflows/helper";
import { HistoryIcon, TableArrow } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";

const Logs = () => {
    const dispatch = useDispatch();
    const [activeRunId, setActiveRunId] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const { data = [], currentPage, perPage, totalItems } = useSelector((state) => state.logs || {});
    const [loading, setLoading] = useState(data.length === 0);
    const handleRefresh = async (page = 1, per_page = 10) => {
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
                <Flex gap="2px" alignItems='center' >
                    <Text className="zaplane-label">
                        {__("App Name", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>

            ),
            cell: (row) => {
                return (
                    <Box >
                        <ZAPLabel label={row?.node?.app} type={"simple"} />
                        <Text className="zaplane-sub-title" color="var(--zaplane-text-muted)">
                            {/* {__(formatLabel(row?.node?.event), 'zaplane')} */}
                        </Text>
                    </Box>
                );
            },
            // columnWidth: "180px",
            textAlign: "start",
        },
        {
            name: (
                <Flex gap="2px" alignItems='center' justifyContent="center" ml='-32px'>
                    <Text className="zaplane-label">
                        {__("Created At", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>
            ),
            cell: (row) => {
                const { date, time } = formatDateTime(row.started_at);

                return (
                    <Box >
                        <ZAPLabel label={date} type={"simple"} />
                        <Text className="zaplane-sub-title" ml='-63px' color="var(--zaplane-text-muted)">
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
                <Flex gap="2px" alignItems='center' justifyContent="center" ml='-32px'>
                    <Text className="zaplane-label">
                        {__("Updated At", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>
            ),
            cell: (row) => {
                const { date, time } = formatDateTime(row.finished_at);

                return (
                    <Box>
                        <ZAPLabel label={date} type={"simple"} />
                        <Text className="zaplane-sub-title" ml='-63px' color="var(--zaplane-text-muted)">
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
                <Flex gap="2px" justifyContent="center" alignItems='center'>
                    <Text className="zaplane-label">
                        {__("DURATION", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>
            ),
            cell: (row) => (
                <ZAPLabel label={getDuration(row.started_at, row.finished_at)} type={"simple"} />
            ),
            // columnWidth: "150px",
        },
        {
            name: (<Flex gap="2px" justifyContent="center" alignItems='center'>
                <Text className="zaplane-label">
                    {__("Status", "zaplane")}
                </Text>
                <Icon as={TableArrow} />
            </Flex>),
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
                render={() => (
                    <Box>
                        <Text fontSize="lg" fontWeight="600">
                            {__("Workflow Logs", "zaplane")}
                        </Text>
                    </Box>
                )}
            />

            <div className="zaplane-page-content">
                <ListTable
                    columns={columns}
                    isRowSelectable={true}
                    data={data?.runs || []}
                    showSubHeader={false}
                    showColumnFilter={false}
                    showPagination={data?.runs?.length >= 10}
                    noDataText={__("No logs found", "zaplane")}
                    totalItems={totalItems}
                    dataFetchingStatus={loading}
                    suffix="logs-table"
                    currentPageNumber={currentPage}
                    perPage={perPage}
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
