import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { LuFolderOpen, LuFolderPlus, LuMinus } from "react-icons/lu";
import { getFolders, createFolder, addWorkflowToFolder, removeWorkflowFromFolder, getFolderWorkflows } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { getWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import { outlineBtn, primaryBtn } from "../../../assets/scss/chakra/recipe";
const FolderCell = ({
  row,
  isFolder = false
}) => {
  const dispatch = useDispatch();
  const {
    folders
  } = useSelector(state => state.folder);
  const allFolders = folders?.data || [];
  const [modalOpen, setModalOpen] = useState(false);
  const [folderName, setFolderName] = useState("");
  const [creating, setCreating] = useState(false);
  const [assigning, setAssigning] = useState(false);
  const selectedFolder = allFolders.find(f => f.id === row?.folder_id);
  useEffect(() => {
    if (!allFolders.length) {
      dispatch(getFolders());
    }
  }, []);
  const handleSelectFolder = async folder => {
    setAssigning(true);
    try {
      await dispatch(addWorkflowToFolder({
        folder_id: folder.id,
        workflow_id: row.id
      }));
      if (isFolder) {
        await dispatch(getFolderWorkflows({
          folder_id: row?.folder_id
        }));
      } else {
        await dispatch(getWorkFlow());
      }
    } finally {
      setAssigning(false);
    }
  };
  const handleRemove = async () => {
    if (!selectedFolder) return;
    setAssigning(true);
    try {
      await dispatch(removeWorkflowFromFolder({
        folder_id: selectedFolder.id,
        workflow_id: row.id
      }));
      if (isFolder) {
        await dispatch(getFolderWorkflows({
          folder_id: row?.folder_id
        }));
      } else {
        await dispatch(getWorkFlow());
      }
    } finally {
      setAssigning(false);
    }
  };
  const handleCreateFolder = async () => {
    const trimmed = folderName.trim();
    if (!trimmed) return;
    setCreating(true);
    try {
      const res = await dispatch(createFolder({
        title: trimmed
      }));
      const newFolder = res?.payload?.data || res?.payload;
      if (newFolder?.id) {
        await dispatch(addWorkflowToFolder({
          folder_id: newFolder.id,
          workflow_id: row.id
        }));
        dispatch(getFolders());
        dispatch(getWorkFlow());
      }
    } finally {
      setCreating(false);
      setFolderName("");
      setModalOpen(false);
    }
  };
  const menuItems = [...allFolders.map(folder => ({
    label: folder.title,
    icon: LuFolderOpen,
    onClick: () => handleSelectFolder(folder)
  })), ...(allFolders.length ? [{
    type: "divider"
  }] : []), {
    label: __("Create New", "zaplane"),
    icon: LuFolderPlus,
    onClick: () => setModalOpen(true)
  }];
  if (assigning) {
    return (
      <div className="flex items-center justify-center h-[36px] w-[100px] border border-[#E5E7EB] bg-white rounded-full">
        <svg className="animate-spin h-4 w-4 text-[#006BFF]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
    );
  }
  return <>
    {selectedFolder ? <ZAPMenu items={menuItems} trigger={<div onClick={e => e.stopPropagation()} className="flex flex-row items-center gap-2 px-4 h-[36px] rounded-full border border-[#E5E7EB] bg-white group hover:border-[#D1D5DB] transition-all cursor-pointer max-w-[160px]">
      <LuFolderOpen size={14} className="text-[#6B7280] shrink-0" />
      <span className="text-[13px] font-semibold text-[#111827] truncate flex-[1]">
        {selectedFolder.title}
      </span>
      <div
        onClick={e => {
          e.stopPropagation();
          handleRemove();
        }}
        className="flex items-center justify-center w-[18px] h-[18px] rounded-full bg-gray-50 hover:bg-gray-100 shrink-0 transition-colors"
        title={__("Remove from folder", "zaplane")}
      >
        <LuMinus size={11} className="text-[#6B7280]" />
      </div>
    </div>} /> : <ZAPMenu items={menuItems} trigger={<button
      onClick={e => e.stopPropagation()}
      className="flex items-center gap-2 px-4 h-[36px] rounded-full border border-[#E5E7EB] bg-white text-[13px] font-semibold text-[#374151] hover:bg-[#F9FAFB] hover:border-[#D1D5DB] transition-all"
      aria-label={__("Add to folder", "zaplane")}
    >
      <LuFolderOpen size={14} className="text-[#6B7280]" />
      {__("Add", "zaplane")}
    </button>} />}

    <WPModal isOpen={modalOpen} title={__("Create Folder", "zaplane")} onRequestClose={() => {
      setModalOpen(false);
      setFolderName("");
    }} shouldCloseOnClickOutside size="medium" suffix="create-folder">
      <div className="flex flex-col gap-4">
        <span className="zaplane-label mb-4">
          {__("Streamline your workflows by organizing them into folders.", "zaplane")}
        </span>

        <input placeholder={__("Folder Name", "zaplane")} value={folderName} onChange={e => setFolderName(e.target.value)} onKeyDown={e => {
          if (e.key === "Enter") handleCreateFolder();
          if (e.key === "Escape") {
            setModalOpen(false);
            setFolderName("");
          }
        }} autoFocus className="zaplane-input mb-5" />

        <div className="flex flex-row items-center justify-end gap-3">
          <button style={outlineBtn} onClick={() => {
            setModalOpen(false);
            setFolderName("");
          }}>
            {__("Cancel", "zaplane")}
          </button>
          <button style={primaryBtn} disabled={!folderName.trim()} onClick={handleCreateFolder}>
            {__("Create", "zaplane")}
          </button>
        </div>
      </div>
    </WPModal>
  </>;
};
export default FolderCell;