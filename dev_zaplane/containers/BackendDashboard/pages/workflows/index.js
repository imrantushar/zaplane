import { __ } from "@wordpress/i18n";

import { Flex } from "@chakra-ui/react";
import { Box } from "lucide-react";
import WorkflowMotion from "./workFlowMotion";
import Sidebar from "./sidebar";




const Workflows = () => {

  return (
    <>
      <Flex>
        <Sidebar />
        <WorkflowMotion />
      </Flex>
    </>
  );
};
      export default Workflows;
