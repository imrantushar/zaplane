import React, { useRef } from "react";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { HiUpload } from "react-icons/hi";
import { primaryBtn } from "../../../../../../../assets/scss/chakra/recipe";
import { __ } from "@wordpress/i18n";
export default function ImportJSONModal({
  isOpen,
  onClose,
  file,
  setFile,
  handleImport
}) {
  const fileInputRef = useRef(null);
  
  const handleFileChange = (e) => {
    const selected = e.target.files?.[0];
    if (selected) {
      if (!selected.name.endsWith(".json")) {
        alert("Only JSON files allowed");
        return;
      }
      if (selected.size > 1024 * 1024 * 2) {
        alert("Invalid file. Only JSON under 2MB allowed.");
        return;
      }
      setFile(selected);
    } else {
      setFile(null);
    }
  };

  return <WPModal title={__("Import JSON File", "zaplane")} isOpen={isOpen} onRequestClose={onClose} size="medium">
      <div className="flex flex-col items-center p-6 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 text-center">
        <input 
          type="file" 
          accept="application/json" 
          ref={fileInputRef}
          onChange={handleFileChange}
          className="hidden" 
        />
        <button 
          onClick={() => fileInputRef.current?.click()} 
          className="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none"
        >
          <HiUpload className="mr-2" /> {__("Select JSON File", "zaplane")}
        </button>
        {file && <p className="mt-2 text-sm text-gray-600">{file.name}</p>}
      </div>
      <div className="flex justify-end gap-[12px] mt-[24px]">
        <button variant={'outline'} onClick={onClose} className="mt-[16px]">
          {__("cancel", "zaplane")}
        </button>
        <button style={primaryBtn} onClick={handleImport} className="mt-[16px] px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 ml-3">
          {__("Import", "zaplane")}
        </button>
      </div>

    </WPModal>;
}