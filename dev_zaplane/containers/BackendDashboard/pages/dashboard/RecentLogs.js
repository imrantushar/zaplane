import { useMemo } from "react";
import { Box, HStack, Text } from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import ListTable from "@ZAPComponents/ListTable";
import { getDuration } from "@ZAPUtils/helper";

const RecentLogs = ({ data = [] }) => {
  const isSuccess = (status) => status === "completed";

  const columns = useMemo(
    () => [
      {
        name: (
          <Text className="zaplane-label">
            {__("CREATED AT", "zaplane")}
          </Text>
        ),
        cell: (row) => (
          <Text className="zaplane-label">
            {sprintf(__("%s", "zaplane"), row.started_at)}
          </Text>
        ),
        columnWidth: "180px",
      },
      {
        name: (
          <Text className="zaplane-label">
            {__("DURATION / SIZE", "zaplane")}
          </Text>
        ),
        cell: (row) => (
          <Text className="zaplane-label">
            {sprintf(
              __("%s", "zaplane"),
              getDuration(row.started_at, row.finished_at)
            )}
          </Text>
        ),
        columnWidth: "180px",
      },
      {
        name: (
          <Text className="zaplane-label">
            {__("STATUS", "zaplane")}
          </Text>
        ),
        cell: (row) => (
          <HStack spacing={2} justifyContent={"center"}>
            <Box
              w="8px"
              h="8px"
              borderRadius="full"
              bg={isSuccess(row.status) ? "green.500" : "red.500"}
            />
            <Text className="zaplane-label">
              {sprintf(
                __("%s", "zaplane"),
                isSuccess(row.status)
                  ? __("Success", "zaplane")
                  : __("Failed", "zaplane")
              )}
            </Text>
          </HStack>
        ),
        columnWidth: "160px",
      },
    ],
    []
  );

  return (
    <Box width="100%">
      <Text className="zaplane-heading" mb="16px">
        {__("Recent Logs", "zaplane")}
      </Text>

      <ListTable
        columns={columns}
        data={Array.isArray(data) ? data.slice(0, 5) : []}
        isRowSelectable={false}
        showSubHeader={false}
        showColumnFilter={false}
        showPagination={false}
        noDataText={__("No logs found", "zaplane")}
        totalItems={data?.length || 0}
        dataFetchingStatus={false}
        suffix="recent-logs-table"
      />
    </Box>
  );
};

export default RecentLogs;
