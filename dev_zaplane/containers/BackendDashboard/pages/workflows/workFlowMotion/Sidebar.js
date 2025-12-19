import React from "react";

const items = Array.from({ length: 10 }, (_, i) => ({
  id: `node-${i + 1}`,
  label: `Node ${i + 1}`,
}));

export default function Sidebar() {
  const onDragStart = (event, nodeType) => {
    event.dataTransfer.setData("application/reactflow", nodeType);
    event.dataTransfer.effectAllowed = "move";
  };

  return (
    <div
      style={{
        width: 200,
        borderRight: "1px solid #ddd",
        padding: 10,
        background: "#f9f9f9",
      }}
    >
      <h4>Sidebar</h4>

      {items.map((item) => (
        <div
          key={item.id}
          draggable
          onDragStart={(e) => onDragStart(e, item.label)}
          style={{
            padding: "8px",
            marginBottom: "6px",
            background: "#fff",
            border: "1px solid #ccc",
            cursor: "grab",
          }}
        >
          {item.label}
        </div>
      ))}
    </div>
  );
}

