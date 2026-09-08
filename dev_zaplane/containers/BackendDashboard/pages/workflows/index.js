import { useState } from "react";
import { __ } from "@wordpress/i18n";
import TopBar from "@ZAPComponents/TopBar";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import SubTopBar from "@ZAPComponents/SubTopBar";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { IoIosArrowForward } from "react-icons/io";
import { plugin_root_url } from "@ZAPUtils/helper";
import ImportWorkflow from "./workFlowMotion/ImportWorkflow";
import WorkflowTable from "@ZAPComponents/WorkflowTable";
import Teaser from "@ZAPComponents/Teaser";
const CreateWorkflows = ({
  onNavigateToEdit,
  title = __('Workflows', 'zaplane'),
  renderTopBar = null
}) => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  return <>
      {renderTopBar ? renderTopBar({}) : <TopBar leftContent={() => <>
              <div height="40px" width="40px" className="flex rounded-[20px] bg-var(--zaplane-second-primary) items-center justify-center">
                <img src={`${plugin_root_url}assets/images/zaplane.svg`} />
              </div>
  
              <IoIosArrowForward />
  
              <ZAPLabel as="h2" color="var(--zapplane-font-color)" type="subtitle" fontWeight="medium" label={title} />
            </>} />}

      <SubTopBar heading={__("Workflows", "zaplane")}>
        <div className="flex items-center gap-2">
          <ImportWorkflow />
        <button onClick={() => setIsModalOpen(true)} style={primaryBtn}>
          {__("Create Workflow", "zaplane")}
        </button>
        </div>
      </SubTopBar>

      <div className="zaplane-page-content">
        <Teaser screen="workflows" />
        <WorkflowTable onNavigateToEdit={onNavigateToEdit} />
      </div>

      <CreateWorkflowModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} onNavigateToEdit={onNavigateToEdit} />
    </>;
};
export default CreateWorkflows;