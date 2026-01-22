import { getBezierPath } from "@xyflow/react";
import { Box, Center } from "@chakra-ui/react";
import { FaPlus } from "react-icons/fa";

const FloatingEdge = ({ sourceX, sourceY, openDrawerFromAdd }) => {
  const targetX = sourceX + 140;
  const targetY = sourceY;

  const [edgePath] = getBezierPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition: "right",
    targetPosition: "left",
  });

  return (
    <>
      {/* Dashed Edge */}
      <path
        d={edgePath}
        fill="none"
        stroke="#bdbdbd"
        strokeWidth={2}
        strokeDasharray="6 6"
      />

      <foreignObject
        width={32}
        height={32}
        x={targetX - 16}
        y={targetY - 16}
      >
        <Center
          as="button"
          onClick={openDrawerFromAdd}
          w="32px"
          h="32px"
          borderRadius="full"
          border="2px dashed var(--zaplane-border-color)"
          cursor="pointer"
          position="absolute"
          top="6px"
          right="-80px"
          _hover={{
            borderColor: "var(--zaplane-primary-color)",
            bg: "var(--zaplane-background)",
          }}
        >
          <Box as={FaPlus} fontSize="12px" color="var(--zaplane-primary-color)" />
          <Box as="span" 
          left="-45px"
          border="2px dashed var(--zaplane-border-color)" 
          top="13px"
          width="42px"
          position="absolute"/>
        </Center>
      </foreignObject>
    </>
  );
};

export default FloatingEdge;
