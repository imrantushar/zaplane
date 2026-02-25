import { getBezierPath } from "@xyflow/react";
import { Box, Center } from "@chakra-ui/react";
import { FaPlus } from "react-icons/fa";

const FloatingEdge = ({  openDrawerFromAdd, canvasLayout }) => {
 
  const isLR = canvasLayout === "LR"
  const [edgePath] = getBezierPath({
    sourcePosition: isLR ? "right" : "bottom",
    targetPosition: isLR ? "left" : "top",
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
          top={isLR ? "13px" : "110px"}
          right={isLR ? "-80px" : "73px"}
          _hover={{
            borderColor: "var(--zaplane-primary-color)",
            bg: "var(--zaplane-background)",
          }}
        >
          <Box as={FaPlus} fontSize="12px" color="var(--zaplane-primary-color)" />
          <Box as="span"
            top={isLR ? "11px" : "-49px"}
            left={isLR ? "-45px" : "11px"}
            border="2px dashed var(--zaplane-border-color)"
            width={isLR ? "42px" : "1px"}
            height={isLR ? "0" : "49px"}
            position="absolute" />
        </Center>
      </foreignObject>
    </>
  );
};

export default FloatingEdge;
