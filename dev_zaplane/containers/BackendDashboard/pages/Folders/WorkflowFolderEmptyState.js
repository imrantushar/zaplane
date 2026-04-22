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
            <div className="bg-[var(--zaplane-background)] border border-[var(--zaplane-border-color)] rounded-xl py-12 px-6 text-center max-w-2xl mx-auto shadow-sm">
                <div className="flex flex-col items-center gap-6">
                    <div className="p-4 bg-[var(--zaplane-second-primary)] rounded-full text-[var(--zaplane-primary)]">
                        <FolderPlus className="w-10 h-10" strokeWidth={1.5} />
                    </div>

                    <div className="space-y-2">
                        <h3 className="text-xl font-bold text-[var(--zaplane-font-color)]">
                            {__('Create Folders for your Workflows', 'zaplane')}
                        </h3>
                        <p className="text-[var(--zaplane-font-secondary-color)] max-w-sm mx-auto leading-relaxed">
                            {__('Create folders to categorize your workflows and easily share them with your workspace.', 'zaplane')}
                        </p>
                    </div>

                    <button 
                        onClick={() => setIsFolderModalOpen(true)} 
                        className="bg-[var(--zaplane-primary)] hover:opacity-90 text-white font-medium py-2.5 px-8 rounded-lg transition-all shadow-md active:scale-95"
                    >
                        {__("Create Folder", "zaplane")}
                    </button>

                </div>
            </div>
            <CreateFolderModal isOpen={isFolderModalOpen} onClose={() => setIsFolderModalOpen(false)} /></>;
};
export default WorkflowFolderEmptyState;