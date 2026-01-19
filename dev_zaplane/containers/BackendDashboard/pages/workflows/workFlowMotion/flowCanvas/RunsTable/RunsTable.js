import {
  Table,
  Badge,
  HStack,
  Button,
  Text,
  Spinner,
  Flex,
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { useState } from "react";
import {
  getNodeLogDetails,
  getSingleRun,
  nodeLogsRunDetails,
} from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import LogDetails from "@ZAPComponents/LogDetails";
import { getDuration, statusStyle } from "../../helper";
import ZAPLoading from "@ZAPComponents/Loading";
import ZAPTable from "@ZAPComponents/Table";
import { __ } from "@wordpress/i18n";



const RunsTable = ({ runs = [] }) => {
  const dispatch = useDispatch();

  const [showDetails, setShowDetails] = useState(false);
  const [activeRunId, setActiveRunId] = useState(null);
  const { isLoading } = useSelector((state) => state.workflows);

  
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

  

  return (
    <ZAPTable
      data={runs}
      rowKey="id"
      variant="line"
      isLoading={isLoading}
      noDataText='Have no History Yet'
      size="sm"
      caption="Workflow Execution History"
      columns={[
        {
          label: "Run ID",
          key: "id",
          render: (row) => <Text fontWeight="medium">#{row.id}</Text>,
        },
        {
          label: "Status",
          key: "status",
          render: (row) => (
            <Badge
              px="2"
              py="0.5"
              rounded="md"
              fontSize="xs"
              textTransform="capitalize"
              {...statusStyle(row.status)}
            >
              {row.status}
            </Badge>
          ),
        },
        {
          label: "Duration",
          key: "duration",
          render: (row) => (
            <Text textAlign="center" fontSize="sm">
              {getDuration(row.started_at, row.finished_at)}
            </Text>
          ),
        },
      ]}
      actionsRenderer={(row) => (
        <HStack justify="flex-end" spacing="1">
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
            onClick={() => dispatch(getSingleRun(row.id))}
          >
            {__('Re-execute', 'zaplane')}
          </Button>
        </HStack>
      )}
      footerRenderer={() => (
        <Text fontSize="sm" color="gray.600">
          Total Runs: {runs.length}
        </Text>
      )}
    />

  );
};

export default RunsTable;
