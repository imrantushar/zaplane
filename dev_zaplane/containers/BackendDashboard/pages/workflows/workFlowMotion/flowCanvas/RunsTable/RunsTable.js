import { useDispatch, useSelector } from "react-redux";
import { useEffect, useState } from "react";
import LogDetails from "@ZAPComponents/LogDetails";
import { formatLabel, getDuration } from "@ZAPUtils/helper";
import { __ } from "@wordpress/i18n";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { statusStyle } from "../../../helper";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import { getRunWorkFlow, getSingleRun } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowRuns";
import ListTable from "@ZAPComponents/ListTable";
import { HistoryIcon, ReExcutionIcon } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import './styles.scss';
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
    if (activeDrawer !== "logs") {return;}
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
    cell: row => <span>{row.id}</span>,
    // columnWidth: "180px",
    textAlign: "center"
  }, {
    name: __('Trigger', 'zaplane'),
    cell: row => row.trigger ? <span className="block" title={row.trigger.label || ""}>
          {`${__("Trigger", "zaplane")} ${row.trigger.number}`}
          {row.trigger.label ? <span className="text-[var(--zaplane-font-secondary-color)]">{` · ${row.trigger.label}`}</span> : null}
        </span> : <span className="text-[var(--zaplane-font-secondary-color)]">—</span>,
    textAlign: "start"
  }, {
    name: __('Status', 'zaplane'),
    cell: row => <span style={statusStyle(row.status)} className="inline-block px-2 py-0.5 rounded-md text-xs capitalize">
          {formatLabel(row.status)}
        </span>,
  }, {
    name: __('Duration', 'zaplane'),
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
            <button type="button" aria-label={__('View details', 'zaplane')} onClick={() => {
          setActiveRunId(row.id);
          setDrawerOpen(true);
          dispatch(nodeLogsRunDetails(row.id));
        }} className="flex px-[8px] py-[4px] justify-center items-center rounded-[2.917px] border border-[var(--zaplane-border-color)] text-[var(--zaplane-font-color)]">
              <HistoryIcon height="20px" width="20px" />
            </button>
          </ZAPTooltip>
          <ZAPTooltip content={__("Re-Try", 'zaplane')}>
            <button type="button" aria-label={__('Retry run', 'zaplane')} onClick={() => dispatch(getSingleRun(row.id))} className="flex px-[8px] py-[4px] justify-center items-center rounded-[2.917px] border border-[var(--zaplane-border-color)] text-[var(--zaplane-font-color)]">
              <ReExcutionIcon height="20px" width="20px" />
            </button>
          </ZAPTooltip>


        </div>,
    // columnWidth: "100px",
    textAlign: "center"
  }];
  return <>
      <div className="zaplane-run-history">
      <ListTable 
      columns={columns.map(column => ({ ...column, cell: row => <><span className="zaplane-run-history__label">{column.name}</span><div className="zaplane-run-history__value">{column.cell(row)}</div></> }))}
      isRowSelectable={false}
       data={runs} 
       showSubHeader={false} 
       showColumnFilter={false} 
       showPagination={totalItems > 0}
       noDataText={__("No history found", "zaplane")} 
       totalItems={totalItems} 
       dataFetchingStatus={loading} 
       suffix="workflow-run-history"
       currentPageNumber={currentPage} 
       rowsPerPage={itemPerPage} 
       onChangePage={handlePageChange} 
       onChangeItemsPerPage={handlePerPageChange} />
      </div>
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
