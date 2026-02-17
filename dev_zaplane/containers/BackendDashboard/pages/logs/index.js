import React, { useEffect, useMemo, useState } from "react";
import {
    Text,
    Box,
    Button,
    Badge,
    HStack,
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
import { getDuration } from "@ZAPUtils/helper";
import { statusStyle } from "../workflows/helper";

const Logs = () => {
    const dispatch = useDispatch();
    const [activeRunId, setActiveRunId] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const { data = [], isLoading } = useSelector((state) => state.logs || {});

    useEffect(() => {
        dispatch(getRunsList());
    }, [dispatch]);

    const columns = useMemo(() => [
        {
            name: __('CREATED AT', 'zaplane'),
            cell: (row) => (
                <div className="zaplane-table-flex-col">
                    <span style={{ fontWeight: 600 }}>
                        {row.started_at || "--"}
                    </span>
                    <span style={{ fontSize: '12px', color: '#666' }}>
                        ID: {row.id}
                    </span>
                </div>
            ),
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
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => {
                        setActiveRunId(row.id);
                        setDrawerOpen(true);
                        dispatch(nodeLogsRunDetails(row.id));
                    }}
                >
                    {__("Details", "zaplane")}
                </Button>
            ),
            columnWidth: "100px",
            textAlign: "end",
        },
    ], [dispatch]);

    if (isLoading) {
        return <ZAPLoading />;
    }

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
