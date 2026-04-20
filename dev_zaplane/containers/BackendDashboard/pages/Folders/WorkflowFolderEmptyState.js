import { FolderPlus } from "lucide-react";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { useState } from "react";
import { __ } from "@wordpress/i18n";
import CreateFolderModal from "@ZAPComponents/CreateFolderModal";
const WorkflowFolderEmptyState = ({
  onCreateFolder
}) => {
  const [isFolderModalOpen, setIsFolderModalOpen] = useState(false);
  return <>
            <div px={{
      base: 6,
      md: 12
    }} className="bg-white border border-var(--zaplane-border-color, #CBD1D7) rounded-[lg] py-10 w-[full] text-center">
                <div gap='18px' className="flex flex-col">
                    <FolderPlus style={{width:"32px", height:"32px", strokeWidth:1.5}} className="text-var(--zaplane-font-color)" />

                    <span className="zaplane-label font-[700] text-[lg] text-var(--zaplane-font-color)">
                        {__('Create Folders for your Workflows', 'zaplane')}
                    </span>
                    <span lineHeight="tall" className="zaplane-label text-[sm] text-var(--zaplane-font-secondary-color) max-w-[sm]">
                        {__('Create folders to categories your workflows and also share with your workspace', 'zaplane')}
                    </span>
                    <button onClick={() => setIsFolderModalOpen(true)} style={primaryBtn}>
                        {__("Create Folder", "zaplane")}
                    </button>

                </div>
            </div>
            <CreateFolderModal isOpen={isFolderModalOpen} onClose={() => setIsFolderModalOpen(false)} /></>;
};
export default WorkflowFolderEmptyState;