import { useState } from "react";
import {
  Handle,
  Position,
  NodeToolbar,
  useReactFlow,
} from "@xyflow/react";
import { Box, HStack, Icon, Text } from "@chakra-ui/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { FaRegCopy } from "react-icons/fa";
import FloatingEdge from "../floatingEdge/FloatingEdge";
import { __ } from "@wordpress/i18n";
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
          <Text
            borderRadius="full"
            p="4px 8px"
            fontWeight="medium"
            className="zaplane-label"
            margin={0}
          >
            {data.action || "Action"}
          </Text>
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
            color="var(--zaplane-font-color)"
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
        bg="var(--zaplane-body-background)"
        border="1px solid"
        borderColor="var(--zaplane-border-color)"
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

        <Text className="zaplane-label" fontSize="sm" fontWeight="medium">
          {__(data.app, "zaplane")}
        </Text>
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
