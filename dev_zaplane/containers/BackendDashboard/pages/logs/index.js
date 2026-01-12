import React, { useEffect, useState } from "react";
import {
    Table,
    Text,
    HStack,
    Box,
    Button,
    Flex,
    Spinner,
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";


import {
    getRunsList,
    retryNodeRun,
} from "@ZAPRedux/Slices/logsSlice/logsSlice";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import LogDetails from "@ZAPComponents/LogDetails";
import { getDuration } from "../workflows/workFlowMotion/helper";

const Logs = () => {
    const dispatch = useDispatch();

    const [showDetails, setShowDetails] = useState(false);
    const [activeRunId, setActiveRunId] = useState(null);

    const 
    {data,isLoading} = useSelector((state) => state.logs || {});
    console.log(data,isLoading,'data');

    useEffect(() => {
        dispatch(
            getRunsList()
        );
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
            <Flex align="center" justify="center" h="100%">
                <Spinner size="xl" />
            </Flex>
        );
    }
    if(!data?.length){
           return (
            <Flex align="center" justify="center" h="100%">
               <Text>There Have No data</Text>
            </Flex>
        );
    }

    return (
        <Table.Root size="sm" variant="outline">
            <Table.Header>
                <Table.Row>
                    <Table.ColumnHeader>CREATED AT</Table.ColumnHeader>
                    <Table.ColumnHeader>STATUS</Table.ColumnHeader>
                    <Table.ColumnHeader>DURATION / SIZE</Table.ColumnHeader>
                    <Table.ColumnHeader>NODES</Table.ColumnHeader>
                    <Table.ColumnHeader>ACTIONS</Table.ColumnHeader>
                </Table.Row>
            </Table.Header>

            <Table.Body>
                {data?.map((row) => (
                    <Table.Row key={row.id}>
                        <Table.Cell>
                            <Text fontSize="sm">
                                {row.started_at || "--"}
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
                                        setActiveRunId(row.id);
                                        setShowDetails(true);
                                        dispatch(nodeLogsRunDetails(row?.id));
                                    }}
                                >
                                    Details
                                </Button>

                                <Button
                                    size="xs"
                                    variant="outline"
                                    onClick={() =>
                                        dispatch(retryNodeRun(row?.id))
                                    }
                                >
                                    Re-execute
                                </Button>
                            </HStack>
                        </Table.Cell>
                    </Table.Row>
                ))}
            </Table.Body>
        </Table.Root>
    );
};

export default Logs;
