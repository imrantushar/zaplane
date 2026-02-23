import { useMemo } from "react";
import { Box, HStack, Text } from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import ListTable from "@ZAPComponents/ListTable";
import { formatDateTime, formatLabel, getDuration } from "@ZAPUtils/helper";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";

const RecentLogs = ({ data = [] }) => {
  const isSuccess = (status) => status === "completed";

  const columns = [
    {
      name: (
        <Text className="zaplane-label">
          {__("App Name", "zaplane")}
        </Text>
      ),
      cell: (row) => {
        return (
          <Box >
            <ZAPLabel label={row?.node?.app} type={"simple"}/>
            <Text className="zaplane-sub-title" color="var(--zaplane-text-muted)">
              {__(formatLabel(row?.node?.event), 'zaplane')}
            </Text>
          </Box>
        );
      },
      // columnWidth: "180px",
      textAlign: "start",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("CREATED AT", "zaplane")}
        </Text>
      ),
      cell: (row) => {
        const { date, time } = formatDateTime(row.started_at);

        return (
          <Box >
            <ZAPLabel label={date} type={"simple"}/>
            <Text className="zaplane-sub-title" ml='-63px' color="var(--zaplane-text-muted)">
              {__(time, 'zaplane')}
            </Text>
          </Box>
        );
      },
      // columnWidth: "180px",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("Updated At", "zaplane")}
        </Text>
      ),
      cell: (row) => {
        const { date, time } = formatDateTime(row.finished_at);

        return (
          <Box textAlign="center">
            <ZAPLabel label={date} type={"simple"}/>
            <Text className="zaplane-sub-title" ml='-63px' color="var(--zaplane-text-muted)">
              {__(time, 'zaplane')}
            </Text>
          </Box>
        );
      },
      columnWidth: "160px",
      textAlign: "center",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("DURATION / SIZE", "zaplane")}
        </Text>
      ),
      cell: (row) => (
        <ZAPLabel label={getDuration(row.started_at, row.finished_at)} type={"simple"}/>
      ),
      // columnWidth: "180px",
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
          <ZAPLabel label={isSuccess(row.status)
                ? __("Success", "zaplane")
                : __("Failed", "zaplane")} type={"simple"}/>
        </HStack>
      ),
      // columnWidth: "160px",
    },
  ]

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
