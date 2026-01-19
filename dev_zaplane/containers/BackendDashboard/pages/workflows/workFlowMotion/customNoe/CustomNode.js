import { useState } from "react";
import {
  Handle,
  Position,
  NodeToolbar,
  useReactFlow,
} from "@xyflow/react";
import { Box, HStack, Icon } from "@chakra-ui/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { FaRegCopy } from "react-icons/fa";
import FloatingEdge from "../floatingEdge/FloatingEdge";
import ZAPText from "@ZAPComponents/Text";
export default function CustomNode({ id, data, xPos, yPos }) {
  const [hovered, setHovered] = useState(false);

  const { getEdges } = useReactFlow();
  const edges = getEdges();

  const hasOutgoingEdge = edges.some((e) => e.source === id);

  const NODE_WIDTH = 160;
  const NODE_HEIGHT = 48;

  const sourceX = xPos + NODE_WIDTH;
  const sourceY = yPos + NODE_HEIGHT / 2;


  return (
    <Box
      position="relative"
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
    >
      <NodeToolbar
        isVisible={true}
        position={Position.Top}
        align="start"
        offset={10}
      >
        <HStack>
          <ZAPText
            borderRadius="full"
            p="4px 8px"
            fontWeight="medium"
            background="#E6F4FF"
            margin={0}
          >
            {data.action || "Action"}
          </ZAPText>
        </HStack>
      </NodeToolbar>
      {
        data.action !== 'trigger' && <NodeToolbar
          isVisible={hovered}
          position={Position.Bottom}
          align="center"
          offset={-3}
        >
          <HStack
            bg="var(--zaplane-border-color)"
            color="black"
            p="6px"
            marginTop="4px"
            borderRadius="full"
            boxShadow="lg"
            cursor="pointer"
            pointerEvents="auto"
             _hover={{ bg: "red.300" }}
          >
            <Icon
              as={RiDeleteBin5Line}
              boxSize={4}
              cursor="pointer"
              onClick={(e) => {
                e.stopPropagation();
                data?.deleteNode(id);
              }}
            />
            {!data?.action && <Icon as={FaRegCopy} boxSize={4} />}
          </HStack>
        </NodeToolbar>
      }

      <Box
        bg="white"
        border="1px solid"
        borderColor="gray.300"
        borderRadius="md"
        px={4}
        py={2}
        minW="160px"
        textAlign="center"
        boxShadow="sm"
        onClick={data.onOpenDrawer}
      >
        {data?.action !== "trigger" && (
          <Handle
            type="target"
            position={Position.Left}
            style={{
              width: 10,
              height: 10,
              borderRadius: "50%",
              background: "#3182ce",
              border: "2px solid white",
            }}
          />
        )}

        <ZAPText m={0} fontSize="sm" fontWeight="medium">
          {data.app}
        </ZAPText>
        {!data.conditions && (
          <Handle
            type="source"
            position={Position.Right}
            style={{
              width: 10,
              height: 10,
              borderRadius: "50%",
              background: "#3182ce",
              border: "2px solid white",
            }}
          />
        )}
      </Box>
      {!hasOutgoingEdge && !data.conditions && (
        <FloatingEdge
          sourceX={sourceX}
          sourceY={sourceY}
          openDrawerFromAdd={data.openDrawerFromAdd}
        />
      )}
    </Box>
  );
}
