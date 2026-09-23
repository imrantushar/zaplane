import { useDispatch, useSelector } from "react-redux";
import { useEffect, useState } from "react";
import LogDetails from "@ZAPComponents/LogDetails";
import { getDuration } from "@ZAPUtils/helper";
import { __, sprintf } from "@wordpress/i18n";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { statusStyle } from "../../../helper";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import { getRunWorkFlow, getSingleRun } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowRuns";
import ListTable from "@ZAPComponents/ListTable";
import { HistoryIcon, ReExcutionIcon } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
const RunsTable = ({
  id,
  activeDrawer,
  setRefreshing
}) => {
  const dispatch = useDispatch();
  const [activeRunId, setActiveRunId] = useState(null);
  const {
    runs = [],
    currentPage,
    totalItems,
    itemPerPage
  } = useSelector(state => state.workflows);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [loading, setLoading] = useState(runs.length === 0);
  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true);
    setRefreshing(true);
    await dispatch(getRunWorkFlow({
      id,
      page,
      per_page
    }));
    setRefreshing(false);
    setLoading(false);
  };
  useEffect(() => {
    if (activeDrawer !== "logs") return;
    handleRefresh(currentPage, itemPerPage);
    const interval = setInterval(() => {
      handleRefresh(currentPage, itemPerPage);
    }, 8000);
    return () => clearInterval(interval);
  }, [currentPage, itemPerPage, activeDrawer]);
  const handlePageChange = newPage => {
    handleRefresh(newPage, itemPerPage);
  };
  const handlePerPageChange = itemsPerPage => {
    handleRefresh(1, itemsPerPage);
  };
  const columns = [{
    name: __('Run ID', 'zaplane'),
    cell: row => <span>{__(row.id, "zaplane")}</span>,
    // columnWidth: "180px",
    textAlign: "center"
  }, {
    name: __('Trigger', 'zaplane'),
    cell: row => row.trigger ? <span className="block truncate whitespace-nowrap" title={row.trigger.label || ""}>
          {sprintf(__("Trigger %d", "zaplane"), row.trigger.number)}
          {row.trigger.label ? <span className="text-[var(--zaplane-font-secondary-color)]">{` · ${row.trigger.label}`}</span> : null}
        </span> : <span className="text-[var(--zaplane-font-secondary-color)]">—</span>,
    columnWidth: "280px",
    textAlign: "start"
  }, {
    name: __('Status', 'zaplane'),
    cell: row => <span textTransform="capitalize" style={statusStyle(row.status)} className="px-2 py-0.5 rounded-md text-[xs]">
          {__(row.status, "zaplane")}
        </span>,
    columnWidth: "100px"
  }, {
    name: __('DURATION', 'zaplane'),
    cell: row => <span>
          {getDuration(row.started_at, row.finished_at)}
        </span>
    // columnWidth: "150px",
  }, {
    name: __('Node Run', 'zaplane'),
    cell: row => <span>
          {row.node_runs_count}
        </span>
    // columnWidth: "150px",
  }, {
    name: __('Action', 'zaplane'),
    cell: row => <div className="flex flex-row items-center gap-1 justify-center">
          <ZAPTooltip content={__("Details", 'zaplane')}>
            <div onClick={() => {
          setActiveRunId(row.id);
          setDrawerOpen(true);
          dispatch(nodeLogsRunDetails(row.id));
        }} className="flex px-[8px] py-[4px] justify-center items-center rounded-[2.917px] border border-[var(--zaplane-border-color)] text-[var(--zaplane-font-color)]">
              <HistoryIcon height="20px" width="20px" />
            </div>
          </ZAPTooltip>
          <ZAPTooltip content={__("Re-Try", 'zaplane')}>
            <div onClick={() => dispatch(getSingleRun(row.id))} className="flex px-[8px] py-[4px] justify-center items-center rounded-[2.917px] border border-[var(--zaplane-border-color)] text-[var(--zaplane-font-color)]">
              <ReExcutionIcon height="20px" width="20px" />
            </div>
          </ZAPTooltip>


        </div>,
    // columnWidth: "100px",
    textAlign: "center"
  }];
  return <>
      <ListTable 
      columns={columns} 
      isRowSelectable={false}
       data={runs} 
       showSubHeader={false} 
       showColumnFilter={false} 
       showPagination={totalItems > 0}
       noDataText={__("No history found", "zaplane")} 
       totalItems={totalItems} 
       dataFetchingStatus={loading} 
       suffix="history-table" 
       currentPageNumber={currentPage} 
       rowsPerPage={itemPerPage} 
       onChangePage={handlePageChange} 
       onChangeItemsPerPage={handlePerPageChange} />
      <ZAPDrawer open={drawerOpen} arrowClose={true} onClose={() => {
      setDrawerOpen(false);
      setActiveRunId(null);
    }} title={__("Log Details", "zaplane")} placement="end" size="md">
        {activeRunId ? <LogDetails runId={activeRunId} onBack={() => {
        setDrawerOpen(false);
        setActiveRunId(null);
      }} /> : null}
      </ZAPDrawer>
    </>;
};
export default RunsTable;