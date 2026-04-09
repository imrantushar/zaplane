import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { Text, Box, Icon, HStack, Button, Menu, Portal } from "@chakra-ui/react";
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
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import { RiDeleteBin6Line } from "react-icons/ri";
import { LiaEditSolid } from "react-icons/lia";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPDrawer from "@ZAPComponents/Drawer";
import LogDetails from "@ZAPComponents/LogDetails";
import { nodeLogsRunDetails } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowLogs";
import { HistoryIcon } from "@ZAPUtils/icons";
import ZAPActionBar from "@ZAPComponents/ZAPActionBar";
import { TbFileExport } from "react-icons/tb";
import { exportWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";
import SaveAsRecipeModal from "@ZAPComponents/SaveAsRecipeModal";
import { getRecipeFolders } from "@ZAPRedux/Slices/recipeSlice/actions/recipe";
import { FiCheck, FiChevronDown, FiFolder } from "react-icons/fi";
import ZAPMenu from "@ZAPComponents/ZapMenu";

const flattenRecipeFolders = (nodes = [], acc = []) => {
  (nodes || []).forEach((node) => {
    acc.push({ id: node.id, title: node.title });
    flattenRecipeFolders(node.children || [], acc);
  });
  return acc;
};

const DEFAULT_WORKFLOW_RECIPE_FOLDER = () => ({
  folderId: null,
  label: __("Default", "zaplane"),
});

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
  const { folders: recipeFolders = [] } = useSelector((state) => state.recipes || {});
  const [selection, setSelection] = useState([]);
  const [loading, setLoading] = useState(allWorkFlows.length === 0);
  const [saveAsRecipeRow, setSaveAsRecipeRow] = useState(null);
  const [recipeTargetFolderByWorkflow, setRecipeTargetFolderByWorkflow] = useState({});

  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true);
    await dispatch(getWorkFlow({ page, per_page }));
    setLoading(false);
  };

  useEffect(() => {
    handleRefresh();
  }, []);

  useEffect(() => {
    dispatch(getRecipeFolders());
  }, [dispatch]);

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
      name: <Text className="zaplane-label">{__("Folders", "zaplane")}</Text>,
      cell: (row) => {
        const rowKey = String(row.id);
        const flatFolders = flattenRecipeFolders(recipeFolders);
        const defaultFolder = DEFAULT_WORKFLOW_RECIPE_FOLDER();
        const selected = recipeTargetFolderByWorkflow[rowKey] ?? defaultFolder;

        const menuItems = [
          {
            label: defaultFolder.label,
            icon: FiFolder,
            onClick: () =>
              setRecipeTargetFolderByWorkflow((prev) => ({
                ...prev,
                [row.id]: DEFAULT_WORKFLOW_RECIPE_FOLDER(),
              })),
            rightIcon: selected.folderId === null ? FiCheck : null,
          },
          ...flatFolders.map((folder) => ({
            label: folder.title,
            icon: FiFolder,
            onClick: () =>
              setRecipeTargetFolderByWorkflow((prev) => ({
                ...prev,
                [row.id]: { folderId: folder.id, label: folder.title },
              })),
            rightIcon:
              Number(selected.folderId) === Number(folder.id) ? FiCheck : null,
          })),
          { type: "divider" },
          {
            label: __("Save as Recipe", "zaplane"),
            onClick: () => setSaveAsRecipeRow(row),
          },
        ];

        return (
          <Box display="flex" justifyContent="center">
            <ZAPMenu
              items={menuItems}
              trigger={
                <Button size="sm" variant="outline" maxW="220px">
                  <HStack justify="space-between" w="full">
                    <HStack spacing={2}>
                      <Icon as={FiFolder} />
                      <Text noOfLines={1} className="zaplane-label">
                        {selected.label}
                      </Text>
                    </HStack>
                    <Icon as={FiChevronDown} />
                  </HStack>
                </Button>
              }
            />
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
        <HStack justify="flex-end" spacing="1" justifyContent={"center"}>
          <ZAPTooltip content={__("Details", "zaplane")}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="3px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => {
                setActiveRunId(row.id);
                setDrawerOpen(true);
                dispatch(nodeLogsRunDetails(row.id));
              }}
            >
              <Icon as={HistoryIcon} />
            </Box>
          </ZAPTooltip>
          <ZAPTooltip content={__("Edit", "zaplane")}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="3px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() =>
                navigate(`${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`)
              }
            >
              <Icon height="15px" width="15px" as={LiaEditSolid} />
            </Box>
          </ZAPTooltip>
          <ZAPTooltip content={__("Delete", "zaplane")}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="3px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => {
                if (window.confirm(__("Are you sure you want to delete?", "zaplane"))) {
                  dispatch(deleteWorkFlow(row.id));
                }
              }}
            >
              <Icon height="15px" width="15px" as={RiDeleteBin6Line} />
            </Box>
          </ZAPTooltip>
          <ZAPTooltip content={__("Export", "zaplane")}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="3px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => handleExport(row)}
            >
              <Icon height="15px" width="15px" as={TbFileExport} />
            </Box>
          </ZAPTooltip>
        </HStack>
      ),
      textAlign: "center",
    },
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
        {activeRunId && <LogDetails runId={activeRunId} onBack={() => setDrawerOpen(false)} />}
      </ZAPDrawer>
      <SaveAsRecipeModal
        isOpen={!!saveAsRecipeRow}
        onClose={() => setSaveAsRecipeRow(null)}
        workflowId={saveAsRecipeRow?.id}
        defaultTitle={saveAsRecipeRow?.title}
        initialFolderId={saveAsRecipeRow?.id != null ? recipeTargetFolderByWorkflow[saveAsRecipeRow.id]?.folderId ?? null : null}
      />
    </>
  );
};

export default WorkflowTable;