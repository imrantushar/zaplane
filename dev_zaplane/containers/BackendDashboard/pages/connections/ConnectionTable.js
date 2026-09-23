import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { useEffect, useState } from "react";
import ListFilters, { readFilters, writeFilters } from "@ZAPComponents/ListFilters";
import { FiGrid } from "react-icons/fi";
import { integrations } from "@ZAPUtils/helper";
import ListTable from "@ZAPComponents/ListTable";
import OptionMenu from "@ZAPComponents/OptionMenu";
import StatusOptions from "@ZAPComponents/StatusOptions";
import { FiTrash2, FiRefreshCw, FiEye, FiEdit2 } from "react-icons/fi";
import { deleteConnection, testConnection, fetchSingleConnection, updateConnection, fetchConnections } from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";
import ConnectionDetails from "./ConnectionDetails/ConnectionDetails";
import { formatDateTime } from "@ZAPUtils/helper";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { TableArrow } from "@ZAPUtils/icons";
import ZAPActionBar from "@ZAPComponents/ZAPActionBar";
import ZAPIcon from "@ZAPComponents/ZAPIcon";
import ZAPIconGroup from "@ZAPComponents/ZAPIconGroup/ZAPIconGroup";
const ConnectionTable = ({ onEdit }) => {
  const dispatch = useDispatch();
  const [detailsOpen, setDetailsOpen] = useState(false);
  const {
    allConnection = [],
    isLoading,
    connection,
    currentPage,
    itemPerPage,
    totalItems,
    counts,
    apps = []
  } = useSelector(state => state.connections);
  const [selection, setSelection] = useState([]);
  const [loading, setLoading] = useState(allConnection.length === 0);
  // Status / app / search, done by the server so every page is searched.
  const [filters, setFilters] = useState(() => readFilters(["status", "app", "search"]));
  const handleRefresh = async (page = 1, per_page = itemPerPage || 10, withFilters = filters) => {
    setLoading(true);
    await dispatch(fetchConnections({
      page,
      per_page,
      status: withFilters.status,
      search: withFilters.search,
      app: withFilters.app || undefined
    }));
    setLoading(false);
  };
  const changeFilters = next => {
    setFilters(next);
    writeFilters(next);
    handleRefresh(1, itemPerPage || 10, next);
  };
  const appName = slug => (integrations?.apps?.[slug] || integrations?.tools?.[slug])?.name || slug;
  useEffect(() => {
    handleRefresh();
  }, []);
  const handlePageChange = newPage => {
    handleRefresh(newPage, itemPerPage);
  };
  const handlePerPageChange = itemsPerPage => {
    handleRefresh(1, itemsPerPage);
  };
  const handleStatusChange = async (row, newStatus) => {
    if (!row?.id || !newStatus) return;
    await dispatch(updateConnection({
      id: row.id,
      payload: {
        status: newStatus
      }
    }));
    // The tab counts (and a status filter) depend on it.
    handleRefresh(currentPage, itemPerPage);
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
      handleRefresh(currentPage, itemPerPage);
    } catch (e) {
      console.error("Failed to delete selected team members", e);
    }
  };
  const columns = [{
    name: <span>
                    {__("App / Name", "zaplane")}
                </span>,
    cell: row => {
      return <div className="flex items-center gap-3">
                    <ZAPIconGroup icons={[row?.icon || row?.app]} />
                    <span className="zaplane-label truncate font-[400]">
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
      return <div className="text-center flex flex-col items-center">
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
      return <div className="text-center flex flex-col items-center">
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
    }, ...(row.auth_type !== "oauth2" ? [{
      label: __("Edit credentials", "zaplane"),
      icon: <FiEdit2 />,
      type: "button",
      onClick: () => onEdit?.(row)
    }] : []), {
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
            <ListFilters
              tabs={[
                { key: "", label: __("All", "zaplane") },
                { key: "active", label: __("Active", "zaplane"), tone: "active" },
                { key: "inactive", label: __("Inactive", "zaplane"), tone: "muted" }
              ]}
              counts={counts}
              filters={filters}
              onChange={changeFilters}
              searchPlaceholder={__("Search connections…", "zaplane")}
              group={{
                key: "app",
                icon: FiGrid,
                placeholder: __("All apps", "zaplane"),
                options: apps.map(slug => ({ value: slug, label: appName(slug) })).sort((x, y) => x.label.localeCompare(y.label))
              }}
            />
            <ListTable columns={columns} data={allConnection} isRowSelectable={true} showColumnFilter={false} showPagination={totalItems > 0} noDataText={filters.status || filters.app || filters.search ? __("No connections match these filters", "zaplane") : __("No connections found", "zaplane")} totalItems={totalItems} dataFetchingStatus={loading} suffix="connection-table" currentPageNumber={currentPage} rowsPerPage={itemPerPage} onChangePage={handlePageChange} onChangeItemsPerPage={handlePerPageChange} getSelectRowValue={rows => {
      setSelection(rows || []);
    }} />
            <ZAPActionBar selection={selection} onDelete={handleDeleteSelected} onClose={() => setSelection([])} />

            <ConnectionDetails isOpen={detailsOpen} onClose={() => setDetailsOpen(false)} connection={connection} />
        </>;
};
export default ConnectionTable;