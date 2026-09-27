import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useDispatch } from "react-redux";
import { __ } from "@wordpress/i18n";
import ListTable from "@ZAPComponents/ListTable";
import { formatDateTime, route_path } from "@ZAPUtils/helper";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import ZAPDrawer from "@ZAPComponents/Drawer";
import LogDetails from "@ZAPComponents/LogDetails";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import { FiExternalLink } from "react-icons/fi";
import { HistoryIcon } from "@ZAPUtils/icons";
const RecentLogs = ({
  data = []
}) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [activeRunId, setActiveRunId] = useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const openWorkflow = row => {
    if (!row?.workflow_id) {
      return;
    }

    navigate(`${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.workflow_id}`);
  };

  const openLog = row => {
    if (!row?.id) {
      return;
    }

    setActiveRunId(row.id);
    setDrawerOpen(true);
    dispatch(nodeLogsRunDetails(row.id));
  };

  const runStatuses = {
    completed: { label: __("Success", "zaplane"), color: "var(--zaplane-success)" },
    failed: { label: __("Failed", "zaplane"), color: "var(--zaplane-danger)" },
    running: { label: __("Running", "zaplane"), color: "var(--zaplane-primary)" },
    pending: { label: __("Pending", "zaplane"), color: "var(--zaplane-warning)" },
    waiting: { label: __("Waiting", "zaplane"), color: "var(--zaplane-warning)" },
    cancelled: { label: __("Cancelled", "zaplane"), color: "var(--zaplane-text-muted)" },
  };
  const getRunStatus = status => runStatuses[status] || {
    label: __("Unknown", "zaplane"), color: "var(--zaplane-text-muted)"
  };
  const columns = [{
    name: <span>
          {__("Workflow Name", "zaplane")}
        </span>,
    cell: row => <div className="min-w-[220px] max-w-[320px]">
          <ZAPLabel
            label={row?.workflow_title || __("Untitled workflow", "zaplane")}
            type={"simple"}
          />
        </div>,
    columnWidth: "260px",
    textAlign: "start"
  }, {
    name: <span>
          {__("Execute Time", "zaplane")}
        </span>,
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.started_at);
      return <div className="flex min-w-[170px] flex-col items-center justify-center gap-1 whitespace-nowrap text-center">
            <ZAPLabel label={date} type={"simple"} lineHeight="20px" />
            <span className="zaplane-sub-title text-[var(--zaplane-text-muted)]">
              {time}
            </span>
          </div>;
    },
    columnWidth: "180px",
    textAlign: "center"
  }, {
    name: <span>
          {__("Status", "zaplane")}
        </span>,
    cell: row => <div className="flex min-w-[100px] flex-row items-center justify-center gap-2 whitespace-nowrap">
          <div
            className="w-[8px] h-[8px] rounded-full"
            style={{ backgroundColor: getRunStatus(row.status).color }}
          />
          <ZAPLabel label={getRunStatus(row.status).label} type={"simple"} />
        </div>,
    columnWidth: "120px",
    textAlign: "center"
  }, {
    name: <span>
          {__("Actions", "zaplane")}
        </span>,
    cell: row => <div className="flex min-w-[80px] items-center justify-center">
          <ZAPMenu
            isIcon
            items={[
              ...(row?.workflow_id ? [{
                label: __("Open workflow", "zaplane"),
                icon: FiExternalLink,
                onClick: () => openWorkflow(row)
              }] : []),
              {
                label: __("View log", "zaplane"),
                icon: HistoryIcon,
                onClick: () => openLog(row)
              }
            ]}
          />
        </div>,
    columnWidth: "90px",
    textAlign: "center"
  }];
  const EmptyState = () => (
    <div className="flex flex-col items-center justify-center p-12 text-center">
      <div className="mb-6 opacity-40">
        {/* Simple SVG illustration matching the mockup pattern */}
        <svg width="240" height="120" viewBox="0 0 240 120" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="20" cy="20" r="4" fill="var(--zaplane-success)" />
          <circle cx="35" cy="20" r="4" fill="var(--zaplane-warning)" />
          <circle cx="50" cy="20" r="4" fill="var(--zaplane-danger)" />
          <rect x="20" y="40" width="40" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="80" y="40" width="40" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="140" y="40" width="40" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="200" y="40" width="20" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <circle cx="30" cy="65" r="8" fill="var(--zaplane-border-color)" />
          <rect x="50" y="65" width="30" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="100" y="65" width="30" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="150" y="65" width="30" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="200" y="65" width="20" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <circle cx="30" cy="95" r="8" fill="var(--zaplane-border-color)" />
          <rect x="50" y="95" width="30" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="100" y="95" width="30" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="150" y="95" width="30" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
          <rect x="200" y="95" width="20" height="4" rx="2" fill="var(--zaplane-secondary-color)" />
        </svg>
      </div>
      <h3 className="text-[var(--zaplane-font-color)] text-[20px] font-[600] mb-2">{__("No workflow runs yet", "zaplane")}</h3>
      <p className="text-[var(--zaplane-text-muted)] text-[14px]">{__("Run a workflow to see its recent activity here.", "zaplane")}</p>
    </div>
  );

  const tableData = Array.isArray(data) ? data.slice(0, 5) : [];

  return (
    <>
      <div className="bg-[var(--zaplane-background)] rounded-[8px] border border-[var(--zaplane-border-color)] w-full min-w-0 min-h-[400px] flex flex-col overflow-hidden">
        <div className="p-6">
          <span className="text-[var(--zaplane-font-secondary-color)] text-[16px] font-[500]">
            {__("Recent Workflow Runs", "zaplane")}
          </span>
        </div>

        <div className="min-w-0 flex-1 [&>.zaplane-table]:border-0 [&>.zaplane-table]:rounded-none [&>.zaplane-table]:p-0 [&_table]:min-w-[760px] [&_td]:align-middle">
          {tableData.length > 0 ? (
            <ListTable
              columns={columns}
              data={tableData}
              isRowSelectable={false}
              showSubHeader={false}
              showColumnFilter={false}
              totalItems={tableData.length}
              dataFetchingStatus={false}
              suffix="recent-logs-table"
            />
          ) : (
            <EmptyState />
          )}
        </div>
      </div>

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
    </>
  );
};

export default RecentLogs;
