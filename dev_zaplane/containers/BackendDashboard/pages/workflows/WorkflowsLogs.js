import { useDispatch, useSelector } from "react-redux";
import { useEffect, useState } from "react";
import LogDetails from "@ZAPComponents/LogDetails";
import { getDuration } from "@ZAPUtils/helper";
import { __ } from "@wordpress/i18n";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import { getRunWorkFlow, getSingleRun } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowRuns";
import ListTable from "@ZAPComponents/ListTable";
import { HistoryIcon, ReExcutionIcon } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import { statusStyle } from "./helper";
const WorkflowsLogs = ({
  id
}) => {
  const dispatch = useDispatch();
  const [activeRunId, setActiveRunId] = useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const {
    runs = [],
    currentPage,
    perPage,
    totalItems,
    itemPerPage
  } = useSelector(state => state.workflows);
  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true);
    await dispatch(getRunWorkFlow({
      id,
      page,
      per_page
    }));
    setLoading(false);
  };
  useEffect(() => {
    handleRefresh(currentPage, itemPerPage);
  }, [currentPage, itemPerPage]);
  const handlePageChange = newPage => handleRefresh(newPage, perPage);
  const handlePerPageChange = itemsPerPage => handleRefresh(currentPage, itemsPerPage);
  const columns = [{
    name: __('Run ID', 'zaplane'),
    cell: row => <span>{row.id}</span>,
    textAlign: "center"
  }, {
    name: __('Status', 'zaplane'),
    cell: row => <span textTransform="capitalize" {...statusStyle(row.status)} className="px-2 py-0.5 rounded-md text-[xs]">
          {__(row.status, "zaplane")}
        </span>,
    columnWidth: "120px"
  }, {
    name: __('Duration', 'zaplane'),
    cell: row => <span>
          {getDuration(row.started_at, row.finished_at)}
        </span>
  }, {
    name: __('Node Run', 'zaplane'),
    cell: row => <span>{row.node_runs_count}</span>
  }, {
    name: __('Action', 'zaplane'),
    cell: row => <div className="flex flex-row items-center gap-1 justify-center">
          <ZAPTooltip content={__("Details", 'zaplane')}>
            <div onClick={() => {
          setActiveRunId(row.id);
          setDrawerOpen(true);
          dispatch(nodeLogsRunDetails(row.id));
        }} className="flex p-[5px 6px] justify-center items-center rounded-[2.917px] border cursor-pointer">
              <HistoryIcon height="20px" width="20px" />
            </div>
          </ZAPTooltip>
          <ZAPTooltip content={__("Re-Try", 'zaplane')}>
            <div onClick={() => dispatch(getSingleRun(row.id))} className="flex p-[5px 6px] justify-center items-center rounded-[2.917px] border cursor-pointer">
              <ReExcutionIcon height="20px" width="20px" />
            </div>
          </ZAPTooltip>
        </div>,
    textAlign: "center"
  }];
  return <>
      <ListTable columns={columns} isRowSelectable={false} data={runs} showSubHeader={false} showColumnFilter={false} showPagination={totalItems >= 10} noDataText={__("No history found", "zaplane")} totalItems={totalItems} dataFetchingStatus={loading} suffix="history-table" currentPageNumber={currentPage} perPage={perPage} rowsPerPage={itemPerPage} onChangePage={handlePageChange} onChangeItemsPerPage={handlePerPageChange} />
      <ZAPDrawer open={drawerOpen} arrowClose={true} onClose={() => {
      setDrawerOpen(false);
      setActiveRunId(null);
    }} title={__("Log Details", "zaplane")} placement="end" size="md">
        {activeRunId && <LogDetails runId={activeRunId} onBack={() => {
        setDrawerOpen(false);
        setActiveRunId(null);
      }} />}
      </ZAPDrawer>
    </>;
};
export default WorkflowsLogs;