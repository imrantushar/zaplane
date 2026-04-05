import { useState } from "react";
import { __ } from "@wordpress/i18n";
import { Box, Button, Flex, Image } from "@chakra-ui/react";
import TopBar from "@ZAPComponents/TopBar";
import WorkflowTable from "./WorkflowTable";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import { outlineBtn, primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { IoIosArrowForward } from "react-icons/io";
import { plugin_root_url } from "@ZAPUtils/helper";
import SubTopBar from "@ZAPComponents/SubTopBar";
import { FiHelpCircle } from "react-icons/fi";


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
          // <ZAPMenu
          //   triggerLabel="Create Workflow"
          //   items={[
          //     {
          //       label: "Create from Scratch",
          //       onClick: () => setIsModalOpen(true),
          //     },
          //   ]}
          // />
          <Flex gap={3} alignItems="center">
            <Button
              {...outlineBtn}
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--zaplane-font-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><g transform="scale(0.9) translate(1.5,1.5)"><path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"></path><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"></path><path d="M8 6v8"></path></g></svg>
              {__("What's New")}
            </Button>
            <Button
              {...outlineBtn}
              onClick={() => {
                window.open('https://zaplane.com/', '_blank');
              }}
            >
              <FiHelpCircle color='var(--zaplane-font-color)' />
              {__("Help")}
            </Button>
          </Flex>
        )}
      />
      <SubTopBar heading={__("Workflows", "zaplane")}>
        <Button onClick={() => setIsModalOpen(true)} {...primaryBtn}>
          {__("Create Workflow", "zaplane")}
        </Button>
      </SubTopBar>

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
