import React, { useEffect, useState } from "react";
import {
  Table,
  Text,
  HStack,
  Box,
  Button,
  Flex,
  Spinner,
  Badge,
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";

import {
  getRunsList,
  retryNodeRun,
} from "@ZAPRedux/Slices/logsSlice/logsSlice";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import LogDetails from "@ZAPComponents/LogDetails/LogDetails";

const Logs = () => {
  const dispatch = useDispatch();
  const [showDetails, setShowDetails] = useState(false);
  const [activeRunId, setActiveRunId] = useState(null);

  const runs = useSelector((state) => state.logs?.data?.runs || []);
  const loading = useSelector((state) => state.logs?.loading);

  useEffect(() => {
    dispatch(getRunsList({ limit: 20, offset: 0 }));
  }, [dispatch]);

  const getDuration = (start, end) => {
    if (!start || !end) return "--";
    const seconds = Math.round((new Date(end) - new Date(start)) / 1000);
    return `${seconds}s`;
  };

  const statusStyle = (status) => {
    switch (status) {
      case "completed":
        return { color: "green.600", bg: "green.50", text: "Success" };
      case "failed":
        return { color: "red.600", bg: "red.50", text: "Failed" };
      case "running":
        return { color: "blue.600", bg: "blue.50", text: "Running" };
      default:
        return { color: "gray.600", bg: "gray.50", text: status || "—" };
    }
  };

  if (loading) {
    return (
      <Flex align="center" justify="center" h="100%">
        <Spinner size="xl" />
      </Flex>
    );
  }

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

  if (!runs.length) {
    return (
      <Flex align="center" justify="center" h="100%">
        <Text>No runs available</Text>
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
        {runs.map((run) => {
          const status = statusStyle(run.status);
          return (
            <Table.Row key={run.id}>
              <Table.Cell>
                <Text fontSize="sm">{run.started_at || "—"}</Text>
              </Table.Cell>

              <Table.Cell>
                <HStack gap="2">
                  <Box w="8px" h="8px" borderRadius="full" bg={status.color} />
                  <Badge px="2" py="0.5" rounded="md" fontSize="xs" bg={status.bg} color={status.color}>
                    {status.text}
                  </Badge>
                </HStack>
              </Table.Cell>

              <Table.Cell>
                <Text fontSize="sm">
                  {getDuration(run.started_at, run.finished_at)}
                </Text>
                <Text fontSize="xs" color="gray.500">
                  --
                </Text>
              </Table.Cell>

              <Table.Cell>
                <Text fontSize="sm">--</Text>
              </Table.Cell>

              <Table.Cell>
                <HStack spacing="2">
                  <Button
                    size="xs"
                    variant="outline"
                    onClick={() => {
                      setActiveRunId(run.id);
                      setShowDetails(true);
                      dispatch(nodeLogsRunDetails(run.id));
                    }}
                  >
                    Details
                  </Button>

                  <Button
                    size="xs"
                    variant="outline"
                    onClick={() => dispatch(retryNodeRun(run.id))}
                  >
                    Re-execute
                  </Button>
                </HStack>
              </Table.Cell>
            </Table.Row>
          );
        })}
      </Table.Body>
    </Table.Root>
  );
};

export default Logs;
