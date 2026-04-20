import React, { useEffect, useRef, useState } from "react";
import TopBar from "@ZAPComponents/TopBar";
import { FiDownload } from "react-icons/fi";
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
import '../styles.scss';
import { LiaStopCircleSolid } from "react-icons/lia";
import { CiPlay1 } from "react-icons/ci";
import { updateWorkFlowStatus, updateWorkFlowTitle } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import { useNavigate } from "react-router-dom";
import { plugin_root_url, route_path } from "@ZAPUtils/helper";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import { exportWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";
import { IoIosArrowForward } from "react-icons/io";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
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
  isFlowDirty,
  onNavigateBack,
  renderTopBar = null
}) {
  const {
    apiCountdown,
    apiRequestRunning
  } = useSelector(state => state.workflows);
  const [refreshing, setRefreshing] = useState(false);
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [isEditingTitle, setIsEditingTitle] = useState(false);
  const titleInputRef = useRef(null);
  useApiCountdown();
  useEffect(() => {
    if (isEditingTitle && titleInputRef.current) {
      titleInputRef.current.focus();
      titleInputRef.current.select();
    }
  }, [isEditingTitle]);
  useApiCountdown();
  useEffect(() => {
    if (!workFlow?.workflow) return;
    const updateStatusAndTitle = async () => {
      try {
        if (values?.status && values.status !== workFlow.workflow.status) {
          await dispatch(updateWorkFlowStatus({
            id,
            status: values.status
          }));
        }
        if (values?.title && values.title !== workFlow.workflow.title) {
          await dispatch(updateWorkFlowTitle({
            id,
            title: values.title
          }));
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
      await dispatch(getRunWorkFlow({
        id
      }));
      setRefreshing(false);
    }, 5000);
    return () => clearInterval(interval);
  }, [activeDrawer, dispatch, id]);

  // export work folw
  const handleExport = async () => {
    try {
      const res = await dispatch(exportWorkflows({
        workflow_ids: [id],
        versions: "all",
        include_runs: false
      }));
      downloadJSON(res?.payload, values?.title || "workflow");
    } catch (err) {
      console.error("Export failed:", err);
    }
  };
  const currentTitle = values?.title ?? workFlow?.workflow?.title ?? "Untitled Flow";
  const rightActions = <div gap='12px' className="flex">
      {!apiRequestRunning ? <button style={secondPrimaryBtn} onClick={() => {
      dispatch(startApiCountdown(120));
      dispatch(workflowNodeListiner(id));
    }} className="h-[36px]">
          <CiPlay1 />{__("Test Flow Once", "zaplane")}
        </button> : <button style={secondPrimaryBtn} onClick={() => dispatch(workflowNodeListinerStop(id))} className="h-[36px]">
          <LiaStopCircleSolid />{__("Stop", "zaplane")}
        </button>}

      {apiRequestRunning && <div gap='4px' className="flex items-center">
          <span>
            {__("Listening...", "zaplane")}
          </span>
          <span className="zaplane-label text-var(--zaplane-text-muted)">
            {formatTime(apiCountdown)}
          </span>
        </div>}
      <ZAPTooltip content={'Full Screen'} positioning={{
      placement: "buttom",
      offset: {
        mainAxis: 45,
        crossAxis: -5
      }
    }}>
        <button size="sm" variant="outline" onClick={toggleFullscreen}>
          {isFullscreen ? <LuMinimize /> : <LuFullscreen />}
        </button>
      </ZAPTooltip>

      {/* Logs Drawer */}
      <ZAPDrawer title={__("Log History", "zaplane")} size="md" isFullscreen={isFullscreen} open={activeDrawer === "logs"} onClose={() => setActiveDrawer(null)} trigger={<button size="sm" variant="outline" onClick={() => setActiveDrawer("logs")} className={`${`zaplane-label ${activeDrawer === 'logs' && 'zaplane-button-actve'}`} text-#454F59`}>
            {__("Logs", "zaplane")}
          </button>}>
        <div gap="5px" className="flex">
          <button size="sm" variant="outline" onClick={() => dispatch(getRunWorkFlow({
          id
        }))} className="text-#454F59 font-[500] border">
            <TfiReload className={refreshing ? "zaplane-refresh-spin" : ""} />{__("Refresh", "zaplane")}
          </button>
          <button size="sm" variant="outline" onClick={() => dispatch(workFLowExction({
          workflow_hash: workFlow?.version?.hash
        }))} className="text-#454F59 font-[500] border">
            <LuSquarePlay /> {__("Replay", "zaplane")}
          </button>
        </div>
        <RunsTable id={id} activeDrawer={activeDrawer} setRefreshing={setRefreshing} />
      </ZAPDrawer>

      {/* Version Drawer */}
      <ZAPDrawer title={__("Version History", "zaplane")} open={activeDrawer === "history"} isFullscreen={isFullscreen} onClose={() => setActiveDrawer(null)} trigger={<ZAPTooltip content={'History'} positioning={{
      placement: "buttom",
      offset: {
        mainAxis: 45,
        crossAxis: -5
      }
    }}>
            <button size="sm" variant="outline" onClick={() => setActiveDrawer("history")}>
              <LucideHistory />
            </button>
          </ZAPTooltip>}>
        <VersionHistoryTable id={id} />
      </ZAPDrawer>

      {/* Status Select */}
      <Select options={statusOptions} value={values?.status ? statusOptions.find(opt => opt.value === values.status) : statusOptions.find(opt => opt.value === workFlow?.workflow?.status)} onChange={selected => setFieldValue("status", selected.value)} isClearable={false} isSearchable={false} placeholder="Select status" styles={{
      control: provided => ({
        ...provided,
        minHeight: "36px",
        height: '36px'
      })
    }} />

      <button style={primaryBtn} disabled={!isFlowDirty} size="sm" onClick={handleSubmit} className="h-[36px]">
        {__("Update", "zaplane")}
      </button>
      <ZAPMenu isIcon items={[{
      label: "Export",
      icon: FiDownload,
      onClick: handleExport
    }]} />
    </div>;
  if (renderTopBar) {
    return renderTopBar({
      rightActions,
      currentTitle,
      setFieldValue,
      isEditingTitle,
      setIsEditingTitle,
      titleInputRef
    });
  }
  return <TopBar leftContent={() => <>
          <div height='40px' width='40px' gap='10px' className="flex rounded-[20px] bg-var(--zaplane-second-primary) items-center justify-center">
            <img src={`${plugin_root_url}assets/images/zaplane.svg`} />
          </div>
          <IoIosArrowForward />
          <ZAPLabel as="h2" color="var(--zapplane-font-color)" type="subtitle" fontWeight="medium" fontSize="14px" href={onNavigateBack ? undefined : `${route_path}admin.php?page=zaplane-workflows`} onClick={onNavigateBack ? e => {
      e.preventDefault();
      onNavigateBack();
    } : undefined} label={__('WorkFlows', 'zaplane')} />
          <IoIosArrowForward />
          {isEditingTitle ? <input ref={titleInputRef} height="28px" value={currentTitle} onChange={e => setFieldValue("title", e.target.value)} onBlur={() => setIsEditingTitle(false)} onKeyDown={e => {
      if (e.key === "Enter" || e.key === "Escape") setIsEditingTitle(false);
    }} variant="outline" minW="80px" className="text-[14px] font-[500] border rounded-[4px] px-[6px] w-[auto] max-w-[200px]" /> : <span as="h2" onClick={() => setIsEditingTitle(true)} noOfLines={1} textOverflow="ellipsis" className="m-0 text-[14px] font-[500] text-var(--zapplane-font-color) cursor-pointer max-w-[200px]">
              {currentTitle}
            </span>}
        </>} rightContent={() => rightActions} />;
}