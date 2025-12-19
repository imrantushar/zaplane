import { useState } from "react";
import { Handle, Position, NodeToolbar } from "@xyflow/react";
import { Box, Text, HStack, Icon } from "@chakra-ui/react";
import { RiDeleteBin7Line } from "react-icons/ri";
import { FaRegCopy } from "react-icons/fa";

export default function CustomNode({ data }) {
  const [hovered, setHovered] = useState(false);

  return (
    <Box
      position="relative"
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
    >
      <NodeToolbar
        isVisible={hovered}
        position={Position.Bottom}
        align="end"
        offset={-3}
        
      >
        <HStack
          bg="gray.800"
          color="white"
          px={3}
          py={1}
          borderRadius="md"
          boxShadow="md"
          cursor="pointer"
          pointerEvents="auto"
          onMouseEnter={() => setHovered(true)}   
          onMouseLeave={() => setHovered(false)}
        >
          <Icon as={RiDeleteBin7Line} boxSize={4} />
          <Icon as={FaRegCopy} boxSize={4} />
        </HStack>
      </NodeToolbar>
      <Box
        bg="white"
        border="1px solid"
        borderColor="gray.300"
        borderRadius="md"
        px={4}
        py={2}
        minW="100px"
        textAlign="center"
        boxShadow="sm"
      >
        <Handle type="target" position={Position.Left} />

        <Text m={0} fontSize="sm" fontWeight="medium">
          {data.label}
        </Text>

        <Handle type="source" position={Position.Right} />
      </Box>
    </Box>
  );
}
