import React from "react";
import { useDispatch } from "react-redux";
import { __ } from "@wordpress/i18n";
import { Copy } from "lucide-react";
import { showNotification } from "@ZAPRedux/Slices/notificationSlice/notificationSlice";

const CopyInput = ({ label, value, help }) => {
  const dispatch = useDispatch();

  const handleCopy = (e) => {
    e.preventDefault();
    navigator.clipboard.writeText(value || "");
    dispatch(
      showNotification({
        message: __("Copied to clipboard!", "zaplane"),
        isShow: true,
        type: "success",
      })
    );
  };

  return (
    <div className="flex flex-col gap-2">
      {label && <span className="zaplane-label">{__(label, "zaplane")}</span>}
      <div className="flex items-stretch border border-slate-200 rounded-md overflow-hidden">
        <input
          type="text"
          className="zaplane-input !border-none !rounded-none !shadow-none flex-1 bg-white px-3 py-2 focus:outline-none"
          value={value || ""}
          readOnly
        />
        <button
          type="button"
          onClick={handleCopy}
          className="px-3 bg-white !border-y-0 !border-r-0 !border-l border-solid border-slate-200 cursor-pointer flex items-center justify-center hover:bg-slate-50 transition-colors"
          title={__("Copy to clipboard", "zaplane")}
        >
          <Copy size={16} className="text-slate-500" />
        </button>
      </div>
      {help && <p className="text-gray-500 text-xs mt-1">{help}</p>}
    </div>
  );
};

export default CopyInput;
