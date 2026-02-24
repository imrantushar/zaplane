import {
  Badge,
  HStack,
  Button,
  Text,
  Icon,
  Box
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { useState } from "react";
import LogDetails from "@ZAPComponents/LogDetails";
import ZAPLoading from "@ZAPComponents/Loading";
import { getDuration } from "@ZAPUtils/helper";
import ZAPTable from "@ZAPComponents/Table";
import { __ } from "@wordpress/i18n";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { statusStyle } from "../../../helper";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import { getSingleRun } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowRuns";
import ListTable from "@ZAPComponents/ListTable";
import { HistoryIcon, ReExcutionIcon } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";




const RunsTable = ({ runs = [] }) => {
  const dispatch = useDispatch();
  const [activeRunId, setActiveRunId] = useState(null);
  const { isLoading } = useSelector((state) => state.workflows);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const columns = [
    {
      name: __('Run ID', 'zaplane'),
      cell: (row) => (
        <Text className="zaplane-label">{__(row.id, "zaplane")}</Text>
      ),
      // columnWidth: "180px",
      textAlign: "center",
    },
    {
      name: __('Status', 'zaplane'),
      cell: (row) => (
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
      columnWidth: "120px",
    },
    {
      name: __('DURATION', 'zaplane'),
      cell: (row) => (
        <Text className="zaplane-label">
          {getDuration(row.started_at, row.finished_at)}
        </Text>
      ),
      // columnWidth: "150px",
    },

    {
      name: __('Action', 'zaplane'),
      cell: (row) => (
        <HStack justify="flex-end" spacing="1" justifyContent={"center"}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="2.917px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => {
                setActiveRunId(row.id);
                setDrawerOpen(true);
                dispatch(nodeLogsRunDetails(row.id));
              }}
            >
              <Icon
                height="20px"
                width="20px"
                as={HistoryIcon}
              />
            </Box>
          <ZAPTooltip content={__("Re-Excute", 'zaplane')}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="2.917px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => dispatch(getSingleRun(row.id))}>
              <Icon
                height="20px"
                width="20px"
                as={ReExcutionIcon}

              />
            </Box>
          </ZAPTooltip>


        </HStack>
      ),
      // columnWidth: "100px",
      textAlign: "center",
    },
  ]

  return (
    <>
      <ListTable
        columns={columns}
        isRowSelectable={false}
        data={runs}
        showSubHeader={false}
        showColumnFilter={false}
        showPagination={false}
        noDataText={__("No history found", "zaplane")}
        totalItems={runs.length}
        dataFetchingStatus={isLoading}
        suffix="history-table"
      />
      <ZAPDrawer
        open={drawerOpen}
        arrowClose={true}
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
