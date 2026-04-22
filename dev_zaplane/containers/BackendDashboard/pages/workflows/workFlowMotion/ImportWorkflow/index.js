import React, { useState } from "react";
import { useDispatch } from "react-redux";
import { __ } from "@wordpress/i18n";
import ImportJSONModal from "./ImportJSONModal";
import { importWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";
import { FiUpload } from "react-icons/fi";
import { getWorkFlow } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import { outlineBtn } from "../../../../../../../assets/scss/chakra/recipe";
const ImportWorkflow = () => {
  const dispatch = useDispatch();
  const [isOpen, setIsOpen] = useState(false);
  const [file, setFile] = useState(null);
  const handleImport = async () => {
    if (!file) {
      alert("Please select a JSON file");
      return;
    }
    try {
      const text = await file.text();
      const json = JSON.parse(text);
      await dispatch(importWorkflows(json));
      await dispatch(getWorkFlow({
        page: 1,
        per_page: 20
      }));
      setIsOpen(false);
      setFile(null);
    } catch (err) {
      console.error("Import failed:", err);
      alert("Invalid JSON file");
    }
  };
  return <>
            <ZAPTooltip content={__("Import Workflow", "zaplane")}>
                <button style={outlineBtn}  onClick={() => setIsOpen(true)}>
                    <FiUpload />
                </button>
            </ZAPTooltip>


            <ImportJSONModal isOpen={isOpen} onClose={() => setIsOpen(false)} file={file} setFile={setFile} handleImport={handleImport} />
        </>;
};
export default ImportWorkflow;