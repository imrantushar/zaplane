import { Handle, Position } from "@xyflow/react";

export default function CustomNode({ data }) {
  return (
    <div
      style={{
        padding: 10,
        border: "1px solid #555",
        borderRadius: 4,
        background: "#fff",
        minWidth: 80,
        textAlign: "center",
        width: "30px"
      }}
    >
      <Handle
        type="target"
        position={Position.Top}
      />
      {data.label}
      <Handle
        type="source"
        position={Position.Bottom}
      />
    </div>
  );
}
