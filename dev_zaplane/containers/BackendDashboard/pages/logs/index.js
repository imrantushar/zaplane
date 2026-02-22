import React, { useEffect, useMemo, useState } from "react";
import {
    Text,
    Box,
    Button,
    Badge,
    HStack,
    Icon,
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
import { HistoryIcon } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";

const Logs = () => {
    const dispatch = useDispatch();
    const [activeRunId, setActiveRunId] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const { data = [], isLoading } = useSelector((state) => state.logs || {});

    useEffect(() => {
        dispatch(getRunsList());
    }, [dispatch]);

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
                        <Text className="zaplane-label">{__(row?.node?.app, 'zaplane')}</Text>
                        <Text className="zaplane-label" color="var(--zaplane-text-muted)">
                            {__(formatLabel(row?.node?.event), 'zaplane')}
                        </Text>
                    </Box>
                );
            },
            columnWidth: "180px",
            textAlign: "start",
        },
        {
            name: (
                <Text className="zaplane-label">
                    {__("Created At", "zaplane")}
                </Text>
            ),
            cell: (row) => {
                const { date, time } = formatDateTime(row.started_at);

                return (
                    <Box >
                        <Text className="zaplane-label">{__(date, 'zaplane')}</Text>
                        <Text className="zaplane-label" color="var(--zaplane-text-muted)">
                            {__(time, 'zaplane')}
                        </Text>
                    </Box>
                );
            },
            columnWidth: "180px",
            textAlign: "start",
        },
        {
            name: __('DURATION', 'zaplane'),
            cell: (row) => (
                <Text fontSize="sm">
                    {getDuration(row.started_at, row.finished_at)}
                </Text>
            ),
            columnWidth: "150px",
        },
        {
            name: __('Status', 'zaplane'),
            cell: (row) => (
                <Badge
                    {...statusStyle(row.status)}
                    borderRadius="full"
                    px={3}
                >
                    {row.status}
                </Badge>
            ),
            columnWidth: "120px",
        },
        {
            name: __('Action', 'zaplane'),
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
            columnWidth: "100px",
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
                    data={data}
                    showSubHeader={false}
                    showColumnFilter={false}
                    showPagination={false}
                    noDataText={__("No logs found", "zaplane")}
                    totalItems={data.length}
                    dataFetchingStatus={isLoading}
                    suffix="logs-table"
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
