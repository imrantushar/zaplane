import { __ } from "@wordpress/i18n";
import ListTable from "@ZAPComponents/ListTable";
import { formatDateTime, formatLabel, getDuration } from "@ZAPUtils/helper";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
const RecentLogs = ({
  data = []
}) => {
  const isSuccess = status => status === "completed";
  const columns = [{
    name: <span>
          {__("App Name", "zaplane")}
        </span>,
    cell: row => {
      return <div>
            <ZAPLabel label={row?.node?.app} type={"simple"} />
            <span className="zaplane-sub-title text-var(--zaplane-text-muted)">
              {formatLabel(row?.node?.event)}
            </span>
          </div>;
    },
    // columnWidth: "180px",
    textAlign: "start"
  }, {
    name: <span className="zaplane-label ml-[-23px]">
          {__("CREATED AT", "zaplane")}
        </span>,
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.started_at);
      return <div>
            <ZAPLabel label={date} type={"simple"} />
            <span className="zaplane-sub-title ml-[-38px] text-var(--zaplane-text-muted)">
              {time}
            </span>
          </div>;
    }
    // columnWidth: "180px",
  }, {
    name: <span className="zaplane-label ml-[-23px]">
          {__("Updated At", "zaplane")}
        </span>,
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.finished_at);
      return <div className="text-center">
            <ZAPLabel label={date} type={"simple"} />
            <span className="zaplane-sub-title ml-[-38px] text-var(--zaplane-text-muted)">
              {time}
            </span>
          </div>;
    },
    columnWidth: "160px",
    textAlign: "center"
  }, {
    name: <span>
          {__("DURATION / SIZE", "zaplane")}
        </span>,
    cell: row => <ZAPLabel label={getDuration(row.started_at, row.finished_at)} type={"simple"} />
    // columnWidth: "180px",
  }, {
    name: <span>
          {__("STATUS", "zaplane")}
        </span>,
    cell: row => <div className="flex flex-row items-center gap-2 justify-center">
          <div
            className="w-[8px] h-[8px] rounded-full"
            style={{ backgroundColor: isSuccess(row.status) ? 'var(--zaplane-success)' : 'var(--zaplane-danger)' }}
          />
          <ZAPLabel label={isSuccess(row.status) ? __("Success", "zaplane") : __("Failed", "zaplane")} type={"simple"} />
        </div>
    // columnWidth: "160px",
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
    <div className="bg-[var(--zaplane-background)] rounded-[8px] border border-[var(--zaplane-border-color)] w-full min-h-[400px] flex flex-col">
      <div className="p-6">
        <span className="text-[var(--zaplane-font-secondary-color)] text-[16px] font-[500]">
          {__("Recent Workflow Runs", "zaplane")}
        </span>
      </div>

      <div className="flex-1">
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
  );
};

export default RecentLogs;
