import { useState } from "react";
import { __ } from "@wordpress/i18n";
import { Button } from "@chakra-ui/react";

import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import ImportWorkflow from "./workFlowMotion/ImportWorkflow";
import WorkflowTable from "@ZAPComponents/WorkflowTable";
import PageLayout from "@ZAPComponents/PageLayout";



const CreateWorkflows = () => {
  const [isModalOpen, setIsModalOpen] = useState(false);

  return (
    <PageLayout
        title="Flows"
        heading="Workflows"
        actions={
            <>
                <ImportWorkflow />
                <Button
                    onClick={() => setIsModalOpen(true)}
                    {...primaryBtn}
                >
                    {__("Create Workflow", "zaplane")}
                </Button>
            </>
        }
    >
        <WorkflowTable />

      <CreateWorkflowModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
      />
    </PageLayout>
  );
};

export default CreateWorkflows;