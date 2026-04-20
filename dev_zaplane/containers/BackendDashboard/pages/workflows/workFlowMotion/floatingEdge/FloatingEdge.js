import { getBezierPath } from "@xyflow/react";
import { FaPlus } from "react-icons/fa";
const FloatingEdge = ({
  openDrawerFromAdd,
  canvasLayout
}) => {
  const isLR = canvasLayout === "LR";
  const [edgePath] = getBezierPath({
    sourcePosition: isLR ? "right" : "bottom",
    targetPosition: isLR ? "left" : "top"
  });
  return <>
      {/* Dashed Edge */}
      <path d={edgePath} fill="none" stroke="#bdbdbd" strokeWidth={2} strokeDasharray="6 6" />

      <foreignObject width={32} height={32}>
        <div as="button" onClick={openDrawerFromAdd} position="absolute" top={isLR ? "13px" : "110px"} right={isLR ? "-80px" : "73px"} _hover={{
        borderColor: "var(--zaplane-primary-color)",
        bg: "var(--zaplane-background)"
      }} className="flex items-center justify-center w-[32px] h-[32px] rounded-full border cursor-pointer">
          <div as={FaPlus} className="text-[12px] text-var(--zaplane-primary-color)" />
          <div as="span" top={isLR ? "13px" : "-49px"} left={isLR ? "-45px" : "11px"} width={isLR ? "42px" : "1px"} height={isLR ? "0" : "49px"} position="absolute" className="border" />
        </div>
      </foreignObject>
    </>;
};
export default FloatingEdge;