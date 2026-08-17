import { useEffect, useState, useMemo } from "react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate } from "react-router-dom";
import WPModal from "@ZAPComponents/Modal/WPModal";
import ZAPInput from "@ZAPComponents/ZAPInput";
import { createWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import { route_path } from "@ZAPUtils/helper";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";
import ZAPDivider from "@ZAPComponents/ZAPDivider";
import { addWorkflowToFolder, getFolders } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
const CreateWorkflowModal = ({
  isOpen,
  onClose,
  id,
  onNavigateToEdit
}) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [workflowName, setWorkflowName] = useState("");
  const [folderId, setFolderId] = useState(null);
  const {
    folders
  } = useSelector(state => state.folder);
  const allFolders = folders?.data || [];
  useEffect(() => {
    dispatch(getFolders());
  }, [dispatch]);
  const effectiveFolderId = folderId ?? id ?? null;
  const options = useMemo(() => {
    return allFolders.map(f => ({
      label: f.title,
      value: f.id
    }));
  }, [allFolders]);
  const handleCreate = async () => {
    if (!workflowName.trim()) return;
    const payload = {
      ...(effectiveFolderId && {
        folderId: effectiveFolderId
      })
    };
    const res = await dispatch(createWorkflows({
      title: workflowName
    }));
    if (res?.payload?.id) {
      if (onNavigateToEdit) {
        onNavigateToEdit(res.payload.id);
      } else {
        navigate(`${route_path}admin.php?page=zaplane-workflows&action=edit&id=${res.payload.id}`);
      }
    }
    if (effectiveFolderId) {
      await dispatch(addWorkflowToFolder({
        folder_id: effectiveFolderId,
        workflow_id: res?.payload?.id
      }));
    }
    setWorkflowName("");
    setFolderId(null);
    onClose();
  };
  return <WPModal title={__("Create Workflow", "zaplane")} isOpen={isOpen} onRequestClose={onClose} size="medium">
    <div flexDirection="column" gap="8px" className="flex flex-col gap-2 mt-6">
      <ZAPInput label={__("Workflow Name", "zaplane")} placeholder={__("Enter workflow name", "zaplane")} value={workflowName} onChange={e => setWorkflowName(e.target.value)} />
      {!!allFolders.length && <div direction="column" gap={2} className="flex">
        <div className="flex flex-col gap-2 w-full">
          <ZAPSelect
            label={__("Select Folder", "zaplane")}
            options={options}
            isClearable
            value={effectiveFolderId}
            onChange={opt => setFolderId(opt?.value || null)}
          />
        </div>
      </div>}



      <ZAPDivider mt="24px" />

      <div className="flex mt-5 gap-3">
        <button style={primaryBtn} onClick={handleCreate} disabled={!workflowName.trim()}>
          {__("Create", "zaplane")}
        </button>
      </div>
    </div>
  </WPModal>;
};
export default CreateWorkflowModal;