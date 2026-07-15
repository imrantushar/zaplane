import React, { useState } from 'react';

import { __, sprintf } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import { route_path } from "@ZAPUtils/helper";
import { FiEye, FiFolder } from "react-icons/fi";
import { BsThreeDotsVertical } from "react-icons/bs";
import { useNavigate } from "react-router-dom";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { outlineBtn, primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { updateFolder, deleteFolder } from '@ZAPRedux/Slices/folderSlice/folderSlice';
import ZAPInput from '@ZAPComponents/ZAPInput';
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
      <div 
        onClick={() => goToFolder(folder?.id)} 
        className="bg-[var(--zaplane-background)] border border-[var(--zaplane-border-color)] rounded-xl p-6 transition-all duration-200 cursor-pointer shadow-sm hover:shadow-md  group"
      >
        {/* Top Section */}
        <div className="flex justify-between items-start mb-6">
          <div className="flex items-center gap-3 overflow-hidden">
            <div className="p-2 rounded-[4px] transition-colors">
              <FiFolder className="w-5 h-5" />
            </div>
            <span className="font-semibold text-[var(--zaplane-font-color)] text-[16px] truncate">
              {folder?.title}
            </span>
          </div>

          <ZAPMenu 
            isIcon
            items={[{
              label: __("Rename", "zaplane"),
              onClick: () => setIsRenameOpen(true)
            }, {
              label: __("Delete", "zaplane"),
              onClick: () => {
                if (window.confirm("Are you sure you want to delete this folder?")) {
                  dispatch(deleteFolder(folder?.id));
                }
              }
            }]} 
          />
        </div>

        {/* Bottom Section */}
        <div className="flex justify-between items-end">
          <div className="text-sm text-[var(--zaplane-font-secondary-color)] font-medium tracking-tight">
            {workflowLabel}
          </div>
          <button 
            aria-label={sprintf(__("View %s", "zaplane"), folder?.title)} 
            className="p-2  rounded-[4px] transition-all" 
            onClick={(e) => {
              e.stopPropagation();
              goToFolder(folder?.id);
            }}
          >
            <FiEye className="w-5 h-5" />
          </button>
        </div>
      </div>


      <WPModal title={__("Rename Folder", "zaplane")} isOpen={isRenameOpen} onRequestClose={() => setIsRenameOpen(false)} size="large">
    
        <div className="flex flex-col gap-6">
          <ZAPInput
            placeholder={__("Enter folder name", "zaplane")}
            value={title}
            onChange={e => setTitle(e.target.value)}
          />
          <div className="flex justify-end gap-3">
            <button style={outlineBtn} onClick={() => setIsRenameOpen(false)}>
              {__("Cancel", "zaplane")}
            </button>
            <button style={primaryBtn} onClick={handleRename} disabled={!title.trim()}>
              {__("Update", "zaplane")}
            </button>
          </div>
        </div>
      </WPModal>

    </>;
};
export default FolderCard;