import React, { useEffect } from "react";
import {
    Table,
    Text,
    HStack,
    Box,
    Button,
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { getQueueList } from "@ZAPRedux/Slices/queueSlice/queueSlice";


const Queue = () => {
    const dispatch = useDispatch();
    const queue = useSelector(
        (state) => state.queue?.data || []
    );

    useEffect(() => {
        dispatch(getQueueList());
    }, [dispatch]);

    const isSuccess = (status) => status === "completed";

    return (
        <Table.Root size="sm" variant="outline">
            <Table.Header>
                <Table.Row>
                    <Table.ColumnHeader>CREATED AT</Table.ColumnHeader>
                    <Table.ColumnHeader>STATUS</Table.ColumnHeader>
                    <Table.ColumnHeader>DURATION / SIZE</Table.ColumnHeader>
                    <Table.ColumnHeader>NODES</Table.ColumnHeader>
                </Table.Row>
            </Table.Header>

            <Table.Body>
                {queue.map((row) => (
                    <Table.Row key={row.id}>
                        <Table.Cell>
                            <Text fontSize="sm">
                                {row.available_at}
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
                                    {row.status}
                                </Text>
                            </HStack>
                        </Table.Cell>

                        <Table.Cell>
                            <Text fontSize="sm">--</Text>
                            <Text fontSize="xs" color="gray.500">
                                --
                            </Text>
                        </Table.Cell>

                        <Table.Cell>
                            <Text fontSize="sm">
                                {row.node_key}
                            </Text>
                        </Table.Cell>
                    </Table.Row>
                ))}

                {!queue.length && (
                    <Table.Row>
                        <Table.Cell colSpan={5}>
                            <Text
                                fontSize="sm"
                                color="gray.500"
                                textAlign="center"
                            >
                                No queue data found
                            </Text>
                        </Table.Cell>
                    </Table.Row>
                )}
            </Table.Body>
        </Table.Root>
    );
};

export default Queue;
