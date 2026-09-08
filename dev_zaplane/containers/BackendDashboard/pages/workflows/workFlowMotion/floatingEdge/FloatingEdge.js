import { FaPlus } from "react-icons/fa";
const FloatingEdge = ({
  openDrawerFromAdd,
  canvasLayout
}) => {
  const isLR = canvasLayout === "LR";

  // Configuration for positioning
  const lineLength = 40; // length of the dashed line
  const buttonSize = 32;

  const containerStyle = {
    position: "absolute",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    pointerEvents: "none",
    zIndex: 10,
    // Position relative to the node's handles
    ...(isLR ? {
      top: "50%",
      right: `-${lineLength + buttonSize}px`,
      transform: "translateY(-50%)",
      flexDirection: "row",
      width: `${lineLength + buttonSize}px`,
      height: `${buttonSize}px`,
    } : {
      bottom: `-${lineLength + buttonSize}px`,
      left: "50%",
      transform: "translateX(-50%)",
      flexDirection: "column",
      width: `${buttonSize}px`,
      height: `${lineLength + buttonSize}px`,
    })
  };

  const lineStyle = {
    ...(isLR ? {
      width: `${lineLength}px`,
      height: "0px",
      borderTop: "2px dashed var(--zaplane-border-color)",
    } : {
      width: "0px",
      height: `${lineLength}px`,
      borderLeft: "2px dashed var(--zaplane-border-color)",
    })
  };

  const buttonStyle = {
    width: `${buttonSize}px`,
    height: `${buttonSize}px`,
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "var(--zaplane-background)",
    border: "1.5px dashed var(--zaplane-border-color)",
    borderRadius: "50%",
    cursor: "pointer",
    transition: "all 0.2s ease",
    color: "var(--zaplane-font-secondary-color)",
    boxShadow: "0 1px 2px 0 rgba(0, 0, 0, 0.05)",
    pointerEvents: "auto",
  };

  return (
    <div style={containerStyle} className="zaplane-floating-edge-container">
      <div style={lineStyle} className="zaplane-floating-edge-line" />
      <div 
        onClick={(e) => {
          e.stopPropagation();
          openDrawerFromAdd();
        }}
        style={buttonStyle}
        className="zaplane-add-node-button hover:scale-110"
      >
        <FaPlus size={12} />
      </div>
    </div>
  );
};


export default FloatingEdge;