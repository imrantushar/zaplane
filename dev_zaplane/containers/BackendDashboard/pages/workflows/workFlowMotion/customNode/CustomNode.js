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
import FloatingEdge from "../FloatingEdge/FloatingEdge";
import { __, sprintf } from "@wordpress/i18n";
import { formatLabel } from "@ZAPUtils/helper";
import { FaWordpress } from "react-icons/fa6";

export default function CustomNode({ id, data, canvasLayout, nodes }) {
  const [hovered, setHovered] = useState(false);
  const { getEdges } = useReactFlow();
  const edges = getEdges();


  const hasOutgoingEdge = edges.some((e) => e.source === id);

  const isLR = canvasLayout === "LR";
  const isSelectApp = data.app === "Select an app";

  const formattedAction =
    data?.action?.charAt(0).toUpperCase() + data?.action?.slice(1);

  const isCondition = data?.app === "Condition";
  const node = nodes.find((n) => n.id === id);
  const hasPort = node?.port === undefined;


  return (
    <Box
      position="relative"
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
    >
      {/* TOP TOOLBAR */}
      <NodeToolbar isVisible={true} position={Position.Top} align="start" offset={10}>
        <HStack>
          <Text
            borderRadius="full"
            p="4px 8px"
            fontWeight="medium"
            className="zaplane-label"
          >
            {formattedAction || "Action"}
          </Text>
        </HStack>
      </NodeToolbar>

      {/* DELETE TOOLBAR */}
      {data.action !== "trigger" && hasPort && (
        <NodeToolbar isVisible={hovered} position={Position.Bottom} align="center" offset={-3}>
          <HStack
            bg="var(--zaplane-border-color)"
            color="var(--zaplane-font-color)"
            p="6px"
            marginTop="4px"
            marginLeft={isLR ? "0" : "100px"}
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
      )}

      {/* NODE BODY */}
      <Box
        bg="var(--zaplane-body-background)"
        borderRadius="md"
        px={4}
        py={2}
        width="180px"
        textAlign="center"
        boxShadow="sm"
        onClick={data.onOpenDrawer}
      >
        {/* TARGET HANDLE */}
        {data?.action !== "trigger" && (
          <Handle
            type="target"
            position={isLR ? Position.Left : Position.Top}
            style={{
              width: 10,
              height: 10,
              borderRadius: "50%",
              background: "var(--zaplane-primary)",
              border: "2px solid var(--zaplane-background)",
            }}
          />
        )}

        {/* NODE CONTENT */}
        <HStack spacing={3} align="center">
          <Box
            w="40px"
            h="40px"
            display="flex"
            alignItems="center"
            justifyContent="center"
            borderRadius="8px"
            bg="orange.50"
          >
            <Icon as={FaWordpress} boxSize={5} color="orange.500" />
          </Box>

          <Box textAlign="left" flex="1" minW="0">
            <Text
              className="zaplane-label"
              overflow="hidden"
              textOverflow="ellipsis"
              whiteSpace="nowrap"
            >
              {isSelectApp
                ? __(data.app, "zaplane")
                : sprintf(__("%s", "zaplane"), formatLabel(data.event))}
            </Text>

            {!isSelectApp && (
              <Text
                className="zaplane-sub-title"
                fontSize="14px"
                overflow="hidden"
                textOverflow="ellipsis"
                whiteSpace="nowrap"
              >
                {sprintf(__("%s", "zaplane"), data.app)}
              </Text>
            )}
          </Box>
        </HStack>

        {/* SOURCE HANDLES */}

        {isCondition ? (
          <>
            <Handle
              type="source"
              id="true"
              position={isLR ? Position.Right : Position.Bottom}
              style={{
                top: isLR ? "50%" : undefined,
                left: !isLR ? "50%" : undefined,
                width: 10,
                height: 10,
                borderRadius: "50%",
                background: "var(--zaplane-primary)",
                border: "2px solid var(--zaplane-background)",
              }}
            />
            <Handle
              type="source"
              id="false"
              position={isLR ? Position.Right : Position.Bottom}
              style={{
                top: isLR ? "50%" : undefined,
                left: !isLR ? "50%" : undefined,
                width: 10,
                height: 10,
                borderRadius: "50%",
                background: "var(--zaplane-primary)",
                border: "2px solid var(--zaplane-background)",
              }}
            />
          </>
        ) : (
          <Handle
            type="source"
            position={isLR ? Position.Right : Position.Bottom}
            style={{
              width: 10,
              height: 10,
              borderRadius: "50%",
              background: "var(--zaplane-primary)",
              border: "2px solid var(--zaplane-background)",
            }}
          />
        )}
      </Box>

      {/* ADD NODE BUTTON */}
      {!hasOutgoingEdge && !isCondition && (
        <FloatingEdge
          openDrawerFromAdd={data.openDrawerFromAdd}
          canvasLayout={canvasLayout}
        />
      )}
    </Box>
  );
}