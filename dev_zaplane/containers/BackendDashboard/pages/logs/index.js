import React, { useEffect, useState } from "react";
import {
    Text,
    HStack,
    Box,
    Button,
    Flex,
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";

import {
    getRunsList,
    retryNodeRun,
} from "@ZAPRedux/Slices/logsSlice/logsSlice";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import LogDetails from "@ZAPComponents/LogDetails";
import ZAPLoading from "@ZAPComponents/Loading";
import ZAPTable from "@ZAPComponents/Table";
import { __, sprintf } from "@wordpress/i18n";
import { getDuration } from "@ZAPUtils/helper";
import TopBar from "@ZAPComponents/TopBar";
import ZAPDrawer from "@ZAPComponents/Drawer";

const Logs = () => {
    const dispatch = useDispatch();

    const [activeRunId, setActiveRunId] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);

    const { data, isLoading } = useSelector((state) => state.logs || {});

    useEffect(() => {
        dispatch(getRunsList());
    }, [dispatch]);

    const isSuccess = (status) => status === "completed";

    if (isLoading) {
        return <ZAPLoading />;
    }
    return (
        <>
            <TopBar
                render={() => (
                    <Box>
                        <Text
                            fontSize="lg"
                            fontWeight="600"
                            className="zaplane-label"
                        >
                            {__("Workflow Logs", "zaplane")}
                        </Text>
                    </Box>
                )}
            />

            <div className="zaplane-page-content">
                <ZAPTable
                    data={data}
                    rowKey="id"
                    variant="outline"
                    size="sm"
                    columns={[
                        {
                            label: "CREATED AT",
                            key: "started_at",
                            render: (row) => (
                                <Text fontSize="sm">
                                    {sprintf(__("%s", "zaplane"), row.started_at) || "--"}
                                </Text>
                            ),
                            textAlign: "center",
                        },
                        {
                            label: "STATUS",
                            key: "status",
                            render: (row) => (
                                <HStack spacing={2}>
                                    <Box
                                        w="8px"
                                        h="8px"
                                        borderRadius="full"
                                        bg={
                                            isSuccess(row.status)
                                                ? "green.500"
                                                : "red.500"
                                        }
                                    />
                                    <Text fontSize="sm">
                                        {sprintf(
                                            __('Status: %s', 'zapplane'),
                                            isSuccess(row.status)
                                                ? __('Success', 'zaplane')
                                                : __('Failed', 'zapplane')
                                        )}
                                    </Text>
                                </HStack>
                            ),
                        },
                        {
                            label: "DURATION / SIZE",
                            key: "duration",
                            render: (row) => (
                                <Text fontSize="sm">
                                    {sprintf(
                                        __('%s', 'zapplane'),
                                        getDuration(row.started_at, row.finished_at)
                                    )}
                                </Text>
                            ),
                            textAlign: "center",
                        },
                    ]}
                    actionsRenderer={(row) => (
                        <>
                            <Button
                                size="xs"
                                variant="outline"
                                onClick={() => {
                                    setActiveRunId(row.id);
                                    setDrawerOpen(true);
                                    dispatch(nodeLogsRunDetails(row.id));
                                }}
                            >
                                {__("Details", "zaplane")}
                            </Button>

                            <Button
                                size="xs"
                                variant="outline"
                                onClick={() =>
                                    dispatch(retryNodeRun(row.id))
                                }
                            >
                                {__("Re-execute", "zaplane")}
                            </Button>
                        </>
                    )}
                />
            </div>
            <ZAPDrawer
                open={drawerOpen}
                onClose={() => {
                    setDrawerOpen(false);
                    setActiveRunId(null);
                }}
                closeOnOverlayClick
                title={__("Run Details", "zaplane")}
                placement="end"
                size="md"
            >
                {activeRunId ? (
                    <LogDetails
                        runId={activeRunId}
                        onBack={() => {
                            setDrawerOpen(false);
                            setActiveRunId(null);
                        }}
                    />
                ) : null}
            </ZAPDrawer>
        </>
    );
};

export default Logs;
