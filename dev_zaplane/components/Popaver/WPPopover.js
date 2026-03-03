import { Popover } from "@wordpress/components";
import "./styles.scss";

const WPPopover = ({ isOpen, onClose, title, children,prefix}) => {
  if (!isOpen) return null;

  return (
    <Popover
      className={`zaplane-popover ${prefix}`}
      position="bottom center"
      onFocusOutside={onClose}
    >
      <div className="zaplane-popover-inner">
        {title && (
          <div className="zaplane-popover-title">
            {title}
          </div>
        )}

        <div className="zaplane-popover-content">
          {children || <p>Default popover content</p>}
        </div>
      </div>
    </Popover>
  );
};

export default WPPopover;
