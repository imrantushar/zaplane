import React, { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate } from "react-router-dom";
import { __ } from "@wordpress/i18n";
import { getRunsList, clearRuns, deleteRun } from "@ZAPRedux/Slices/logsSlice/logsSlice";
import Button from "@ZAPComponents/Button";
import ZAPActionBar from "@ZAPComponents/ZAPActionBar";
import { FiTrash2, FiEdit2, FiChevronDown, FiFilter } from "react-icons/fi";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import LogDetails from "@ZAPComponents/LogDetails";
import ZAPDrawer from "@ZAPComponents/Drawer";
import ListTable from "@ZAPComponents/ListTable";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import { formatDateTime, formatLabel, getDuration, plugin_root_url, route_path } from "@ZAPUtils/helper";
import { isAbsoluteIcon, resolveIconFilename } from "@ZAPComponents/ZAPIconGroup/ZAPIconGroup";
import { statusStyle } from "../workflows/helper";
import { HistoryIcon } from "@ZAPUtils/icons";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import PageLayout from "@ZAPComponents/PageLayout";

const FALLBACK_APP_ICON = `${plugin_root_url}assets/images/button.svg`;

// Small app logo resolved from the node's integration slug (e.g. "wordpress").
const AppLogo = ({ app }) => {
  const icon = resolveIconFilename(app);
  const isAbsolute = isAbsoluteIcon(icon);
  const isImage = isAbsolute || icon?.endsWith(".svg");
  const src = isAbsolute ? icon : `${plugin_root_url}assets/images/icons/${icon}`;
  return <div className="flex h-8 w-8 items-center justify-center rounded-full border border-[var(--zaplane-border-color)] bg-white shrink-0">
    {isImage ? <img src={src} alt={app} className="h-4 w-4 object-contain" onError={e => {
      e.currentTarget.onerror = null;
      e.currentTarget.src = FALLBACK_APP_ICON;
    }} /> : <span className={`zaplane-icon zaplane-icon--${icon} m-0`} />}
  </div>;
};

