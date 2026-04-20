import React, { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { __ } from "@wordpress/i18n";
import { getRunsList } from "@ZAPRedux/Slices/logsSlice/logsSlice";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import LogDetails from "@ZAPComponents/LogDetails";
import ZAPDrawer from "@ZAPComponents/Drawer";
import ListTable from "@ZAPComponents/ListTable";
import { formatDateTime, formatLabel, getDuration } from "@ZAPUtils/helper";
import { HistoryIcon } from "@ZAPUtils/icons";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import PageLayout from "@ZAPComponents/PageLayout";
const Logs = () => {
  const dispatch = useDispatch();
  const [activeRunId, setActiveRunId] = useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const {
    data = [],
    currentPage,
    perPage,
    itemPerPage,
    totalItems
  } = useSelector(state => state.logs || {});
  const [loading, setLoading] = useState(data.length === 0);
  const handleRefresh = async (page = 1, per_page = 20) => {
    setLoading(true);
    await dispatch(getRunsList({
      page,
      per_page
    }));
    setLoading(false);
  };
  useEffect(() => {
    handleRefresh();
  }, []);
  const handlePageChange = newPage => {
    handleRefresh(newPage, perPage);
  };
  const handlePerPageChange = itemsPerPage => {
    handleRefresh(currentPage, itemsPerPage);
  };
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
    name: <span className="zaplane-label ml-[-33px]">
      {__("Created At", "zaplane")}
    </span>,
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.started_at);
      return <div className="ml-[-12px]">
        <ZAPLabel label={date} type={"simple"} />
        <span className="zaplane-sub-title ml-[-38px] text-var(--zaplane-text-muted)">
          {__(time, 'zaplane')}
        </span>
      </div>;
    },
    // columnWidth: "180px",
    textAlign: "center"
  }, {
    name: <span className="zaplane-label ml-[-33px]">
      {__("Updated At", "zaplane")}
    </span>,
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.finished_at);
      return <div className="ml-[-12px]">
        <ZAPLabel label={date} type={"simple"} />
        <span className="zaplane-sub-title ml-[-38px] text-var(--zaplane-text-muted)">
          {__(time, 'zaplane')}
        </span>
      </div>;
    },
    // columnWidth: "160px",
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
    cell: row => <div className="flex flex-row items-center gap-2 justify-center">
      <div bg={row.status === 'completed' ? "green.500" : "red.500"} className="w-[8px] h-[8px] rounded-full" />
      <ZAPLabel label={row.status === 'completed' ? __("Success", "zaplane") : __("Failed", "zaplane")} type={"simple"} />
    </div>
    // columnWidth: "120px",
  }, {
    name: <span>
      {__("Action", "zaplane")}
    </span>,
    cell: row => <div justify="flex-end" className="flex flex-row items-center gap-1 justify-center">
      <ZAPTooltip content={__("Details", 'zaplane')}>
        <div onClick={() => {
          setActiveRunId(row.id);
          setDrawerOpen(true);
          dispatch(nodeLogsRunDetails(row.id));
        }} className="flex p-[5px 6px] justify-center items-center rounded-[2.917px] border">
          <HistoryIcon style={{ height: "20px", width: "20px" }} />
        </div>
      </ZAPTooltip>
    </div>,
    // columnWidth: "100px",
    textAlign: "center"
  }];

  // if (isLoading) {
  //     return <ZAPLoading />;
  // }

  return <PageLayout title="Logs" heading="Logs">
    <ListTable columns={columns} isRowSelectable={false} data={data || []} showSubHeader={false} showColumnFilter={false} showPagination={totalItems >= 20} noDataText={__("No logs found", "zaplane")} totalItems={totalItems} dataFetchingStatus={loading} suffix="logs-table" currentPageNumber={currentPage} perPage={perPage} rowsPerPage={itemPerPage} onChangePage={handlePageChange} onChangeItemsPerPage={handlePerPageChange} />
    <ZAPDrawer open={drawerOpen} arrowClose onClose={() => {
      setDrawerOpen(false);
      setActiveRunId(null);
    }} closeOnOverlayClick title={__("Run Details", "zaplane")} placement="end" size="md">
      {activeRunId && <LogDetails runId={activeRunId} onBack={() => {
        setDrawerOpen(false);
        setActiveRunId(null);
      }} />}
    </ZAPDrawer>
  </PageLayout>;
};
export default Logs;