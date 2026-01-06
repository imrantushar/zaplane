import React, { useEffect, useState } from "react";
import {
    Table,
    Text,
    HStack,
    Box,
    Button,
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";

import LogDetails from "./LogDetails/LogDetails";
import { getRunsList, getSingleRunDetails, retryNodeRun, } from "@ZAPRedux/Slices/logsSlice/logsSlice";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";



const Logs = () => {
    const dispatch = useDispatch();
    const [expandedRowId, setExpandedRowId] = useState(null);

    const runs = useSelector((state) => state.logs?.data || []);
    console.log(runs);


    useEffect(() => {
        dispatch(
            getRunsList({
                limit: 20,
                offset: 0,
            })
        );
    }, [dispatch]);

    const getDuration = (start, end) => {
        if (!start || !end) return "--";
        const startTime = new Date(start);
        const endTime = new Date(end);
        const seconds = Math.round((endTime - startTime) / 1000);
        return `${seconds}s`;
    };

    const isSuccess = (status) => status === "completed";

    return (
        <Table.Root size="sm" variant="outline">
            {!expandedRowId && (
                <Table.Header>
                    <Table.Row>
                        <Table.ColumnHeader>CREATED AT</Table.ColumnHeader>
                        <Table.ColumnHeader>STATUS</Table.ColumnHeader>
                        <Table.ColumnHeader>DURATION / SIZE</Table.ColumnHeader>
                        <Table.ColumnHeader>NODES</Table.ColumnHeader>
                        <Table.ColumnHeader>ACTIONS</Table.ColumnHeader>
                    </Table.Row>
                </Table.Header>
            )}

            <Table.Body>
                {expandedRowId ? (
                    <Table.Row>
                        <Table.Cell colSpan={5} bg="gray.50">
                            <LogDetails runId={expandedRowId} />
                        </Table.Cell>
                    </Table.Row>
                ) : (
                    runs?.runs?.map((row) => (
                        <Table.Row key={row.id}>
                            <Table.Cell>
                                <Text fontSize="sm">
                                    {row.started_at}
                                </Text>
                            </Table.Cell>

                            <Table.Cell>
                                <HStack gap="2">
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
                                        {isSuccess(row.status)
                                            ? "Success"
                                            : "Failed"}
                                    </Text>
                                </HStack>
                            </Table.Cell>

                            <Table.Cell>
                                <Text fontSize="sm">
                                    {getDuration(
                                        row.started_at,
                                        row.finished_at
                                    )}
                                </Text>
                                <Text fontSize="xs" color="gray.500">
                                    --
                                </Text>
                            </Table.Cell>

                            <Table.Cell>
                                <Text fontSize="sm">--</Text>
                            </Table.Cell>

                            <Table.Cell>
                                <HStack>
                                    <Button
                                        size="xs"
                                        variant="outline"
                                        onClick={() => {
                                            dispatch(nodeLogsRunDetails(row?.id))
                                            setExpandedRowId(row.id)
                                        }

                                        }

                                    >
                                        Details
                                    </Button>

                                    <Button
                                        size="xs"
                                        variant="outline"
                                        onClick={()=> dispatch(retryNodeRun(row?.id))}

                                    >
                                        Re-execute
                                    </Button>
                                </HStack>
                            </Table.Cell>
                        </Table.Row>
                    ))
                )}
            </Table.Body>
        </Table.Root>
    );
};

export default Logs;
