import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { Text, Box, Icon, HStack, Button, Menu, Portal } from "@chakra-ui/react";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import ListTable from "@ZAPComponents/ListTable";
import { formatDateTime, route_path } from "@ZAPUtils/helper";
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
import { TbTemplate } from "react-icons/tb";
import { exportWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";
import SaveAsRecipeModal from "@ZAPComponents/SaveAsRecipeModal";
import { getRecipeFolders } from "@ZAPRedux/Slices/recipeSlice/actions/recipe";
import { FiCheck, FiChevronDown, FiFolder } from "react-icons/fi";

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
  /** Per-workflow target folder when saving as recipe (static default + user picks). */
  const [recipeTargetFolderByWorkflow, setRecipeTargetFolderByWorkflow] = useState({});

  const handleRefresh = async (page = 1, per_page = 10) => {
    setLoading(true)
    await dispatch(getWorkFlow({ page, per_page }));
    setLoading(false)
  };

  useEffect(() => {
    handleRefresh();
  }, []);

  useEffect(() => {
    dispatch(getRecipeFolders());
  }, [dispatch]);

  const handlePageChange = (newPage) => {
    handleRefresh(newPage, perPage)
  };

  const handlePerPageChange = (itemsPerPage) => {
    handleRefresh(currentPage, itemsPerPage)
  };
  //export handler for export functionality

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
  const columns = [
    {
      name: (
        <Text className="zaplane-label">
          {__("Title", "zaplane")}
        </Text>
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
        <Text className="zaplane-label" ml='-33px'>
          {__("Folders", "zaplane")}
        </Text>

      ),
      cell: (row) => {
        const rowKey = String(row.id);
        const flatFolders = flattenRecipeFolders(recipeFolders);
        const defaultFolder = DEFAULT_WORKFLOW_RECIPE_FOLDER();
        const selected = recipeTargetFolderByWorkflow[rowKey] ?? defaultFolder;

        return (
          <Box display="flex" justifyContent="center">
            <Menu.Root>
              <Menu.Trigger asChild>
                <Button
                  size="sm"
                  variant="outline"
                  maxW="220px"
                  borderRadius="md"
                  px={3}
                >
                  <HStack spacing={2} w="100%" justify="space-between" flex={1}>
                    <HStack spacing={2} minW={0} flex={1}>
                      <Icon as={FiFolder} boxSize={4} flexShrink={0} />
                      <Text m={0} fontSize="sm" fontWeight="500" noOfLines={1}>
                        {selected.label}
                      </Text>
                    </HStack>
                    <Icon as={FiChevronDown} boxSize={3} flexShrink={0} opacity={0.7} />
                  </HStack>
                </Button>
              </Menu.Trigger>
              <Portal>
                <Menu.Positioner>
                  <Menu.Content minW="200px">
                    <Menu.Item
                      value={`default-${rowKey}`}
                      onClick={() =>
                        setRecipeTargetFolderByWorkflow((prev) => ({
                          ...prev,
                          [rowKey]: defaultFolder,
                        }))
                      }
                    >
                      <HStack justify="space-between" w="100%">
                        <Text m={0}>{defaultFolder.label}</Text>
                        {selected.folderId === null ? <Icon as={FiCheck} boxSize={4} /> : null}
                      </HStack>
                    </Menu.Item>
                    {flatFolders.map((folder) => (
                      <Menu.Item
                        key={`${row.id}-f-${folder.id}`}
                        value={`folder-${folder.id}-${row.id}`}
                        onClick={() =>
                          setRecipeTargetFolderByWorkflow((prev) => ({
                            ...prev,
                            [row.id]: { folderId: folder.id, label: folder.title },
                          }))
                        }
                      >
                        <HStack justify="space-between" w="100%">
                          <Text m={0}>{folder.title}</Text>
                          {Number(selected.folderId) === Number(folder.id) ? (
                            <Icon as={FiCheck} boxSize={4} />
                          ) : null}
                        </HStack>
                      </Menu.Item>
                    ))}
                    <Menu.Separator />
                    <Menu.Item
                      value={`save-recipe-${row.id}`}
                      onClick={() => setSaveAsRecipeRow(row)}
                    >
                      {__("Create Folder", "zaplane")}
                    </Menu.Item>
                  </Menu.Content>
                </Menu.Positioner>
              </Portal>
            </Menu.Root>
          </Box>
        );
      },
      // columnWidth: "160px",
      textAlign: "center",
    },

    {
      name: (
        <Text className="zaplane-label">
          {__("Sucess Run", "zaplane")}
        </Text>

      ),
      cell: (row) => (
        <ZAPLabel label={row?.success_runs} type={"simple"} />
      ),
      // columnWidth: "160px",
      textAlign: "center",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("Failed Runs", "zaplane")}
        </Text>
      ),
      cell: (row) => (
        <ZAPLabel label={row?.failed_runs} type={"simple"} />
      ),
      // columnWidth: "160px",
      textAlign: "center",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("Status", "zaplane")}
        </Text>

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
          <ZAPTooltip content={__("Details", 'zaplane')}>
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
          <ZAPTooltip content={__("Export", 'zaplane')}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="2.917px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => handleExport(row)}
            >
              <Icon
                height="15px"
                width="15px"
                as={TbFileExport}

              />
            </Box>
          </ZAPTooltip>
          <ZAPTooltip content={__("Save as Recipe", 'zaplane')}>
            <Box
              display="flex"
              p={"5px 6px"}
              justifyContent="center"
              alignItems="center"
              borderRadius="2.917px"
              border="1px solid var(--zaplane-border-color)"
              onClick={() => setSaveAsRecipeRow(row)}
            >
              <Icon
                height="15px"
                width="15px"
                as={TbTemplate}
              />
            </Box>
          </ZAPTooltip>


        </HStack>
      ),
      // columnWidth: "90px",
      textAlign: "center",
    },
  ]
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
      console.error("Failed to delete selected team members", e);
    }
  };

  return (
    <>
      <ListTable
        columns={columns}
        data={Array.isArray(allWorkFlows) ? allWorkFlows : []}
        isRowSelectable={true}
        showSubHeader={false}
        showColumnFilter={false}
        getSelectRowValue={(rows) => {
          setSelection(rows || []);
        }}
        showPagination={allWorkFlows.length >= 10}
        noDataText={__("No workflows found", "zaplane")}
        dataFetchingStatus={loading}
        suffix="workflow-table"
        totalItems={totalItems}
        currentPageNumber={currentPage}
        perPage={perPage}
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
      </ZAPDrawer>
      <SaveAsRecipeModal
        isOpen={!!saveAsRecipeRow}
        onClose={() => setSaveAsRecipeRow(null)}
        workflowId={saveAsRecipeRow?.id}
        defaultTitle={saveAsRecipeRow?.title}
        initialFolderId={
          saveAsRecipeRow?.id != null
            ? recipeTargetFolderByWorkflow[saveAsRecipeRow.id]?.folderId ?? null
            : null
        }
      />
    </>

  );
};

export default WorkflowTable;
