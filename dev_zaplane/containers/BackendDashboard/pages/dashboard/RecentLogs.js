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
              {__(formatLabel(row?.node?.event), 'zaplane')}
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
              {__(time, 'zaplane')}
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
              {__(time, 'zaplane')}
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
          <div bg={isSuccess(row.status) ? "green.500" : "red.500"} className="w-[8px] h-[8px] rounded-full" />
          <ZAPLabel label={isSuccess(row.status) ? __("Success", "zaplane") : __("Failed", "zaplane")} type={"simple"} />
        </div>
    // columnWidth: "160px",
  }];
  return <div width="100%" boxShadow='md' className="bg-#fff rounded-[4px]">
      <span className="zaplane-heading p-[24px 24px 0 24px]">
        {__("Recent Logs", "zaplane")}
      </span>

      <ListTable columns={columns} data={Array.isArray(data.runs) ? data.runs.slice(0, 5) : []} isRowSelectable={false} showSubHeader={false} showColumnFilter={false} noDataText={__("No logs found", "zaplane")} totalItems={data?.runs?.length || 0} dataFetchingStatus={false} suffix="recent-logs-table" />
    </div>;
};
export default RecentLogs;