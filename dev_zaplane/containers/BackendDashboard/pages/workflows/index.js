import { useState } from "react";
import { __ } from "@wordpress/i18n";
import { Box, Button } from "@chakra-ui/react";
import TopBar from "@ZAPComponents/TopBar";
import WorkflowTable from "./WorkflowTable";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";


const CreateWorkflows = () => {

  const [isModalOpen, setIsModalOpen] = useState(false);

  return (
    <>
      <TopBar
        render={() => (
          <Box>
            <ZAPLabel
              label={__('Flows', 'zaplane')}
              variant="bold"
            />

          </Box>
        )}
        rightContent={() => (
          // <ZAPMenu
          //   triggerLabel="Create Workflow"
          //   items={[
          //     {
          //       label: "Create from Scratch",
          //       onClick: () => setIsModalOpen(true),
          //     },
          //   ]}
          // />
          <Button {...primaryBtn} onClick={() => setIsModalOpen(true)}>
            {__('Create Workflow', 'zaplane')}
          </Button>
        )}
      />

      <div className="zaplane-page-content">
        <WorkflowTable
        />
      </div>

      <CreateWorkflowModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
      />
    </>
  );
};

export default CreateWorkflows;
