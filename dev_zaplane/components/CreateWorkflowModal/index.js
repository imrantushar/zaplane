import { useState } from "react";
import { Box, Flex, Button } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import { useNavigate } from "react-router-dom";

import WPModal from "@ZAPComponents/Modal/WPModal";
import ZAPInput from "@ZAPComponents/ZAPInput";
import { createWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import { route_path } from "@ZAPUtils/helper";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";

const CreateWorkflowModal = ({ isOpen, onClose }) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [workflowName, setWorkflowName] = useState("");

  const handleCreate = async () => {
    if (!workflowName.trim()) return;

    const res = await dispatch(
      createWorkflows({ title: workflowName })
    );

    if (res?.payload?.id) {
      navigate(
        `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${res.payload.id}`
      );
    }

    setWorkflowName("");
    onClose();
  };

  return (
    <WPModal
      title={__("Create Workflow", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
      size="medium"
    >
      <Box>
        <ZAPInput
          label={__("Workflow Name", "zaplane")}
          placeholder={__("Enter workflow name", "zaplane")}
          value={workflowName}
          onChange={(e) => setWorkflowName(e.target.value)}
        />

        <Flex justify="flex-end" mt={5}>
          <Button variant="outline" mr={3} onClick={onClose}>
            {__("Cancel", "zaplane")}
          </Button>
          <Button
            {...primaryBtn}
            onClick={handleCreate}
            isDisabled={!workflowName.trim()}
          >
            {__("Create", "zaplane")}
          </Button>
        </Flex>
      </Box>
    </WPModal>
  );
};

export default CreateWorkflowModal;