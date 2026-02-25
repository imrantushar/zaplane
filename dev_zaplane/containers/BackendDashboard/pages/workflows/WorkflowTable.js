import { useMemo, useCallback, useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { Text, Box, Icon, HStack, Flex } from "@chakra-ui/react";
import Select from "react-select";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";

import ListTable from "@ZAPComponents/ListTable";
import OptionMenu from "@ZAPComponents/OptionMenu";

import { FiEdit, FiTrash2 } from "react-icons/fi";
import { formatDateTime, route_path } from "@ZAPUtils/helper";
import { statusOptions } from "./helper";

import {
  deleteWorkFlow,
  getWorkFlow,
  updateWorkFlowStatus,
} from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import StatusOptions from "@ZAPComponents/StatusOptions";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import { FaRegEdit } from "react-icons/fa";
import { RiDeleteBin6Line } from "react-icons/ri";
import { LiaEditSolid } from "react-icons/lia";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPDrawer from "@ZAPComponents/Drawer";
import LogDetails from "@ZAPComponents/LogDetails";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import { HistoryIcon, TableArrow } from "@ZAPUtils/icons";

const WorkflowTable = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [activeRunId, setActiveRunId] = useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);


  const {
    allWorkFlows,
    totalItems,
    currentPage,
    perPage,
  } = useSelector((state) => state.workflows);
  const [loading, setLoading] = useState(allWorkFlows.length === 0);
  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true)
    await dispatch(getWorkFlow({ page, per_page }));
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
      name: (
        <Flex gap="2px" alignItems='center'>
          <Text className="zaplane-label">
            {__("Title", "zaplane")}
          </Text>
          <Icon as={TableArrow} />
        </Flex>
      ),
      cell: (row) => (
        <Text
          className="zaplane-label"
          fontWeight="400"
          fontSize="14px"
          textOverflow="ellipsis"
          cursor="pointer"
          onClick={() =>
            navigate(
              `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`
            )
          }
        >
          {row.title}
        </Text>
      ),
      // columnWidth: "100px",
      textAlign: 'start'
    },
    {
      name: (
        <Flex gap="2px" alignItems='center' justifyContent="center" ml='-32px'>
          <Text className="zaplane-label">
            {__("Created At", "zaplane")}
          </Text>
          <Icon as={TableArrow} />
        </Flex>
      ),
      cell: (row) => {
        const { date, time } = formatDateTime(row.created_at);
        return (
          <Box>
            <ZAPLabel label={date} type={"simple"} />
            <Text className="zaplane-sub-title" ml='-63px' color="var(--zaplane-text-muted)">
              {__(time, 'zaplane')}
            </Text>
          </Box>
        );
      },
      // columnWidth: "160px",
      textAlign: "center",
    },

    {
      name: (
        <Flex gap="2px" alignItems='center' justifyContent="center">
          <Text className="zaplane-label">
            {__("Sucess Run", "zaplane")}
          </Text>
          <Icon as={TableArrow} />
        </Flex>
      ),
      cell: (row) => (
        <ZAPLabel label={row?.success_runs} type={"simple"} />
      ),
      // columnWidth: "160px",
      textAlign: "center",
    },
    {
      name: (
        <Flex gap="2px" alignItems='center' justifyContent="center" >
          <Text className="zaplane-label">
            {__("Failed Runs", "zaplane")}
          </Text>
          <Icon as={TableArrow} />
        </Flex>
      ),
      cell: (row) => (
        <ZAPLabel label={row?.failed_runs} type={"simple"} />
      ),
      // columnWidth: "160px",
      textAlign: "center",
    },
    {
      name: (
        <Flex gap="2px" alignItems='center' justifyContent="center" ml='-32px'>
          <Text className="zaplane-label">
            {__("Status", "zaplane")}
          </Text>
          <Icon as={TableArrow} />
        </Flex>
      ),
      cell: (row) => {
        const handleStatusChange = (row, newStatus) => {
          if (!row?.id || !newStatus) return;
          dispatch(updateWorkFlowStatus({ id: row.id, status: newStatus }));
        };

        return (
          <StatusOptions
            value={row?.status}
            options={{
              items: [...statusOptions],
            }}
            onChangeHandler={(newStatus) => handleStatusChange(row, newStatus)}
          />
        )
      },
      // columnWidth: "170px",
      textAlign: "center",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("Action", "zaplane")}
        </Text>
      ),
      cell: (row) => (

        <HStack justify="flex-end" spacing="1" justifyContent={"center"}>
          <ZAPTooltip content={__(" Log Details", 'zaplane')}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="2.917px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => {
                setActiveRunId(row.id);
                setDrawerOpen(true);
                dispatch(nodeLogsRunDetails(row.id));
              }}
            >
              <Icon
                as={HistoryIcon}
              />
            </Box>
          </ZAPTooltip>
          <ZAPTooltip content={__("Edit", 'zaplane')}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="2.917px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => {
                navigate(
                  `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`
                )
              }}
            >
              <Icon
                height="15px"
                width="15px"
                as={LiaEditSolid}
              />
            </Box>
          </ZAPTooltip>
          <ZAPTooltip content={__("Delete", 'zaplane')}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="2.917px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => {
                if (
                  window.confirm(
                    __("Are you sure you want to permanently delete?", "zaplane")
                  )
                ) {
                  dispatch(deleteWorkFlow(row.id));
                }
              }}
            >
              <Icon
                height="15px"
                width="15px"
                as={RiDeleteBin6Line}

              />
            </Box>
          </ZAPTooltip>


        </HStack>
      ),
      // columnWidth: "90px",
      textAlign: "center",
    },
  ]


  return (
    <>
      <ListTable
        columns={columns}
        data={allWorkFlows}
        isRowSelectable={true}
        showSubHeader={false}
        showColumnFilter={false}
       showPagination={ allWorkFlows.length >= 10 }
        noDataText={__("No workflows found", "zaplane")}
        dataFetchingStatus={loading}
        suffix="workflow-table"
        totalItems={totalItems}
        currentPageNumber={currentPage}
        perPage={perPage}
        onChangePage={handlePageChange}
        onChangeItemsPerPage={handlePerPageChange}
      />

      <ZAPDrawer
        open={drawerOpen}
        arrowClose
        onClose={() => {
          setDrawerOpen(false);
          setActiveRunId(null);
        }}
        closeOnOverlayClick
        title={__("Log Details", "zaplane")}
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
      </ZAPDrawer></>

  );
};

export default WorkflowTable;
