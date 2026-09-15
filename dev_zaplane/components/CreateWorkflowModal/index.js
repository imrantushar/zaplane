import { useEffect, useState, useMemo, useRef } from "react";
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
  const [isSubmitting, setIsSubmitting] = useState(false);
  const submittingRef = useRef(false);

  const {
    folders
  } = useSelector(state => state.folder);
  const allFolders = folders?.data || [];

  useEffect(() => {
    dispatch(getFolders());
  }, [dispatch]);

  useEffect(() => {
    if (!isOpen) {
      setWorkflowName("");
      setFolderId(null);
      setIsSubmitting(false);
      submittingRef.current = false;
    }
  }, [isOpen]);

  const effectiveFolderId = folderId ?? id ?? null;
  const options = useMemo(() => {
    return allFolders.map(f => ({
      label: f.title,
      value: f.id
    }));
  }, [allFolders]);

  const handleCreate = async () => {
    const trimmed = workflowName.trim();
    if (submittingRef.current || !trimmed) return;
    submittingRef.current = true;
    setIsSubmitting(true);

    try {
      const res = await dispatch(createWorkflows({
        title: trimmed
      }));

      if (res?.payload?.id) {
        if (effectiveFolderId) {
          await dispatch(addWorkflowToFolder({
            folder_id: effectiveFolderId,
            workflow_id: res.payload.id
          }));
        }
        setWorkflowName("");
        setFolderId(null);
        onClose();

        if (onNavigateToEdit) {
          onNavigateToEdit(res.payload.id);
        } else {
          navigate(`${route_path}admin.php?page=zaplane-workflows&action=edit&id=${res.payload.id}`);
        }
      }
    } finally {
      submittingRef.current = false;
      setIsSubmitting(false);
    }
  };

  return <WPModal title={__("Create Workflow", "zaplane")} isOpen={isOpen} onRequestClose={isSubmitting ? undefined : onClose} size="medium">
    <div flexDirection="column" gap="8px" className="flex flex-col gap-2 mt-6">
      <ZAPInput
        label={__("Workflow Name", "zaplane")}
        placeholder={__("Enter workflow name", "zaplane")}
        value={workflowName}
        onChange={e => setWorkflowName(e.target.value)}
        onKeyDown={e => {
          if (e.key === "Enter") {
            e.preventDefault();
            handleCreate();
          }
        }}
      />
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
        <button
          style={{
            ...primaryBtn,
            display: "inline-flex",
            alignItems: "center",
            justifyContent: "center",
            gap: "8px",
            opacity: !workflowName.trim() || isSubmitting ? 0.6 : 1,
            cursor: !workflowName.trim() || isSubmitting ? "not-allowed" : "pointer"
          }}
          onClick={handleCreate}
          disabled={!workflowName.trim() || isSubmitting}
        >
          {isSubmitting && (
            <svg className="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          )}
          {isSubmitting ? __("Creating...", "zaplane") : __("Create", "zaplane")}
        </button>
      </div>
    </div>
  </WPModal>;
};
export default CreateWorkflowModal;