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
import { outlineBtn, primaryBtn, secondPrimaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";
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
import { BsThreeDotsVertical } from "react-icons/bs";
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
  // useEffect(() => {
  //   if (activeDrawer !== "logs") return;
  //   const interval = setInterval(async () => {
  //     setRefreshing(true);
  //     await dispatch(getRunWorkFlow({
  //       id
  //     }));
  //     setRefreshing(false);
  //   }, 5000);
  //   return () => clearInterval(interval);
  // }, [activeDrawer, dispatch, id]);

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
  const toolbarButtonStyle = {
    border: "1px solid var(--zaplane-border-color)",
  };
  const activeToolbarButtonStyle = {
    border: "1px solid var(--zaplane-primary)",
  };
  const rightActions = <div className="flex items-center gap-3">
    {!apiRequestRunning ? (
      <button
        onClick={() => {
          dispatch(startApiCountdown(120));
          dispatch(workflowNodeListiner(id));
        }}
        className="flex items-center gap-2 h-9 px-4 bg-[var(--zaplane-second-primary)] text-[var(--zaplane-primary)] font-medium rounded-[4px] hover:opacity-90 transition-all"
      >
        <CiPlay1 className="text-lg" />
        <span>{__("Test Flow Once", "zaplane")}</span>
      </button>
    ) : (
      <button
        onClick={() => dispatch(workflowNodeListinerStop(id))}
        className="flex items-center gap-2 h-9 px-4 bg-red-50 text-red-600 font-medium rounded-[4px] hover:bg-red-100 transition-all"
      >
        <LiaStopCircleSolid className="text-xl" />
        <span>{__("Stop", "zaplane")}</span>
      </button>
    )}

    {apiRequestRunning && (
      <div className="flex items-center gap-2">
        <span className="text-[13px] font-medium text-gray-500">
          {__("Listening...", "zaplane")}
        </span>
        <span className="text-[13px] font-bold text-[var(--zaplane-primary)]">
          {formatTime(apiCountdown)}
        </span>
      </div>
    )}

    <ZAPTooltip content={'Full Screen'}>
      <button
        onClick={toggleFullscreen}
        style={outlineBtn}
      >
        {isFullscreen ? <LuMinimize size={18} /> : <LuFullscreen size={18} />}
      </button>
    </ZAPTooltip>

    <ZAPDrawer
      title={__("Log History", "zaplane")}
      maxWidth='max-w-[600px]'
      isFullscreen={isFullscreen}
      open={activeDrawer === "logs"}
      onClose={() => setActiveDrawer(null)}
      trigger={
        <button
          onClick={() => setActiveDrawer("logs")}
          style={activeDrawer === "logs" ? activeToolbarButtonStyle : toolbarButtonStyle}
          className={`h-9 px-4 border rounded-[4px] text-sm font-medium transition-all ${activeDrawer === 'logs'
            ? 'bg-[var(--zaplane-second-primary)] border-[var(--zaplane-primary)] text-[var(--zaplane-primary)]'
            : 'border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300'
            }`}
        >
          {__("Logs", "zaplane")}
        </button>
      }
    >
      <div className="flex gap-[5px] mb-[12px]">
        <button
          onClick={() => dispatch(getRunWorkFlow({ id }))}
          className="flex items-center gap-1 px-3 py-1 text-sm font-medium text-[#454F59] border-0 bg-transparent hover:bg-gray-100 rounded"
        >
          <TfiReload className={refreshing ? "animate-spin" : ""} />
          {__("Refresh", "zaplane")}
        </button>

        <button
          onClick={() =>
            dispatch(workFLowExction({ workflow_hash: workFlow?.version?.hash }))
          }
          className="flex items-center gap-1 px-3 py-1 text-sm font-medium text-[#454F59] border-0 bg-transparent hover:bg-gray-100 rounded"
        >
          <LuSquarePlay />
          {__("Replay", "zaplane")}
        </button>
      </div>
      <RunsTable id={id} activeDrawer={activeDrawer} setRefreshing={setRefreshing} />
    </ZAPDrawer>

    <ZAPDrawer
      title={__("Version History", "zaplane")}
      maxWidth='max-w-[600px]'
      open={activeDrawer === "history"}
      isFullscreen={isFullscreen}
      onClose={() => setActiveDrawer(null)}
      trigger={
        <ZAPTooltip content={'History'}>
          <button
            onClick={() => setActiveDrawer("history")}
            style={activeDrawer === "history" ? activeToolbarButtonStyle : toolbarButtonStyle}
            className={`flex items-center justify-center w-9 h-9 border rounded-[4px] transition-all ${activeDrawer === 'history'
              ? 'bg-[var(--zaplane-second-primary)] border-[var(--zaplane-primary)] text-[var(--zaplane-primary)]'
              : 'border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300'
              }`}
          >
            <LucideHistory size={18} />
          </button>
        </ZAPTooltip>
      }
    >
      <VersionHistoryTable id={id} />
    </ZAPDrawer>

    {/* Status Select */}
    <Select
      options={statusOptions}
      value={values?.status ? statusOptions.find(opt => opt.value === values.status) : statusOptions.find(opt => opt.value === workFlow?.workflow?.status)}
      onChange={selected => setFieldValue("status", selected.value)}
      isClearable={false}
      isSearchable={false}
      placeholder="Select status"
      className="zaplane-selete"
      formatOptionLabel={(opt) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
          <span style={{ width: '8px', height: '8px', borderRadius: '50%', backgroundColor: opt.color, flexShrink: 0, display: 'inline-block' }} />
          {opt.label}
        </div>
      )}
    />

    <button
      disabled={!isFlowDirty}
      onClick={handleSubmit}
      className="h-9 px-6 bg-[var(--zaplane-primary)] hover:opacity-90 active:scale-[0.98] text-white font-semibold rounded-[4px] transition-all shadow-sm shadow-blue-200 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      {__("Update", "zaplane")}
    </button>

    <ZAPMenu
      trigger={
        <button
          style={{ ...outlineBtn, padding: '11px' }}
          onClick={e => e.stopPropagation()}
          aria-label="More options"
        >
          <BsThreeDotsVertical />
        </button>
      }
      items={[{
        label: "Export",
        icon: FiDownload,
        onClick: handleExport
      }]}
    />
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
  return <TopBar leftContent={() => (
    <div className="flex items-center gap-3">
      <div className="w-10 h-10 rounded-full bg-[var(--zaplane-second-primary)] flex items-center justify-center shadow-inner">
        <img
          src={`${plugin_root_url}assets/images/zaplane.svg`}
          className="w-5 h-5"
          alt="Zaplane logo"
        />
      </div>

      <IoIosArrowForward className="text-gray-400 text-xs" />

      <ZAPLabel
        as="h2"
        color="var(--zaplane-font-color)"
        type="subtitle"
        fontWeight="medium"
        fontSize="14px"
        href={onNavigateBack ? undefined : `${route_path}admin.php?page=zaplane-workflows`}
        onClick={onNavigateBack ? e => {
          e.preventDefault();
          onNavigateBack();
        } : undefined}
        label={__('Flows', 'zaplane')}
        className="hover:text-[var(--zaplane-primary)] transition-colors"
      />

      <IoIosArrowForward className="text-gray-400 text-xs" />

      {isEditingTitle ? (
        <input
          ref={titleInputRef}
          height="28px"
          value={currentTitle}
          onChange={e => setFieldValue("title", e.target.value)}
          onBlur={() => setIsEditingTitle(false)}
          onKeyDown={e => {
            if (e.key === "Enter" || e.key === "Escape") setIsEditingTitle(false);
          }}
          className="text-[14px] font-semibold border-b-2 border-primary-500 focus:outline-none bg-transparent px-1 w-auto max-w-[200px]"
        />
      ) : (
        <span
          onClick={() => setIsEditingTitle(true)}
          className="m-0 text-[14px] font-semibold text-[var(--zaplane-font-color)] truncate cursor-pointer max-w-[240px] hover:bg-gray-50 px-2 py-1 rounded transition-colors"
        >
          {currentTitle}
        </span>
      )}
    </div>
  )} rightContent={() => rightActions} />;
}
