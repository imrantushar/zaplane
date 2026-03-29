import React, { useEffect, useState } from "react";
import { Button, Text, Flex, Input, Box, FileUpload } from "@chakra-ui/react";
import TopBar from "@ZAPComponents/TopBar";
import { FiArrowLeft } from "react-icons/fi";
import { TfiReload } from "react-icons/tfi";
import { LuFullscreen, LuMinimize, LuSquarePlay } from "react-icons/lu";
import { LucideHistory } from "lucide-react";
import Select from "react-select";
import { __ } from "@wordpress/i18n";
import ZAPDrawer from "@ZAPComponents/Drawer";
import RunsTable from "../RunsTable/RunsTable";
import VersionHistoryTable from "../VersionHistoryTable/VersionHistoryTable";
import { primaryBtn, secondPrimaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";
import { getRunWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowRuns";
import { useDispatch, useSelector } from "react-redux";
import { formatTime } from "../helper";
import { downloadJSON, statusOptions } from "../../../helper";
import { workflowNodeListiner, workflowNodeListinerStop } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowListiner";
import { startApiCountdown } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { workFLowExction } from "@ZAPRedux/Slices/workFlowSlice/actions/workflowExctions";
import { useApiCountdown } from "@ZAPHooks/useApiCountdown/useApiCountdown";
import '../styles.scss'
import { LiaStopCircleSolid } from "react-icons/lia";
import { CiPlay1 } from "react-icons/ci";
import { updateWorkFlowStatus, updateWorkFlowTitle } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import { exportWorkflows, importWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";
import ImportJSONModal from "./ImportJSONModal";

export default function FlowTopBar({
  workFlow,
  isFullscreen,
  toggleFullscreen,
  id,
  values,
  setFieldValue,
  handleSubmit,
  activeDrawer,
  setActiveDrawer,
  isFlowDirty
}) {
  const { apiCountdown, apiRequestRunning } = useSelector((state) => state.workflows);
  const [refreshing, setRefreshing] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [file, setFile] = useState(null);
  const dispatch = useDispatch()
  const navigate = useNavigate()
  useApiCountdown()
  useEffect(() => {
    if (!workFlow?.workflow) return;
    const updateStatusAndTitle = async () => {
      try {
        if (values?.status && values.status !== workFlow.workflow.status) {
          await dispatch(updateWorkFlowStatus({ id, status: values.status }));
        }
        if (values?.title && values.title !== workFlow.workflow.title) {
          await dispatch(updateWorkFlowTitle({ id, title: values.title }));
        }
      } catch (error) {
        console.error("Failed to update status or title:", error);
      }
    };

    updateStatusAndTitle();
  }, [values?.status, values?.title, workFlow, dispatch, id]);
  //listiner
  useEffect(() => {
    if (activeDrawer !== "logs") return;

    const interval = setInterval(async () => {
      setRefreshing(true);

      await dispatch(getRunWorkFlow({ id }));

      setRefreshing(false);
    }, 5000);

    return () => clearInterval(interval);
  }, [activeDrawer, dispatch, id]);

  // export work folw
  const handleExport = async () => {
    try {
      const res = await dispatch(
        exportWorkflows({
          workflow_ids: [id],
          versions: "all",
          include_runs: false,
        })
      );

      downloadJSON(res?.payload, values?.title || "workflow");
    } catch (err) {
      console.error("Export failed:", err);
    }
  };
  //import work flow will be handle in next update
  const handleImport = async () => {
    if (!file) {
      alert("Please select a JSON file");
      return;
    }

    try {
      const text = await file.text();
      const json = JSON.parse(text);
      await dispatch(
        importWorkflows(
          json
        )
      );

      setIsModalOpen(false);
      setFile(null);
    } catch (err) {
      console.error("Import failed:", err);
      alert("Invalid JSON file");
    }
  };

  return (
    <>
      <TopBar
        leftContent={() => (
          <>

            <Button variant="outline" height="36px" width="36px"
              onClick={() => {
                if (isFullscreen) {
                  toggleFullscreen();
                  return;
                }

                if (isFlowDirty) {
                  const confirmLeave = window.confirm(
                    "You have unsaved changes. Are you sure you want to leave?"
                  );
                  if (!confirmLeave) return;
                }

                navigate(`${route_path}admin.php?page=zaplane-workflows`);
              }}>
              <FiArrowLeft />
            </Button>

            <Box w="120px">
              <Input
                height='36px'
                fontSize='14px'
                fontWeight='500'
                value={
                  values?.title ?? workFlow?.workflow?.title ?? "Untitled Flow"
                }
                onChange={(e) => setFieldValue("title", e.target.value)}
                variant="outline"
                border="1px solid transparent"
                _hover={{
                  borderColor: "var(--zaplane-border-color)",
                }}
              // maxW="250px"

              />
            </Box>


          </>
        )}
        rightContent={() => (
          <Flex gap='12px'>
            {!apiRequestRunning ? (
              <Button {...secondPrimaryBtn} h="36px" onClick={() => {
                dispatch(startApiCountdown(120));
                dispatch(workflowNodeListiner(id));
              }}>
                <CiPlay1 />{__("Test Flow Once", "zaplane")}
              </Button>
            ) : (
              <Button {...secondPrimaryBtn} h='36px' onClick={() => dispatch(workflowNodeListinerStop(id))}>
                <LiaStopCircleSolid />{__("Stop", "zaplane")}
              </Button>
            )}

            {apiRequestRunning && (
              <Flex gap='4px' alignItems='center'>
                <Text className="zaplane-label">
                  {__("Listening...", "zaplane")}
                </Text>
                <Text className="zaplane-label" color="var(--zaplane-text-muted)">
                  {formatTime(apiCountdown)}
                </Text>
              </Flex>
            )}
            <Button size="sm" variant="outline"
              className={`${isFullscreen && "zaplane-button-actve"}`} onClick={toggleFullscreen}>
              {isFullscreen ? <LuMinimize /> : <LuFullscreen />}
            </Button>

            {/* Logs Drawer */}
            <ZAPDrawer
              title={__("Log History", "zaplane")}
              size="md"
              isFullscreen={isFullscreen}
              open={activeDrawer === "logs"}
              onClose={() => setActiveDrawer(null)}
              trigger={
                <Button
                  className={`zaplane-label ${activeDrawer === 'logs' && 'zaplane-button-actve'}`}
                  color='#454F59'
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    setActiveDrawer("logs");
                  }}
                >
                  {__("Logs", "zaplane")}
                </Button>
              }
            >
              <Flex gap="5px">
                <Button
                  size="sm"
                  variant="outline"
                  color='#454F59'
                  fontWeight="500"
                  border={"none"}
                  onClick={() => dispatch(getRunWorkFlow({ id }))}
                >
                  <TfiReload className={refreshing ? "zaplane-refresh-spin" : ""} />{__("Refresh", "zaplane")}
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  color='#454F59'
                  fontWeight="500"
                  border={"none"}
                  onClick={() =>
                    dispatch(workFLowExction({
                      workflow_hash: workFlow?.version?.hash,
                    }))
                  }
                >
                  <LuSquarePlay /> {__("Replay", "zaplane")}
                </Button>
              </Flex>

              <RunsTable id={id} />
            </ZAPDrawer>

            {/* Version Drawer */}
            <ZAPDrawer
              title={__("Version History", "zaplane")}
              open={activeDrawer === "history"}
              isFullscreen={isFullscreen}
              onClose={() => setActiveDrawer(null)}
              trigger={
                // <Text
                //   m="0"
                //   cursor="pointer"
                //   onClick={() => {
                //     setActiveDrawer("history");
                //   }}
                // >
                //   <LucideHistory />
                // </Text>
                <Button
                  className={`${activeDrawer === 'history' && 'zaplane-button-actve'}`}
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    setActiveDrawer("history");
                  }}
                >
                  <LucideHistory />
                </Button>
              }
            >
              <VersionHistoryTable id={id} />
            </ZAPDrawer>

            {/* Status Select */}
            <Select
              options={statusOptions}
              value={
                values?.status
                  ? statusOptions.find((opt) => opt.value === values.status)
                  : statusOptions.find(
                    (opt) => opt.value === workFlow?.workflow?.status
                  )
              }
              onChange={(selected) =>
                setFieldValue("status", selected.value)
              }
              isClearable={false}
              isSearchable={false}
              placeholder="Select status"
            />

            <Button {...primaryBtn} disabled={!isFlowDirty} size="sm" onClick={handleSubmit}>
              {__("Update", "zaplane")}
            </Button>
            <ZAPMenu
              isIcon
              items={[
                {
                  label: "Import",
                  onClick: () => setIsModalOpen(true),
                },
                {
                  label: "Export",
                  onClick: handleExport,
                },
              ]}
            />
          </Flex>
        )}
      />
      <ImportJSONModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        file={file}
        setFile={setFile}
        handleImport={handleImport}
      />
    </>

  );
}
