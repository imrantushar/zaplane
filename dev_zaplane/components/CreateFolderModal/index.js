import { useState, useEffect, useRef } from "react";
import { __ } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import WPModal from "@ZAPComponents/Modal/WPModal";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPDivider from "@ZAPComponents/ZAPDivider";
import { createFolder, getFolders } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";

const CreateFolderModal = ({
  isOpen,
  onClose
}) => {
  const dispatch = useDispatch();
  const [folderName, setFolderName] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const submittingRef = useRef(false);

  useEffect(() => {
    if (!isOpen) {
      setFolderName("");
      setIsSubmitting(false);
      submittingRef.current = false;
    }
  }, [isOpen]);

  const handleCreate = async () => {
    const trimmed = folderName.trim();
    if (submittingRef.current || !trimmed) return;
    submittingRef.current = true;
    setIsSubmitting(true);

    try {
      const res = await dispatch(createFolder({
        title: trimmed
      }));

      // ✅ success হলে list update
      if (res?.meta?.requestStatus === "fulfilled") {
        dispatch(getFolders()); // safe way
        setFolderName("");
        onClose();
      }
    } finally {
      submittingRef.current = false;
      setIsSubmitting(false);
    }
  };

  return <WPModal title={__("Create Folder", "zaplane")} isOpen={isOpen} onRequestClose={isSubmitting ? undefined : onClose} size="medium">
      <div className="mt-6">
        <ZAPInput
          label={__("Folder Name", "zaplane")}
          placeholder={__("Enter folder name", "zaplane")}
          value={folderName}
          onChange={e => setFolderName(e.target.value)}
          onKeyDown={e => {
            if (e.key === "Enter") {
              e.preventDefault();
              handleCreate();
            }
          }}
        />

        <ZAPDivider mt="24px" />

        <div className="flex mt-5 gap-3">
          <button
            style={{
              ...primaryBtn,
              display: "inline-flex",
              alignItems: "center",
              justifyContent: "center",
              gap: "8px",
              opacity: !folderName.trim() || isSubmitting ? 0.6 : 1,
              cursor: !folderName.trim() || isSubmitting ? "not-allowed" : "pointer"
            }}
            onClick={handleCreate}
            disabled={!folderName.trim() || isSubmitting}
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
export default CreateFolderModal;