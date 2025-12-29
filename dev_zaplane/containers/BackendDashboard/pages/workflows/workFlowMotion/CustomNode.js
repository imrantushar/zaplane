import { useState } from "react";
import {
  Handle,
  Position,
  NodeToolbar,
  useReactFlow,
} from "@xyflow/react";
import { Box, Text, HStack, Icon, Badge, Button } from "@chakra-ui/react";
import { RiDeleteBin7Line } from "react-icons/ri";
import { FaRegCopy } from "react-icons/fa";
import FloatingEdge from "./FloatingEdge"; 

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
            background="#E6F4FF"
            margin={0}
          >
            {data.action || "Action"}
          </Text>
          <Text borderRadius="full" fontSize="sm" fontWeight="medium" margin={0}>
            {data.order || 1}
          </Text>
        </HStack>
      </NodeToolbar>
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
        >
          <Icon as={RiDeleteBin7Line} boxSize={4} onClick={()=>data?.deleteNode(id)} />
          {!data?.action && <Icon as={FaRegCopy} boxSize={4} />}
        </HStack>
      </NodeToolbar>
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
        {data?.action !== "Trigger" && (
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

        {data.conditions ? (
          <>
            <Text margin={0} fontSize="sm" fontWeight="bold" mb={2}>
              Conditions / Filters
            </Text>

            {data.conditions.map((cond, index) => (
              <HStack
                key={cond.id}
                justify="space-between"
                bg="white"
                p={2}
                mb={1}
                borderRadius="md"
                boxShadow="sm"
                position="relative"
              >
                <Text margin={0} fontSize="sm">
                  <Badge mr={2}>{cond.id}</Badge>
                  {cond.title}
                </Text>

                <Handle
                  type="source"
                  id={`condition-${cond.id}`}
                  position={Position.Right}
                  style={{
                    right: "-22px",
                    top: "50%",
                    transform: "translateY(-50%)",
                    width: 10,
                    height: 10,
                    background: "#38A169",
                    border: "2px solid white",
                    borderRadius: "50%",
                  }}
                />

                {index !== 0 && (
                  <HStack>
                    <Icon
                      as={FaRegCopy}
                      cursor="pointer"
                      onClick={() => data.onEditCondition(cond.id)}
                    />
                    <Icon
                      as={RiDeleteBin7Line}
                      cursor="pointer"
                      onClick={() => data.onDeleteCondition(cond.id)}
                    />
                  </HStack>
                )}
              </HStack>
            ))}

            <Button size="sm" w="100%" mt={2} onClick={data.onAddCondition}>
              +
            </Button>
          </>
        ) : (
          <Text m={0} fontSize="sm" fontWeight="medium">
            {data.label}
          </Text>
        )}
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
