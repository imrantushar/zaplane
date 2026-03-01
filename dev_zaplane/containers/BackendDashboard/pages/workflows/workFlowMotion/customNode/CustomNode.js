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
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
export default function CustomNode({ id, data, canvasLayout }) {
  const [hovered, setHovered] = useState(false);

  const { getEdges } = useReactFlow();
  const edges = getEdges();

  const hasOutgoingEdge = edges.some((e) => e.source === id);
  const isLR = canvasLayout === "LR"
  const isSelectApp = data.app === "Select an app";
  const formattedAction = data?.action.charAt(0).toUpperCase() + data.action.slice(1);


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
          >
            {formattedAction || "Action"}
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
      }

      <Box
        bg="var(--zaplane-body-background)"
        // border="1px solid"
        // borderColor="var(--zaplane-border-color)"
        borderRadius="md"
        px={4}
        py={2}
        width="180px"
        textAlign="center"
        boxShadow="sm"
        onClick={data.onOpenDrawer}
      >
        {data?.action !== "trigger" && (
          <Handle
            type="target"
            position={canvasLayout === "LR" ? Position.Left : Position.Top}
            style={{
              width: 10,
              height: 10,
              borderRadius: "50%",
              background: "var(--zaplane-primary)",
              border: "2px solid var(--zaplane-background)",
            }}
          />
        )}
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
          <Box textAlign="left">
            <ZAPLabel label=
              {isSelectApp ? __(data.app, "zaplane")
                : sprintf(__("%s", "zaplane"), formatLabel(data.event))} type="basic" textOverflow="ellipsis"/>

            {!isSelectApp && (
              <ZAPLabel label=
                {sprintf(__("%s", "zaplane"), data.app)} type="simple" />

            )}
          </Box>
        </HStack>
        {!data.conditions && (
          <Handle
            type="source"
            position={canvasLayout === "LR" ? Position.Right : Position.Bottom}
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
      {!hasOutgoingEdge && !data.conditions && (
        <FloatingEdge
          // sourceX={sourceX}
          // sourceY={sourceY}
          openDrawerFromAdd={data.openDrawerFromAdd}
          canvasLayout={canvasLayout}
        />
      )}
    </Box>
  );
}
