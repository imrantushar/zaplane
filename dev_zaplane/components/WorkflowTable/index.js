import { useState, useEffect, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import { Text, Box, Icon, Button } from "@chakra-ui/react";
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
import { exportWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";
import { getFolderWorkflows } from "@ZAPRedux/Slices/folderSlice/folderSlice";

import StatusOptions from "@ZAPComponents/StatusOptions";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPDrawer from "@ZAPComponents/Drawer";
import ZAPActionBar from "@ZAPComponents/ZAPActionBar";
import ZAPIconGroup from "@ZAPComponents/ZAPIconGroup/ZAPIconGroup";
import OptionMenu from "@ZAPComponents/OptionMenu";
import FolderCell from "@ZAPComponents/FolderCell";
import SubTopBar from "@ZAPComponents/SubTopBar";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import SaveAsRecipeModal from "@ZAPComponents/SaveAsRecipeModal";


import { RiDeleteBin6Line } from "react-icons/ri";
import { LiaEditSolid } from "react-icons/lia";
import { TbFileExport } from "react-icons/tb";
import { FiLayers } from "react-icons/fi";
import { HistoryIcon } from "@ZAPUtils/icons";
import WorkflowsLogs from "@ZAPContainers/BackendDashboard/pages/workflows/WorkflowsLogs";
import ImportWorkflow from "@ZAPContainers/BackendDashboard/pages/workflows/workFlowMotion/ImportWorkflow";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";


const WorkflowTable = ({ folderId = null, showHeader = false }) => {
  const navigate  = useNavigate();
  const dispatch  = useDispatch();
  const isFolder  = !!folderId;

  const globalState = useSelector((state) => state.workflows);
  const folderState = useSelector((state) => state.folder.folderWorkflows || {});

  const { data: folderData, totalItems: folderTotal, currentPage: folderPage, perPage: folderPerPage } = folderState;
  const { allWorkFlows, totalItems, currentPage, perPage } = globalState;
  const workflows    = isFolder ? (folderData  ?? []) : (Array.isArray(allWorkFlows) ? allWorkFlows : []);
  const totalCount   = isFolder ? folderTotal  : totalItems;
  const activePage   = isFolder ? folderPage   : currentPage;
  const activePerPage= isFolder ? folderPerPage: perPage;
  const [loading,          setLoading]          = useState(workflows.length === 0);
  const [selection,        setSelection]        = useState([]);
  const [activeRunId,      setActiveRunId]      = useState(null);
  const [drawerOpen,       setDrawerOpen]       = useState(false);
  const [isCreateOpen,     setIsCreateOpen]     = useState(false);
  const [recipeModalOpen,  setRecipeModalOpen]  = useState(false);
  const [selectedWorkflow, setSelectedWorkflow] = useState(null);

  const handleRefresh = useCallback(
    async (page = 1, per_page = activePerPage ?? 10) => {
      setLoading(true);
      if (isFolder) {
        await dispatch(getFolderWorkflows({ folder_id: folderId, page, perPage: per_page }));
      } else {
        await dispatch(getWorkFlow({ page, per_page }));
      }
      setLoading(false);
    },
    [dispatch, folderId, isFolder, activePerPage]
  );

  useEffect(() => {
    handleRefresh();
  }, []);  

  const handlePageChange      = (newPage)      => handleRefresh(newPage, activePerPage);
  const handlePerPageChange   = (itemsPerPage) => handleRefresh(activePage, itemsPerPage);


  const handleExport = async (row) => {
    try {
      const res = await dispatch(
        exportWorkflows({ workflow_ids: [row.id], versions: "all", include_runs: false })
      );
      downloadJSON(res?.payload, row.title || "workflow");
    } catch (err) {
      console.error("Export failed:", err);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm(__("Are you sure you want to delete?", "zaplane"))) return;
    await dispatch(deleteWorkFlow(id));
    await handleRefresh(activePage, activePerPage);
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
      await handleRefresh();
    } catch (e) {
      console.error("Failed to delete selected workflows:", e);
    }
  };

  const handleStatusChange = (row, newStatus) => {
    if (!row?.id || !newStatus) return;
    dispatch(updateWorkFlowStatus({ id: row.id, status: newStatus }));
  };

  const handleOpenRecipe = (row) => {
    setSelectedWorkflow(row);
    setRecipeModalOpen(true);
  };

  const handleCloseRecipe = () => {
    setRecipeModalOpen(false);
    setSelectedWorkflow(null);
  };

  const handleOpenDrawer = (id) => {
    setActiveRunId(id);
    setDrawerOpen(true);
  };

  const handleCloseDrawer = () => {
    setDrawerOpen(false);
    setActiveRunId(null);
  };

  const navigateToEdit = (id) =>
    navigate(`${route_path}admin.php?page=zaplane-workflows&action=edit&id=${id}`);


  const columns = [
    {
      name: <Text className="zaplane-label">{__("Apps", "zaplane")}</Text>,
      cell: (row) => <ZAPIconGroup icons={row.integration_icons} maxVisible={2} />,
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
          onClick={() => navigateToEdit(row.id)}
        >
          {row.title}
        </Text>
      ),
      textAlign: "start",
      columnWidth: "200px",
    },
    {
      name: <Text className="zaplane-label">{__("Folder", "zaplane")}</Text>,
      cell: (row) => (
        <Box display="flex" justifyContent="center">
          <FolderCell row={row} isFolder={isFolder} />
        </Box>
      ),
      textAlign: "center",
    },
    {
      name: <Text className="zaplane-label">{__("Success Run", "zaplane")}</Text>,
      cell: (row) => <ZAPLabel label={row?.success_runs} type="simple" />,
      textAlign: "center",
    },
    {
      name: <Text className="zaplane-label">{__("Failed Runs", "zaplane")}</Text>,
      cell: (row) => <ZAPLabel label={row?.failed_runs} type="simple" />,
      textAlign: "center",
    },
    {
      name: <Text className="zaplane-label">{__("Status", "zaplane")}</Text>,
      cell: (row) => (
        <StatusOptions
          value={row?.status}
          options={{ items: [...statusOptions] }}
          onChangeHandler={(newStatus) => handleStatusChange(row, newStatus)}
        />
      ),
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
              onClick: () => navigateToEdit(row.id),
            },
            {
              label: __("Delete", "zaplane"),
              icon: <Icon as={RiDeleteBin6Line} />,
              type: "button",
              suffix: "trash",
              onClick: () => handleDelete(row.id),
            },
            {
              label: __("Details", "zaplane"),
              icon: <Icon as={HistoryIcon} />,
              type: "button",
              onClick: () => handleOpenDrawer(row.id),
            },
            {
              label: __("Export", "zaplane"),
              icon: <Icon as={TbFileExport} />,
              type: "button",
              hasBorder: false,
              onClick: () => handleExport(row),
            },
            {
              label: __("Save as Recipe", "zaplane"),
              icon: <Icon as={FiLayers} />,
              type: "button",
              hasBorder: false,
              onClick: () => handleOpenRecipe(row),
            },
          ]}
        />
      ),
      textAlign: "center",
    },
  ];

  return (
    <>
      {showHeader && (
        <SubTopBar heading={__("Workflows", "zaplane")}>
          <ImportWorkflow />
          <Button onClick={() => setIsCreateOpen(true)} {...primaryBtn}>
            {__("Create Workflow", "zaplane")}
          </Button>
        </SubTopBar>
      )}

      <ListTable
        columns={columns}
        data={workflows}
        isRowSelectable
        getSelectRowValue={(rows) => setSelection(rows || [])}
        showPagination={totalCount >= 10}
        noDataText={__("No workflows found", "zaplane")}
        dataFetchingStatus={loading}
        totalItems={totalCount}
        currentPageNumber={activePage}
        perPage={activePerPage}
        onChangePage={handlePageChange}
        onChangeItemsPerPage={handlePerPageChange}
      />

      <ZAPActionBar
        selection={selection}
        onDelete={handleDeleteSelected}
        onClose={() => setSelection([])}
      />

      <ZAPDrawer
        open={drawerOpen}
        arrowClose
        onClose={handleCloseDrawer}
        closeOnOverlayClick
        title={__("Log Details", "zaplane")}
        placement="end"
        size="md"
      >
        {activeRunId && <WorkflowsLogs id={activeRunId} />}
      </ZAPDrawer>

      {showHeader && (
        <CreateWorkflowModal
          isOpen={isCreateOpen}
          onClose={() => setIsCreateOpen(false)}
          id={folderId}
        />
      )}

      <SaveAsRecipeModal
        isOpen={recipeModalOpen}
        onClose={handleCloseRecipe}
        workflowId={selectedWorkflow?.id}
        defaultTitle={selectedWorkflow?.title}
      />
    </>
  );
};

export default WorkflowTable;