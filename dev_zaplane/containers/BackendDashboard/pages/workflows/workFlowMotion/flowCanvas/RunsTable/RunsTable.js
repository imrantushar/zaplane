import {
  Table,
  Badge,
  HStack,
  Button,
  Text,
} from "@chakra-ui/react";
import { useDispatch } from "react-redux";
import { useState } from "react";
import {
  getNodeLogDetails,
  getSingleRun,
  nodeLogsRunDetails,
} from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import LogDetails from "../LogDetails/LogDetails";


const RunsTable = ({ runs = [] }) => {
  const dispatch = useDispatch();

  const [showDetails, setShowDetails] = useState(false);
  const [activeRunId, setActiveRunId] = useState(null);

  const statusStyle = (status) => {
    switch (status) {
      case "completed":
        return { color: "green.600", bg: "green.50" };
      case "running":
        return { color: "blue.600", bg: "blue.50" };
      case "failed":
        return { color: "red.600", bg: "red.50" };
      default:
        return { color: "gray.600", bg: "gray.50" };
    }
  };

  /* ===============================
     DETAILS VIEW (TABLE HIDDEN)
     =============================== */
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

  /* ===============================
     TABLE VIEW
     =============================== */
  if (!runs.length) {
    return (
      <Text fontSize="sm" color="gray.500">
        No execution history found.
      </Text>
    );
  }

  return (
    <Table.Root size="sm" variant="line">
      <Table.Caption>Workflow Execution History</Table.Caption>

      <Table.Header>
        <Table.Row>
          <Table.ColumnHeader>Run ID</Table.ColumnHeader>
          <Table.ColumnHeader>Workflow ID</Table.ColumnHeader>
          <Table.ColumnHeader>Status</Table.ColumnHeader>
          <Table.ColumnHeader>Started</Table.ColumnHeader>
          <Table.ColumnHeader>Finished</Table.ColumnHeader>
          <Table.ColumnHeader textAlign="right">
            Actions
          </Table.ColumnHeader>
        </Table.Row>
      </Table.Header>

      <Table.Body>
        {runs.map((run) => (
          <Table.Row key={run.id}>
            <Table.Cell>
              <Text fontWeight="medium">#{run.id}</Text>
            </Table.Cell>

            <Table.Cell>
              <Text fontSize="sm">{run.workflow_id}</Text>
            </Table.Cell>

            <Table.Cell>
              <Badge
                px="2"
                py="0.5"
                rounded="md"
                fontSize="xs"
                textTransform="capitalize"
                {...statusStyle(run.status)}
              >
                {run.status}
              </Badge>
            </Table.Cell>

            <Table.Cell>
              <Text fontSize="sm">{run.started_at || "—"}</Text>
            </Table.Cell>

            <Table.Cell>
              <Text fontSize="sm">{run.finished_at || "—"}</Text>
            </Table.Cell>

            <Table.Cell textAlign="right">
              <HStack justify="flex-end" spacing="1">
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
                  onClick={() => dispatch(getSingleRun(run.id))}
                >
                  Re-execute
                </Button>
              </HStack>
            </Table.Cell>
          </Table.Row>
        ))}
      </Table.Body>

      <Table.Footer>
        <Table.Row>
          <Table.Cell colSpan={6}>
            <Text fontSize="sm" color="gray.600">
              Total Runs: {runs.length}
            </Text>
          </Table.Cell>
        </Table.Row>
      </Table.Footer>
    </Table.Root>
  );
};

export default RunsTable;
