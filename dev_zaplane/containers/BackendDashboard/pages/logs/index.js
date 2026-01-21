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
import { getDuration } from "../workflows/workFlowMotion/helper";
import ZAPLoading from "@ZAPComponents/Loading";
import ZAPTable from "@ZAPComponents/Table";
import { __ } from "@wordpress/i18n";
import TopBar from "@ZAPComponents/TopBar";

const Logs = () => {
    const dispatch = useDispatch();

    const [showDetails, setShowDetails] = useState(false);
    const [activeRunId, setActiveRunId] = useState(null);

    const { data, isLoading } = useSelector((state) => state.logs || {});

    useEffect(() => {
        dispatch(getRunsList());
    }, [dispatch]);

    const isSuccess = (status) => status === "completed";

    if (showDetails) {
        return (
            <LogDetails
                runId={activeRunId}
                onBack={() => {
                    setShowDetails(false);
                    setActiveRunId(null);
                }}
            />
        );
    }

    if (isLoading) {
        return (
            <ZAPLoading />
        );
    }

    if (!data?.length) {
        return (
            <Flex align="center" justify="center" h="300px">
                <Text>{__("No data found", "zaplane")}</Text>
            </Flex>
        );
    }

    return (
        <>
            <TopBar
                render={() => (
                    <Box>
                        <Text fontSize="lg" fontWeight="600" className="zaplane-label">
                            {__('Workflow Logs', 'zaplane')}
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
                            render: (row) => <Text fontSize="sm">{row.started_at || "--"}</Text>,
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
                                        bg={isSuccess(row.status) ? "green.500" : "red.500"}
                                    />
                                    <Text fontSize="sm">
                                        {isSuccess(row.status) ? "Success" : "Failed"}
                                    </Text>
                                </HStack>
                            ),
                        },
                        {
                            label: "DURATION / SIZE",
                            key: "duration",
                            render: (row) => (
                                <Text fontSize="sm">{getDuration(row.started_at, row.finished_at)}</Text>
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
                                    setShowDetails(true);
                                    dispatch(nodeLogsRunDetails(row.id));
                                }}
                            >
                                {__('Details', 'zaplane')}
                            </Button>

                            <Button
                                size="xs"
                                variant="outline"
                                onClick={() => dispatch(retryNodeRun(row.id))}
                            >
                                {__('Re-execute', 'zaplane')}
                            </Button>
                        </>
                    )}
                />
            </div>
        </>

    );
};

export default Logs;
