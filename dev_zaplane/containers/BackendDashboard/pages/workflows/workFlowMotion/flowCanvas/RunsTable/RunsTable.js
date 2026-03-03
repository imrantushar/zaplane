import {
  Badge,
  HStack,
  Button,
  Text,
  Icon,
  Box
} from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import { useEffect, useState } from "react";
import LogDetails from "@ZAPComponents/LogDetails";
import ZAPLoading from "@ZAPComponents/Loading";
import { getDuration } from "@ZAPUtils/helper";
import ZAPTable from "@ZAPComponents/Table";
import { __ } from "@wordpress/i18n";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { statusStyle } from "../../../helper";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import { getRunWorkFlow, getSingleRun } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowRuns";
import ListTable from "@ZAPComponents/ListTable";
import { HistoryIcon, ReExcutionIcon } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";




const RunsTable = ({ id }) => {
  const dispatch = useDispatch();
  const [activeRunId, setActiveRunId] = useState(null);
  const { runs = [], currentPage, perPage } = useSelector((state) => state.workflows);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [loading, setLoading] = useState(runs.length === 0);
  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true)
    await dispatch(getRunWorkFlow({ id, page, per_page }));
    setLoading(false)
  };

  useEffect(() => {
    handleRefresh()
  }, []);

  const handlePageChange = (newPage) => {
    handleRefresh(newPage, perPage)
  };

  const handlePerPageChange = (itemsPerPage) => {
    handleRefresh(currentPage, itemsPerPage)
  };
  const columns = [
    {
      name: __('Run ID', 'zaplane'),
      cell: (row) => (
        <Text className="zaplane-sub-title">{__(row.id, "zaplane")}</Text>
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
        <Text className="zaplane-sub-title">
          {getDuration(row.started_at, row.finished_at)}
        </Text>
      ),
      // columnWidth: "150px",
    },
    {
      name: __('Node Run', 'zaplane'),
      cell: (row) => (
        <Text className="zaplane-sub-title">
          {row.node_runs_count}
        </Text>
      ),
      // columnWidth: "150px",
    },

    {
      name: __('Action', 'zaplane'),
      cell: (row) => (
        <HStack justify="flex-end" spacing="1" justifyContent={"center"}>
          {<ZAPTooltip content={__("Details", 'zaplane')}>}
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
          {</ZAPTooltip>}
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
        showPagination={runs.length >= 10}
        noDataText={__("No history found", "zaplane")}
        totalItems={runs.length}
        dataFetchingStatus={loading}
        suffix="history-table"
        currentPageNumber={currentPage}
        perPage={perPage}
        onChangePage={handlePageChange}
        onChangeItemsPerPage={handlePerPageChange}
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