const Logs = () => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const goEditWorkflow = workflowId => {
    if (!workflowId) return;
    navigate(`${route_path}admin.php?page=zaplane-workflows&action=edit&id=${workflowId}`);
  };
  const [activeRunId, setActiveRunId] = useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [selection, setSelection] = useState([]);
  const [statusFilter, setStatusFilter] = useState('all');
  const {
    data = [],
    currentPage,
    perPage,
    itemPerPage,
    totalItems
  } = useSelector(state => state.logs || {});
  const [loading, setLoading] = useState(data.length === 0);
  const handleRefresh = async (page = 1, per_page = 20, status = statusFilter) => {
    setLoading(true);
    await dispatch(getRunsList({
      page,
      per_page,
      status
    }));
    setLoading(false);
  };
  useEffect(() => {
    handleRefresh();
  }, []);
  const handleStatusFilter = status => {
    setStatusFilter(status);
    handleRefresh(1, perPage, status);
  };
  const statusFilterOptions = [{
    label: __("All statuses", "zaplane"),
    value: "all"
  }, {
    label: __("Success", "zaplane"),
    value: "completed"
  }, {
    label: __("Failed", "zaplane"),
    value: "failed"
  }, {
    label: __("Running", "zaplane"),
    value: "running"
  }];
  const currentStatusLabel = statusFilterOptions.find(o => o.value === statusFilter)?.label || __("All statuses", "zaplane");
  const handlePageChange = newPage => {
    handleRefresh(newPage, perPage);
  };
  const handlePerPageChange = itemsPerPage => {
    handleRefresh(currentPage, itemsPerPage);
  };
  const handleClearLogs = async () => {
    // eslint-disable-next-line no-alert
    if (!window.confirm(__("Are you sure you want to clear all logs? This cannot be undone.", "zaplane"))) {
      return;
    }
    const result = await dispatch(clearRuns());
    if (!result?.error) {
      handleRefresh(1, perPage);
    }
  };
  const handleDeleteRow = async row => {
    if (!row?.id) return;
    // eslint-disable-next-line no-alert
    if (!window.confirm(__("Are you sure you want to delete this log?", "zaplane"))) {
      return;
    }
    const result = await dispatch(deleteRun(row.id));
    if (!result?.error) {
      handleRefresh(currentPage, perPage);
    }
  };
  const handleDeleteSelected = async () => {
    if (!selection.length) return;
    await Promise.all(
      selection.map(row => row?.id).filter(Boolean).map(id => dispatch(deleteRun(id)))
    );
    setSelection([]);
    handleRefresh(currentPage, perPage);
  };
  const columns = [{
    name: <span>
      {__("App Name", "zaplane")}
    </span>,
    cell: row => {
      return <div className="flex items-center gap-3">
        <AppLogo app={row?.node?.app} />
        <ZAPLabel label={row?.node?.app} type={"simple"} />
      </div>;
    },
    // columnWidth: "180px",
    textAlign: "start"
  }, {
    name: <span>
      {__("Action", "zaplane")}
    </span>,
    cell: row => <ZAPLabel label={__(formatLabel(row?.node?.event), 'zaplane')} type={"simple"} />,
    textAlign: "start"
  }, {
    name: <span>
      {__("Workflow", "zaplane")}
    </span>,
    cell: row => row?.workflow_id ? <button onClick={() => goEditWorkflow(row.workflow_id)} title={__("Edit workflow", "zaplane")} className="text-[13px] font-medium text-[var(--zaplane-font-color)] hover:text-[var(--zaplane-primary)] hover:underline text-left truncate max-w-[200px]">
      {row.workflow_title || __("Untitled workflow", "zaplane")}
    </button> : <span className="text-[var(--zaplane-text-muted)]">—</span>,
    textAlign: "start"
  }, {
    name: <span className="zaplane-label ml-[-33px]">
      {__("Created At", "zaplane")}
    </span>,
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.started_at);
      return <div className="flex flex-col">
        <ZAPLabel label={date} type={"simple"} />
        <span className="zaplane-sub-title ml-[-38px] text-var(--zaplane-text-muted)">
          {__(time, 'zaplane')}
        </span>
      </div>;
    },
    // columnWidth: "180px",
    textAlign: "center"
  }, {
    name: <span>
      {__("DURATION", "zaplane")}
    </span>,
    cell: row => <ZAPLabel label={getDuration(row.started_at, row.finished_at)} type={"simple"} />
    // columnWidth: "150px",
  }, {
    name: <span>
      {__("Node Count", "zaplane")}
    </span>,
    cell: row => <ZAPLabel label={row.node_count} type={"simple"} />
    // columnWidth: "150px",
  }, {
    name: <span>
      {__("Status", "zaplane")}
    </span>,
    cell: row => {
      const style = statusStyle(row.status);
      const label = row.status === 'completed' ? __("Success", "zaplane") : row.status === 'failed' ? __("Failed", "zaplane") : formatLabel(row.status);
      return <div className="flex justify-center">
        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium capitalize" style={style}>
          <span className="w-[6px] h-[6px] rounded-full" style={{ background: style.color }} />
          {label}
        </span>
      </div>;
    }
    // columnWidth: "120px",
  }, {
    name: <span>
      {__("Actions", "zaplane")}
    </span>,
    cell: row => <div className="flex flex-row items-center justify-center">
      <ZAPMenu isIcon items={[{
        label: __("Details", "zaplane"),
        icon: HistoryIcon,
        onClick: () => {
          setActiveRunId(row.id);
          setDrawerOpen(true);
          dispatch(nodeLogsRunDetails(row.id));
        }
      }, ...(row?.workflow_id ? [{
        label: __("Edit workflow", "zaplane"),
        icon: FiEdit2,
        onClick: () => goEditWorkflow(row.workflow_id)
      }] : []), {
        type: "divider"
      }, {
        label: __("Delete", "zaplane"),
        icon: FiTrash2,
        onClick: () => handleDeleteRow(row)
      }]} />
    </div>,
    // columnWidth: "100px",
    textAlign: "center"
  }];

  // if (isLoading) {
  //     return <ZAPLoading />;
  // }

  return (
    <PageLayout 
      title="Logs" 
      heading="Logs" 
      actions={
        <Button 
          label={__("Clear logs", "zaplane")} 
          size="sm" 
          suffix=" p-[8px]" 
          preset="border" 
          onClick={handleClearLogs} 
          isDisabled={loading || data.length === 0} />
      }>
        <ListTable
          columns={columns}
          isRowSelectable={true}
          data={data || []}
          showSubHeader={true}
          subHeaderComponent={
            <ZAPMenu
              menuPlacement="bottom"
              trigger={
                <button className="flex items-center justify-between gap-3 min-w-[180px] px-3 h-[38px] rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] text-[13px] font-medium text-[var(--zaplane-font-color)]">
                  <span className="flex items-center gap-2">
                    <FiFilter size={14} className="text-[var(--zaplane-text-muted)]" />
                    {currentStatusLabel}
                  </span>
                  <FiChevronDown size={16} className="text-[var(--zaplane-text-muted)]" />
                </button>
              }
              items={statusFilterOptions.map(opt => ({
                label: opt.label,
                onClick: () => handleStatusFilter(opt.value)
              }))}
            />
          }
          showColumnFilter={false}
          showPagination={totalItems > itemPerPage}
          noDataText={__("No logs found", "zaplane")} 
          totalItems={totalItems} 
          dataFetchingStatus={loading} 
          suffix="logs-table" 
          currentPageNumber={currentPage} 
          perPage={perPage} 
          rowsPerPage={itemPerPage} 
          onChangePage={handlePageChange}
          onChangeItemsPerPage={handlePerPageChange}
          getSelectRowValue={rows => setSelection(rows || [])}
        />
        <ZAPActionBar selection={selection} onDelete={handleDeleteSelected} onClose={() => setSelection([])} />
        <ZAPDrawer
          open={drawerOpen} 
          arrowClose 
          onClose={() => {
            setDrawerOpen(false);
            setActiveRunId(null);
          }} 
          closeOnOverlayClick 
          title={__("Run Details", "zaplane")} 
          placement="end" 
          size="md"
        >
          {activeRunId && (
            <LogDetails 
              runId={activeRunId} 
              onBack={() => {
                setDrawerOpen(false);
                setActiveRunId(null);
              }} 
            />
        )}
        </ZAPDrawer>
    </PageLayout>
  ) 
  ;
};
export default Logs;