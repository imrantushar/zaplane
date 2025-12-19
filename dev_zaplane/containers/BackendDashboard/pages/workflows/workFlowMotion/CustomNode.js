import { useState } from "react";
import { Handle, Position, NodeToolbar } from "@xyflow/react";
import { Box, Text, HStack, Icon, Badge, Button } from "@chakra-ui/react";
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
                isVisible={true}
                position={Position.Top}
                align="start"
                offset={10}
            >
                <HStack
                >
                    <Text
                        borderRadius="full"
                        p='4px 8px'
                        fontWeight="medium"
                        background='#E6F4FF'
                        margin={0}

                    >
                        {data.action || 'Action'}
                    </Text>
                    <Text
                        borderRadius="full"
                        fontSize="sm"
                        fontWeight="medium"
                        margin={0}
                    >
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
                    onMouseEnter={() => setHovered(true)}
                    onMouseLeave={() => setHovered(false)}
                >
                    <Icon as={RiDeleteBin7Line} boxSize={4} />
                    {!data?.action && (
                    <Icon as={FaRegCopy} boxSize={4} />
                    )}
                    
                </HStack>
            </NodeToolbar>

            {/* 🔹 NODE BODY */}
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
                {!data?.action && (<Handle
                    type="target"
                    position={Position.Left}
                    style={{
                        width: 10,
                        height: 10,
                        borderRadius: "50%",
                        background: "#3182ce",
                        border: "2px solid white",
                    }}
                />)}


                <Text m={0} fontSize="sm" fontWeight="medium">
                    {data.label}
                </Text>

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
            </Box>
        </Box>
    );
}
