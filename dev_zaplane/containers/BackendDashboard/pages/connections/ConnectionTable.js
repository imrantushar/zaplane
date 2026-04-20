import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { useEffect, useState } from "react";
import ListTable from "@ZAPComponents/ListTable";
import OptionMenu from "@ZAPComponents/OptionMenu";
import StatusOptions from "@ZAPComponents/StatusOptions";
import { FiTrash2, FiRefreshCw, FiEye } from "react-icons/fi";
import { deleteConnection, testConnection, fetchSingleConnection, updateConnection, fetchConnections } from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";
import ConnectionDetails from "./ConnectionDetails/ConnectionDetails";
import { formatDateTime } from "@ZAPUtils/helper";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { TableArrow } from "@ZAPUtils/icons";
import ZAPActionBar from "@ZAPComponents/ZAPActionBar";
import ZAPIcon from "@ZAPComponents/ZAPIcon";
import ZAPIconGroup from "@ZAPComponents/ZAPIconGroup/ZAPIconGroup";
const ConnectionTable = () => {
  const dispatch = useDispatch();
  const [detailsOpen, setDetailsOpen] = useState(false);
  const {
    allConnection = [],
    isLoading,
    connection,
    currentPage,
    perPage,
    totalItems
  } = useSelector(state => state.connections);
  const [selection, setSelection] = useState([]);
  const [loading, setLoading] = useState(allConnection.length === 0);
  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true);
    await dispatch(fetchConnections({
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
  const handleStatusChange = (row, newStatus) => {
    if (!row?.id || !newStatus) return;
    dispatch(updateConnection({
      id: row.id,
      payload: {
        status: newStatus
      }
    }));
  };
  const openDetails = row => {
    dispatch(fetchSingleConnection(row.id));
    setDetailsOpen(true);
  };
  const handleDeleteSelected = async () => {
    if (!selection.length) return;
    try {
      await Promise.all(selection.map(row => row?.id).filter(Boolean).map(id => dispatch(deleteConnection(id))));
      setSelection([]);
      dispatch(fetchConnections({
        page: currentPage,
        per_page: perPage
      }));
    } catch (e) {
      console.error("Failed to delete selected team members", e);
    }
  };
  const columns = [{
    name: <span>
                    {__("App / Name", "zaplane")}
                </span>,
    cell: row => {
      return <div gap='12px' className="flex items-center">
                    <ZAPIconGroup icons={[row?.icon]} />
                    <span textOverflow="ellipsis" className="zaplane-label font-[400]">
                        {row.name}
                    </span>
                </div>;
    },
    // columnWidth: "150px",
    textAlign: "start"
  }, {
    name: <span>
                    {__("Auth Type", "zaplane")}
                </span>,
    cell: row => <ZAPLabel label={row.auth_type} type={"simple"} />,
    // columnWidth: "120px",
    textAlign: "center"
  }, {
    name: <span className="zaplane-label ml-[-32px]">
                    {__("Created At", "zaplane")}
                </span>,
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.created_at);
      return <div className="text-center">
                        <ZAPLabel label={date} type={"simple"} />
                        <span className="zaplane-sub-title ml-[-38px] text-var(--zaplane-text-muted)">
                            {__(time, 'zaplane')}
                        </span>
                    </div>;
    },
    // columnWidth: "160px",
    textAlign: "center"
  }, {
    name: <span className="zaplane-label ml-[-32px]">
                    {__("Updated At", "zaplane")}
                </span>,
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.updated_at);
      return <div className="text-center">
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
                    {__("Status", "zaplane")}
                </span>,
    cell: row => <StatusOptions value={row?.status} options={{
      items: [{
        label: "Active",
        value: "active"
      }, {
        label: "Inactive",
        value: "inactive"
      }]
    }} onChangeHandler={newStatus => handleStatusChange(row, newStatus)} />,
    // columnWidth: "170px",
    textAlign: "center"
  }, {
    name: <span>
                    {__("Action", "zaplane")}
                </span>,
    cell: row => <OptionMenu options={[{
      label: __("Test", "zaplane"),
      icon: <FiRefreshCw />,
      type: "button",
      onClick: () => dispatch(testConnection(row.id))
    }, {
      label: __("Details", "zaplane"),
      icon: <FiEye />,
      type: "button",
      onClick: () => openDetails(row)
    }, {
      label: __("Delete", "zaplane"),
      icon: <FiTrash2 />,
      type: "button",
      suffix: "trash",
      hasBorder: false,
      onClick: () => {
        if (window.confirm(__("Are you sure you want to permanently delete ?", "zaplane"))) {
          dispatch(deleteConnection(row.id));
        }
      }
    }]} />,
    // columnWidth: "100px",
    textAlign: "center"
  }];
  return <>
            <ListTable columns={columns} data={allConnection} isRowSelectable={true} showSubHeader={false} showColumnFilter={false} showPagination={totalItems >= 10} noDataText={__("No connections found", "zaplane")} totalItems={totalItems} dataFetchingStatus={loading} suffix="connection-table" currentPageNumber={currentPage} perPage={perPage} onChangePage={handlePageChange} onChangeItemsPerPage={handlePerPageChange} getSelectRowValue={rows => {
      setSelection(rows || []);
    }} />
            <ZAPActionBar selection={selection} onDelete={handleDeleteSelected} onClose={() => setSelection([])} />

            <ConnectionDetails isOpen={detailsOpen} onClose={() => setDetailsOpen(false)} connection={connection} />
        </>;
};
export default ConnectionTable;