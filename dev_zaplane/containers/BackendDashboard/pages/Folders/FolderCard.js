import React, { useState } from 'react';

import { __, sprintf } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import { route_path } from "@ZAPUtils/helper";
import { FiEye, FiFolder } from "react-icons/fi";
import { useNavigate } from "react-router-dom";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { updateFolder, deleteFolder } from '@ZAPRedux/Slices/folderSlice/folderSlice';
const FolderCard = ({
  folder
}) => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [title, setTitle] = useState(folder?.title || "");
  const [isRenameOpen, setIsRenameOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const workflowCount = folder?.workflow_count ?? 0;
  const workflowLabel = workflowCount === 1 ? __("1 Workflow", "zaplane") : sprintf(__("%d Workflows", "zaplane"), workflowCount);
  const goToFolder = id => navigate(`${route_path}admin.php?page=zaplane-folders&action=edit&id=${id}`);
  const handleRename = async () => {
    if (!title?.trim()) return;
    setIsUpdating(true);
    await dispatch(updateFolder({
      id: folder?.id,
      title: title.trim()
    }));
    setIsUpdating(false);
    setIsRenameOpen(false);
  };
  return <>
      <div position="relative" boxShadow={'var(--zaplane-shadow-2)'} transition="border-color 0.18s, box-shadow 0.18s" onClick={() => goToFolder(folder?.id)} _hover={{
      boxShadow: "var(--zaplane-shadow)"
    }} className="bg-var(--zaplane-background) rounded-[8px] p-4 min-h-[104px]">

        <div justify="space-between" align="flex-start" gap={3} className="flex mb-3">
          <div minW={0} align="flex-start" className="flex flex-row items-center gap-2">
            <FiFolder style={{width:"20px", height:"20px"}} className="shrink-0 mt-0.5" />
            <span noOfLines={2} className="font-[600] text-[15px] m-0 cursor-pointer">
              {folder?.title}
            </span>
          </div>


          <ZAPMenu isIcon items={[{
          label: __("Rename", "zaplane"),
          onClick: () => setIsRenameOpen(true)
        }, {
          label: __("Delete", "zaplane"),
          onClick: () => {
            if (window.confirm("Are you sure you want to delete this folder?")) {
              dispatch(deleteFolder(folder?.id));
            }
          }
        }]} />
        </div>


        <div justify="space-between" align="center" className="flex">
          <span className="text-[13px] text-gray-500 m-0">
            {workflowLabel}
          </span>
          <button aria-label={sprintf(__("View %s", "zaplane"), folder?.title)} className="p-2 hover:bg-gray-100 rounded-md" onClick={() => goToFolder(folder?.id)}>
            <FiEye style={{width:"16px", height:"16px"}} />
          </button>
        </div>
      </div>


      <WPModal title={__("Rename Folder", "zaplane")} isOpen={isRenameOpen} onRequestClose={() => setIsRenameOpen(false)} size="large">
        <input value={title} onChange={e => setTitle(e.target.value)} placeholder={__("Enter folder name", "zaplane")} className="zaplane-input mb-4" />
        <div justify="flex-end" gap={3} className="flex">
          <button variant="outline" onClick={() => setIsRenameOpen(false)}>
            {__("Cancel", "zaplane")}
          </button>
          <button style={primaryBtn} onClick={handleRename} disabled={!title.trim()}>
            {__("Update", "zaplane")}
          </button>
        </div>
      </WPModal>

    </>;
};
export default FolderCard;