import React from "react";
import { getBezierPath, getEdgeCenter } from "@xyflow/react";
import { HStack, Icon, Text } from "@chakra-ui/react";
import { RiDeleteBin5Line } from "react-icons/ri";
import { IoAddSharp } from "react-icons/io5";
import "./styles.scss";

const CustomEdge = ({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourceHandleId,
  style = {},
  markerEnd,
  onEdgeDelete,
  onAddNode,
}) => {
  const [edgePath] = getBezierPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
  });

  const [centerX, centerY] = getEdgeCenter({
    sourceX,
    sourceY,
    targetX,
    targetY,
  });

  let label = null;

  if (sourceHandleId === "true") label = "Yes";
  if (sourceHandleId === "false") label = "No";
  const isFalse = label === "No"

  return (
    <g className="zaplane-custom-edge">
      <path
        id={id}
        style={{ ...style, pointerEvents: "none" }}
        className="react-flow__edge-path"
        d={edgePath}
        markerEnd={markerEnd}
      />

      {/* YES / NO LABEL */}
      {label && (
        <foreignObject
          width={30}
          height={20}
          x={centerX - -15}
          y={centerY - 20}
          style={{ overflow: "visible" }}
        >
          <Text
            fontSize="12px"
            textAlign="center"
            bg={isFalse ? "#FEF2F2" : "#defce9"}
            color={isFalse ? "#ef4444":"#22c55e"}
            border={`1px solid ${isFalse? '#ef4444': '#22c55e'}`}
            margin='10px 0 0 0'
            boxShadow="sm"
            borderRadius='10px'
          >
            {label}
          </Text>
        </foreignObject>
      )}

      {/* Delete icon */}
      <foreignObject
        className="zaplane-edge-actions"
        width={24}
        height={24}
        x={centerX - 12}
        y={centerY - 20}
        style={{ overflow: "visible" }}
      >
        <HStack
          bg="var(--zaplane-background)"
          color="var(--zaplane-font-color)"
          p="7px"
          marginLeft="-20px"
          marginTop="4px"
          borderRadius="full"
          boxShadow="lg"
          cursor="pointer"
          pointerEvents="auto"
          height="30px"
          width="30px"
          onClick={() => onEdgeDelete(id)}
        >
          <Icon as={RiDeleteBin5Line} boxSize={4} cursor="pointer" />
        </HStack>
      </foreignObject>
      {
        !label && (<>
          {/* Add node icon */}
          <foreignObject
            width={24}
            height={24}
            x={centerX - 12}
            y={centerY + 4}
            className="zaplane-edge-actions"
            style={{ overflow: "visible" }}
          >
            <HStack
              bg="var(--zaplane-background)"
              color="var(--zaplane-font-color)"
              p="7px"
              m="-20px 0 0 13px"
              borderRadius="full"
              boxShadow="lg"
              cursor="pointer"
              pointerEvents="auto"
              height="30px"
              width="30px"
              onClick={() => onAddNode(id)}
            >
              <Icon as={IoAddSharp} boxSize={4} cursor="pointer" />
            </HStack>
          </foreignObject></>)
      }

    </g>
  );
};

export default CustomEdge;