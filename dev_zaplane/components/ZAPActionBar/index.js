import { createPortal } from 'react-dom';
import { __ } from "@wordpress/i18n";
import { IoMdClose } from "react-icons/io";

const ZAPActionBar = ({
  selection = [],
  onDelete,
  onClose,
  deleteLabel = __("Delete", "zaplane")
}) => {
  const hasSelection = selection?.length > 0;
  if (!hasSelection) return null;

  return createPortal(
    <div
      style={{ zIndex: 9999 }}
      className="fixed bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-3 bg-white border border-gray-200 rounded-lg shadow-lg px-4 py-3"
    >
      <span className="text-sm font-medium text-gray-700">
        {selection.length} {__("items selected", "zaplane")}
      </span>
      <div className="w-px h-5 bg-gray-300" />
      <button
        type="button"
        className="text-sm font-medium text-red-600 hover:text-red-700 px-3 py-1 rounded border border-red-300 hover:bg-red-50"
        onClick={onDelete}
      >
        {deleteLabel}
      </button>
      <div className="w-px h-5 bg-gray-300" />
      <button
        type="button"
        className="text-gray-400 hover:text-gray-600"
        onClick={onClose}
        aria-label={__("Close", "zaplane")}
      >
        <IoMdClose className="h-5 w-5" />
      </button>
    </div>,
    document.body
  );
};
export default ZAPActionBar;
