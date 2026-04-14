import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { Text, Box, Icon, HStack, Button, Menu, Portal, Flex } from "@chakra-ui/react";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import ListTable from "@ZAPComponents/ListTable";
import { route_path } from "@ZAPUtils/helper";
import { downloadJSON, statusOptions } from "./helper";

import {
  deleteWorkFlow,
  getWorkFlow,
  updateWorkFlowStatus,
} from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import StatusOptions from "@ZAPComponents/StatusOptions";
import { RiDeleteBin6Line } from "react-icons/ri";
import { LiaEditSolid } from "react-icons/lia";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPDrawer from "@ZAPComponents/Drawer";
import { HistoryIcon } from "@ZAPUtils/icons";
import ZAPActionBar from "@ZAPComponents/ZAPActionBar";
import { TbFileExport } from "react-icons/tb";
import { exportWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";
import WorkflowsLogs from "./WorkflowsLogs";
import OptionMenu from "@ZAPComponents/OptionMenu";
import SaveAsRecipeModal from "@ZAPComponents/SaveAsRecipeModal";
import { FaSave } from "react-icons/fa";
import ZAPIconGroup from "@ZAPComponents/ZAPIconGroup/ZAPIconGroup";
import FolderCell from "@ZAPComponents/FolderCell";
import { FiLayers } from "react-icons/fi";





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
  const [selection, setSelection] = useState([]);
  const [loading, setLoading] = useState(allWorkFlows.length === 0);
  const [saveAsRecipeRow, setSaveAsRecipeRow] = useState(null);
  const [recipeModalOpen, setRecipeModalOpen] = useState(false);
  const [selectedWorkflow, setSelectedWorkflow] = useState(null);


  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true);
    await dispatch(getWorkFlow({ page, per_page }));
    setLoading(false);
  };

  useEffect(() => {
    handleRefresh();
  }, []);


  const handlePageChange = (newPage) => handleRefresh(newPage, perPage);
  const handlePerPageChange = (itemsPerPage) => handleRefresh(currentPage, itemsPerPage);

  const handleExport = async (row) => {
    try {
      const res = await dispatch(
        exportWorkflows({
          workflow_ids: [row.id],
          versions: "all",
          include_runs: false,
        })
      );
      downloadJSON(res?.payload, row.title || "workflow");
    } catch (err) {
      console.error("Export failed:", err);
    }
  };

  const handleDeleteSelected = async () => {
    if (!selection.length) return;
    try {
      await Promise.all(
        selection
          .map((row) => row?.id)
          .filter(Boolean)
          .map((id) => dispatch(deleteWorkFlow(id)))
      );
      setSelection([]);
      dispatch(getWorkFlow({ page: currentPage, per_page: perPage }));
    } catch (e) {
      console.error("Failed to delete selected workflows", e);
    }
  };

  const columns = [
    {
      name: <Text className="zaplane-label">{__("Apps", "zaplane")}</Text>,
      cell: (row) => {
        return <ZAPIconGroup icons={row.integration_icons} maxVisible={2}/>;
        ;
      },
      textAlign: "start",
      columnWidth: "120px",

    },
    {
      name: <Text className="zaplane-label">{__("Title", "zaplane")}</Text>,
      cell: (row) => (
        <Text
          className="zaplane-label"
          fontWeight="400"
          fontSize="14px"
          textOverflow="ellipsis"
          cursor="pointer"
          onClick={() =>
            navigate(`${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`)
          }
        >
          {row.title}
        </Text>
      ),
      textAlign: "start",
    },
    {
      name: <Text className="zaplane-label">{__("Folder", "zaplane")}</Text>,
      cell: (row) => {
        return (
          <Box display="flex" justifyContent="center" >
            <FolderCell row={row} />
          </Box>
        );
      },
      textAlign: "center",
    },
    {
      name: <Text className="zaplane-label">{__("Success Run", "zaplane")}</Text>,
      cell: (row) => <ZAPLabel label={row?.success_runs} type={"simple"} />,
      textAlign: "center",
    },
    {
      name: <Text className="zaplane-label">{__("Failed Runs", "zaplane")}</Text>,
      cell: (row) => <ZAPLabel label={row?.failed_runs} type={"simple"} />,
      textAlign: "center",
    },
    {
      name: <Text className="zaplane-label">{__("Status", "zaplane")}</Text>,
      cell: (row) => {
        const handleStatusChange = (row, newStatus) => {
          if (!row?.id || !newStatus) return;
          dispatch(updateWorkFlowStatus({ id: row.id, status: newStatus }));
        };
        return (
          <StatusOptions
            value={row?.status}
            options={{ items: [...statusOptions] }}
            onChangeHandler={(newStatus) => handleStatusChange(row, newStatus)}
          />
        );
      },
      textAlign: "center",
    },
    {
      name: <Text className="zaplane-label">{__("Action", "zaplane")}</Text>,
      cell: (row) => (
        <OptionMenu
          options={[
            {
              label: __("Edit", "zaplane"),
              icon: <Icon as={LiaEditSolid} />,
              type: "button",
              onClick: () =>
                navigate(
                  `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`
                ),
            },
            {
              label: __("Delete", "zaplane"),
              icon: <Icon as={RiDeleteBin6Line} />,
              type: "button",
              suffix: "trash",
              onClick: () => {
                if (
                  window.confirm(
                    __("Are you sure you want to delete?", "zaplane")
                  )
                ) {
                  dispatch(deleteWorkFlow(row.id));
                }
              },
            },
            {
              label: __("Details", "zaplane"),
              icon: <Icon as={HistoryIcon} />,
              type: "button",
              onClick: () => {
                setActiveRunId(row.id);
                setDrawerOpen(true);
              },
            },
            {
              label: __("Export", "zaplane"),
              icon: <Icon as={FiLayers} />,
              type: "button",
              hasBorder: false,
              onClick: () => handleExport(row),
            },
            {
              label: __("Save as Recipe", "zaplane"),
              icon: <Icon as={FaSave} />,
              type: "button",
              hasBorder: false,
              onClick: () => {
                setSelectedWorkflow(row);
                setRecipeModalOpen(true);
              },
            },
          ]}
        />
      ),
      textAlign: "center",
    }
  ];
  return (
    <>
      <ListTable
        columns={columns}
        data={Array.isArray(allWorkFlows) ? allWorkFlows : []}
        isRowSelectable={true}
        getSelectRowValue={(rows) => setSelection(rows || [])}
        showPagination={allWorkFlows.length >= 10}
        noDataText={__("No workflows found", "zaplane")}
        dataFetchingStatus={loading}
        totalItems={totalItems}
        currentPageNumber={currentPage}
        perPage={perPage}
        onChangePage={handlePageChange}
        onChangeItemsPerPage={handlePerPageChange}
      />
      <ZAPActionBar selection={selection} onDelete={handleDeleteSelected} onClose={() => setSelection([])} />
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
        {activeRunId && <WorkflowsLogs id={activeRunId} />}
      </ZAPDrawer>
      <SaveAsRecipeModal
        isOpen={recipeModalOpen}
        onClose={() => {
          setRecipeModalOpen(false);
          setSelectedWorkflow(null);
        }}
        workflowId={selectedWorkflow?.id}
        defaultTitle={selectedWorkflow?.title}
      />
    </>
  );
};

export default WorkflowTable;
