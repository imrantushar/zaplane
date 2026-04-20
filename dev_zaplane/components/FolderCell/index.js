import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { LuFolderOpen, LuFolderPlus, LuMinus } from "react-icons/lu";
import { getFolders, createFolder, addWorkflowToFolder, removeWorkflowFromFolder, getFolderWorkflows } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { getWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";
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
    return <div className="flex justify-center items-center min-h-[32px]">
                <div size="sm" />
            </div>;
  }
  return <>
            {selectedFolder ? <ZAPMenu items={menuItems} trigger={<div onClick={e => e.stopPropagation()} className="flex flex-row items-center gap-1 px-[14px] py-1 h-[36px] rounded-full border border-gray-200 bg-gray-50 inline-flex items-center max-w-[160px] cursor-pointer">
                            <LuFolderOpen style={{width:"14px", height:"14px"}} className="text-gray-500 shrink-0" />
                            <span isTruncated className="zaplane-label flex-[1]">
                                {selectedFolder.title}
                            </span>
                            <div as="span" _hover={{
        bg: "gray.200"
      }} onClick={e => {
        e.stopPropagation();
        handleRemove();
      }} aria-label={__("Remove from folder", "zaplane")} className="flex items-center justify-center w-[16px] h-[16px] rounded-sm shrink-0">
                                <LuMinus style={{width:"11px", height:"11px"}} className="text-gray-500" />
                            </div>
                        </div>} /> : <ZAPMenu items={menuItems} trigger={<button size="14px" variant="outline" onClick={e => e.stopPropagation()} aria-label={__("Add to folder", "zaplane")} className="text-[14px] font-[500] py-1 gap-1 h-[36px] rounded-full px-[16px]">
                            <LuFolderOpen style={{width:"14px", height:"14px"}} className="text-gray-500 mr-1" />
                            {__("Add", "zaplane")}
                        </button>} />}

            <WPModal isOpen={modalOpen} title={__("Create Folder", "zaplane")} onRequestClose={() => {
      setModalOpen(false);
      setFolderName("");
    }} shouldCloseOnClickOutside size="medium" suffix="create-folder">
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
                    <button variant="outline" size="sm" onClick={() => {
          setModalOpen(false);
          setFolderName("");
        }}>
                        {__("Cancel", "zaplane")}
                    </button>
                    <button style={primaryBtn} disabled={!folderName.trim()} onClick={handleCreateFolder}>
                        {__("Create", "zaplane")}
                    </button>
                </div>
            </WPModal>
        </>;
};
export default FolderCell;