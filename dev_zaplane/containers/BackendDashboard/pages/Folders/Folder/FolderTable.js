
import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { Text, Box, Icon, HStack, Button, Menu, Portal } from "@chakra-ui/react";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import ListTable from "@ZAPComponents/ListTable";
import { route_path } from "@ZAPUtils/helper";


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
import { exportWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";;
import OptionMenu from "@ZAPComponents/OptionMenu";
import { getFolderWorkflows } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import { downloadJSON, statusOptions } from "../../workflows/helper";
import WorkflowsLogs from "../../workflows/WorkflowsLogs";
import FolderCell from "../../workflows/FolderCell";
import ImportWorkflow from "../../workflows/workFlowMotion/ImportWorkflow";
import SubTopBar from "@ZAPComponents/SubTopBar";
import { primaryBtn } from "../../../../../../assets/scss/chakra/recipe";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";




const FolderTable = ({ folderId }) => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [activeRunId, setActiveRunId] = useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const { data, totalItems, perPage, currentPage } = useSelector(
    (state) => state.folder.folderWorkflows || {}
  );
  const [selection, setSelection] = useState([]);
  const [loading, setLoading] = useState(data?.length === 0);
  const [isModalOpen, setIsModalOpen] = useState(false);


  const handleRefresh = async (page = 1, itemsPerPage = perPage) => {
    setLoading(true);
    await dispatch(getFolderWorkflows({
      folder_id: folderId,
      page,
      perPage: itemsPerPage,
    }));
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
          <Box display="flex" justifyContent="center">
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
              label: __("Details", "zaplane"),
              icon: <Icon as={HistoryIcon} />,
              type: "button",
              onClick: () => {
                setActiveRunId(row.id);
                setDrawerOpen(true);
              },
            },
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
              label: __("Export", "zaplane"),
              icon: <Icon as={TbFileExport} />,
              type: "button",
              hasBorder: false,
              onClick: () => handleExport(row),
            },
          ]}
        />
      ),
      textAlign: "center",
    }
  ];

  return (
    <>
      <SubTopBar heading={__("Workflows", "zaplane")}>
        <ImportWorkflow />
        <Button
          onClick={() => setIsModalOpen(true)}
          {...primaryBtn}
        >
          {__("Create Workflow", "zaplane")}
        </Button>
      </SubTopBar>
      <ListTable
        columns={columns}
        data={data}
        isRowSelectable={true}
        getSelectRowValue={(rows) => setSelection(rows || [])}
        showPagination={data?.length >= 10}
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
      <CreateWorkflowModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        id={folderId}
      />
    </>
  );
};

export default FolderTable;
