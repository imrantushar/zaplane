import {
  Badge,
  Box,
  Button,
  HStack,
  Text,
} from "@chakra-ui/react";
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
  id,
}) => {
  const dispatch = useDispatch()
  const { versions } = useSelector((state) => state.workflows);
  const { isLoading, currentPage,
    perPage, itemPerPage,totalItems} = useSelector((state) => state.workflows);
  const [loading, setLoading] = useState(versions.length === 0);
  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true)
    await dispatch(getAllVersion({ id, page, per_page }));
    setLoading(false)
  };

  useEffect(() => {
    handleRefresh()
  }, []);

  const handlePageChange = (newPage) => {
    handleRefresh(newPage, perPage)
  };

  const handlePerPageChange = (itemsPerPage) => {
    handleRefresh(currentPage, itemsPerPage)
  };
  const columns = [
    {
      name: __('ID', 'zaplane'),
      cell: (row) => (
        <Text className="zaplane-sub-title">{__(row.id, "zaplane")}</Text>
      ),
      // columnWidth: "180px",
      textAlign: "center",
    },
    {
      name: __('Created At', 'zaplane'),
      cell: (row) => {
        const { date, time } = formatDateTime(row.created_at);

        return (
          <Box >
            <ZAPLabel label={date} type={"simple"} />
            <Text className="zaplane-sub-title" ml='-47px' color="var(--zaplane-text-muted)">
              {__(time, 'zaplane')}
            </Text>
          </Box>
        );
      },
      // columnWidth: "180px",
      textAlign: "center",
    },
    {
      name: __('Status', 'zaplane'),
      cell: (row) => (
        <Badge
          px="2"
          py="0.5"
          rounded="md"
          fontSize="xs"
          color={row.is_active ? "#16A34A" : "#4B5563"}
          bg={row.is_active ? "#DCFCE7" : "#F3F4F6"}

        >
          {row.is_active ? "Active" : "Inactive"}
        </Badge>
      ),
      columnWidth: "120px",
    },

    {
      name: __('Action', 'zaplane'),
      cell: (row) => (
        <HStack justify="flex-end" spacing={1}>
          {!row.is_active && (
            <ZAPTooltip content={__("Active version", 'zaplane')}>
              <Button
                size="xs"
                variant="outline"
                onClick={() => dispatch(versionActive({ id, versionID: row.id }))}
              >
                <CheckCircle size={14} />
              </Button>
            </ZAPTooltip>

          )}
          <ZAPTooltip content={__("Prevew Version", 'zaplane')}>
            <Button
              size="xs"
              variant="ghost"
              onClick={() => dispatch(getPreviewOldVersion({ id, versionID: row.id }))}
            >
              <Eye size={14} />
            </Button>
          </ZAPTooltip>

        </HStack>
      ),
      // columnWidth: "100px",
      textAlign: "center",
    },
  ]
  return (

    <ListTable
      columns={columns}
      isRowSelectable={false}
      data={versions}
      showSubHeader={false}
      showColumnFilter={false}
      showPagination={ versions.length >= 10 }
      noDataText={__("No history found", "zaplane")}
      totalItems={totalItems}
      dataFetchingStatus={loading}
      suffix="version-table"
      currentPageNumber={currentPage}
      perPage={perPage}
      rowsPerPage={itemPerPage}
      onChangePage={handlePageChange}
      onChangeItemsPerPage={handlePerPageChange}
    />

  );
};

export default VersionHistoryTable;
