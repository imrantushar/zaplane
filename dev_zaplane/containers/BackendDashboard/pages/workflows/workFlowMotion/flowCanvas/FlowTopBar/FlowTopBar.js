import React from "react";
import { Button, Text, Flex } from "@chakra-ui/react";
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
import { primaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";
import { getRunWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowRuns";
import { useDispatch, useSelector } from "react-redux";
import { getAllVersion } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowVersion";
import { formatTime } from "../helper";
import { statusOptions } from "../../../helper";
import { workflowNodeListiner, workflowNodeListinerStop } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlowListiner";
import { startApiCountdown } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { workFLowExction } from "@ZAPRedux/Slices/workFlowSlice/actions/workflowExctions";
import { useApiCountdown } from "@ZAPHooks/useApiCountdown/useApiCountdown";
import '../styles.scss'

export default function FlowTopBar({
  navigate,
  workFlow,
  isFullscreen,
  toggleFullscreen,
  id,
  values,
  setFieldValue,
  handleSubmit,
  activeDrawer,
  setActiveDrawer,
}) {
  const { runs, versions, apiCountdown, apiRequestRunning } = useSelector((state) => state.workflows);
  const dispatch = useDispatch()
      useApiCountdown()
  return (
    <TopBar
      leftContent={() => (
        <>
          {!isFullscreen && (
            <Button variant="outline" onClick={() => navigate(-1)}>
              <FiArrowLeft />
            </Button>
          )}

          <Text fontSize="md" fontWeight="medium">
            {workFlow?.workflow?.title ||
              __("Untitled Workflow", "zaplane")}
          </Text>

          {!apiRequestRunning ? (
            <Button {...primaryBtn} onClick={() => {
              dispatch(startApiCountdown(120));
              dispatch(workflowNodeListiner(id));
            }}>
              {__("Test Flow Once", "zaplane")}
            </Button>
          ) : (
            <Button {...primaryBtn} onClick={() => dispatch(workflowNodeListinerStop(id))}>
              {__("Stop", "zaplane")}
            </Button>
          )}

          {apiRequestRunning && (
            <Text m="0" fontSize="18px">
              {__("Listening...", "zaplane")} {formatTime(apiCountdown)}
            </Text>
          )}
        </>
      )}
      rightContent={() => (
        <>
          <Button size="sm" variant="outline" onClick={toggleFullscreen}>
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
                size="sm"
                variant="outline"
                onClick={() => {
                  dispatch(getRunWorkFlow(id))
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
                onClick={() =>  dispatch(getRunWorkFlow(id))}
              >
                <TfiReload />{__("Refresh", "zaplane")}
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

            <RunsTable runs={runs} />
          </ZAPDrawer>

          {/* Version Drawer */}
          <ZAPDrawer
            title={__("Version History", "zaplane")}
            open={activeDrawer === "history"}
            isFullscreen={isFullscreen}
            onClose={() => setActiveDrawer(null)}
            trigger={
              <Text
                m="0"
                cursor="pointer"
                onClick={() => {
                  setActiveDrawer("history");
                  dispatch(getAllVersion(id))
                }}
              >
                <LucideHistory />
              </Text>
            }
          >
            <VersionHistoryTable versions={versions} id={id} />
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

          <Button {...primaryBtn} size="sm" onClick={handleSubmit}>
            {__("Update", "zaplane")}
          </Button>
        </>
      )}
    />
  );
}
