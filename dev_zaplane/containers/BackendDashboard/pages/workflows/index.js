import { useState } from "react";
import { __ } from "@wordpress/i18n";
import { Button, Flex, Image } from "@chakra-ui/react";

import TopBar from "@ZAPComponents/TopBar";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import SubTopBar from "@ZAPComponents/SubTopBar";

import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { IoIosArrowForward } from "react-icons/io";
import { plugin_root_url } from "@ZAPUtils/helper";
import ImportWorkflow from "./workFlowMotion/ImportWorkflow";
import WorkflowTable from "@ZAPComponents/WorkflowTable";



const CreateWorkflows = ({ onNavigateToEdit, title = __('Flows', 'zaplane')}) => {
  const [isModalOpen, setIsModalOpen] = useState(false);

  // if (res?.payload.id) {
  //     if (onNavigateToEdit) {
  //       onNavigateToEdit(res.payload.id);
  //     } else {
  //       navigate(
  //         `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${res.payload.id}`
  //       );
  //     }
  //   }
  return (
    <>
      <TopBar
        leftContent={() => (
          <>
            <Flex
              height="40px"
              width="40px"
              borderRadius="20px"
              background="var(--zaplane-second-primary)"
              alignItems="center"
              justifyContent="center"
            >
              <img
                src={`${plugin_root_url}assets/images/zaplane.svg`}
              />
            </Flex>

            <IoIosArrowForward />

            <ZAPLabel
              as="h2"
              color="var(--zapplane-font-color)"
              type="subtitle"
              fontWeight="medium"
              label={title}
            />
          </>
        )}
      />

      <SubTopBar heading={__("Workflows", "zaplane")}>
        <ImportWorkflow />
        <Button
          onClick={() => setIsModalOpen(true)}
          {...primaryBtn}
        >
          {__("Create Workflow", "zaplane")}
        </Button>
      </SubTopBar>

      <div className="zaplane-page-content">
        <WorkflowTable onNavigateToEdit={onNavigateToEdit} />
      </div>

      <CreateWorkflowModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
      />
    </>
  );
};

export default CreateWorkflows;