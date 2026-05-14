import { __ } from "@wordpress/i18n";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ListTable from "@ZAPComponents/ListTable";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import { getAllVersion, getPreviewOldVersion, versionActive } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowVersion";
import { formatDateTime } from "@ZAPUtils/helper";
import { CheckCircle, Eye } from "lucide-react";
import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
const VersionHistoryTable = ({
  id
}) => {
  const dispatch = useDispatch();
  const {
    versions
  } = useSelector(state => state.workflows);
  const {
    isLoading,
    currentPage,
    perPage,
    itemPerPage,
    totalItems
  } = useSelector(state => state.workflows);
  const [loading, setLoading] = useState(versions.length === 0);
  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true);
    await dispatch(getAllVersion({
      id,
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
    name: __('ID', 'zaplane'),
    cell: row => <span>{__(row.id, "zaplane")}</span>,
    // columnWidth: "180px",
    textAlign: "center"
  }, {
    name: __('Created At', 'zaplane'),
    cell: row => {
      const {
        date,
        time
      } = formatDateTime(row.created_at);
      return <div className="text-center flex flex-col items-center">
            <ZAPLabel label={date} type={"simple"} />
            <span className="zaplane-sub-title ml-[-40px] text-var(--zaplane-text-muted)">
              {__(time, 'zaplane')}
            </span>
          </div>;
    },
    // columnWidth: "180px",
    textAlign: "center"
  }, {
    name: __('Status', 'zaplane'),
    cell: row => <span className={` ${
      row.is_active
        ? "text-green-500 bg-green-100"
        : "text-gray-500 bg-gray-100"
    } px-2 py-0.5 rounded-md text-[xs] $`}>
          {row.is_active ? "Active" : "Inactive"}
        </span>,
    columnWidth: "120px"
  }, {
    name: __('Action', 'zaplane'),
    cell: row => <div justify="flex-end" className="flex flex-row items-center gap-1">
          {!row.is_active && <ZAPTooltip content={__("Active version", 'zaplane')}>
              <button className="flex px-[8px] py-[4px] justify-center items-center rounded-[2.917px] border" onClick={() => dispatch(versionActive({
          id,
          versionID: row.id
        }))}>
                <CheckCircle size={14} />
              </button>
            </ZAPTooltip>}
          <ZAPTooltip content={__("Prevew Version", 'zaplane')}>
            <button className="flex px-[8px] py-[4px] justify-center items-center rounded-[2.917px] border" onClick={() => dispatch(getPreviewOldVersion({
          id,
          versionID: row.id
        }))}>
              <Eye size={14} />
            </button>
          </ZAPTooltip>

        </div>,
    // columnWidth: "100px",
    textAlign: "center"
  }];
  return <ListTable columns={columns} isRowSelectable={false} data={versions} showSubHeader={false} showColumnFilter={false} showPagination={totalItems >= 10} noDataText={__("No history found", "zaplane")} totalItems={totalItems} dataFetchingStatus={loading} suffix="version-table" currentPageNumber={currentPage} perPage={perPage} rowsPerPage={itemPerPage} onChangePage={handlePageChange} onChangeItemsPerPage={handlePerPageChange} />;
};
export default VersionHistoryTable;