import { createPortal } from 'react-dom';
import { __ } from "@wordpress/i18n";
import { IoMdClose } from "react-icons/io";
import './styles.scss';

const ZAPActionBar = ({
  selection = [],
  onDelete,
  onClose,
  deleteLabel = __("Delete", "zaplane")
}) => {
  const hasSelection = selection?.length > 0;
  if (!hasSelection) return null;

  return createPortal(
    <div className="zap-action-bar">
      <span className="zap-action-bar__count">
        {selection.length} {__("items selected", "zaplane")}
      </span>
      <div className="zap-action-bar__divider" />
      <button
        type="button"
        className="zap-action-bar__delete"
        onClick={onDelete}
      >
        {deleteLabel}
      </button>
      <div className="zap-action-bar__divider" />
      <button
        type="button"
        className="zap-action-bar__close"
        onClick={onClose}
        aria-label={__("Close", "zaplane")}
      >
        <IoMdClose style={{ height: "20px", width: "20px" }} />
      </button>
    </div>,
    document.body
  );
};
export default ZAPActionBar;
