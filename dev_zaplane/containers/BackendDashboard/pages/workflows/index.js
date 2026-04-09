import { useState } from "react";
import { __ } from "@wordpress/i18n";
import { Box, Button, Flex, Image } from "@chakra-ui/react";
import TopBar from "@ZAPComponents/TopBar";
import WorkflowTable from "./WorkflowTable";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { IoIosArrowForward } from "react-icons/io";
import { plugin_root_url, route_path } from "@ZAPUtils/helper";


const CreateWorkflows = () => {

  const [isModalOpen, setIsModalOpen] = useState(false);

  return (
    <>
      <TopBar
        leftContent={() => (
          <>
            <Flex height='40px' width='40px' borderRadius='20px' gap='10px' background='var(--zaplane-second-primary)' alignItems='center' justifyContent='center'>
              <Image
                src={`${plugin_root_url}assets/images/zaplane.svg`}
                boxSize="20px"
              />
            </Flex>
            <IoIosArrowForward />
            <ZAPLabel
              as="h2"
              color="var(--zapplane-font-color)"
              type="subtitle"
              fontWeight="medium"
              label={__('Flows', 'zaplane')}
            />
          </>
        )}
        rightContent={() => (
          <>
            <Button {...primaryBtn} onClick={() => setIsModalOpen(true)}>
              {__('Create Workflow', 'zaplane')}
            </Button>
          </>
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
