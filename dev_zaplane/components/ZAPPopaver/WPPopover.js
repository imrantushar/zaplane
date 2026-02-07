import { useState } from "react";
import { Button, Popover, PanelBody } from "@wordpress/components";
import "./WPPopover.scss"; // Import SCSS

const WPPopover = ({ buttonLabel = "Toggle Popover", children }) => {
  const [isVisible, setIsVisible] = useState(false);
  const toggleVisible = () => setIsVisible((prev) => !prev);

  return (
    <div className="zaplane-popover-wrapper">
      <Button
        variant="secondary"
        onClick={toggleVisible}
        className="zaplane-popover-trigger"
      >
        {buttonLabel}
      </Button>

      {isVisible && (
        <Popover
          className="zaplane-popover"
          position="bottom center"
          onFocusOutside={toggleVisible}
        >
          <PanelBody className="zaplane-popover-content">
            {children || <p>✅ Default Popover content</p>}
          </PanelBody>
        </Popover>
      )}
    </div>
  );
};

export default WPPopover;
