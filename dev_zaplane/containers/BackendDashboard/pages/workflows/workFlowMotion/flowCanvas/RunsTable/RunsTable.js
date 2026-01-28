import {
  Badge,
  HStack,
  Button,
  Text,

} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { useState } from "react";
import {
  getSingleRun,
  nodeLogsRunDetails,
} from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import LogDetails from "@ZAPComponents/LogDetails";
import ZAPLoading from "@ZAPComponents/Loading";
import { getDuration } from "@ZAPUtils/helper";
import ZAPTable from "@ZAPComponents/Table";
import { __ } from "@wordpress/i18n";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { statusStyle } from "../../../helper";



const RunsTable = ({ runs = [] }) => {
  const dispatch = useDispatch();
  const [activeRunId, setActiveRunId] = useState(null);
  const { isLoading } = useSelector((state) => state.workflows);
  const [drawerOpen, setDrawerOpen] = useState(false);

  return (
    <>
      <ZAPTable
        data={runs}
        rowKey="id"
        variant="line"
        isLoading={isLoading}
        noDataText='Have no History Yet'
        size="sm"
        columns={[
          {
            label: "Run ID",
            key: "id",
            textAlign: "center",
            render: (row) => <Text fontWeight="medium">{__(row.id, "zaplane")}</Text>,
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
                {__(row.status, "zaplane")}
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
                setDrawerOpen(true);
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
      <ZAPDrawer
        open={drawerOpen}
        onClose={() => {
          setDrawerOpen(false);
          setActiveRunId(null);
        }}
        title={__("Log Details", "zaplane")}
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

export default RunsTable;
