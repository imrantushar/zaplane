
import { Box, Button, HStack } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FiChevronDown, FiChevronRight, FiFolder } from "react-icons/fi";
const FolderNode = ({ node, depth, expandedMap, setExpandedMap, selectedId, onSelect }) => {
  const hasChildren = Array.isArray(node.children) && node.children.length > 0;
  const isExpanded = !!expandedMap[node.id];

  return (
    <Box pl={`${depth * 14}px`}>
      <HStack spacing={1} py={1}>
        {hasChildren ? (
          <Button
            variant="ghost"
            minW="18px"
            h="18px"
            p="0"
            onClick={() => setExpandedMap((prev) => ({ ...prev, [node.id]: !isExpanded }))}
          >
            {isExpanded ? <FiChevronDown /> : <FiChevronRight />}
          </Button>
        ) : (
          <Box w="18px" />
        )}
        <Button
          variant={Number(selectedId) === Number(node.id) ? "solid" : "ghost"}
          size="sm"
          leftIcon={<FiFolder />}
          onClick={() => onSelect(node.id)}
          justifyContent="flex-start"
          w="100%"
        >
          {node.title}
        </Button>
      </HStack>
      {hasChildren && isExpanded
        ? node.children.map((child) => (
            <FolderNode
              key={child.id}
              node={child}
              depth={depth + 1}
              expandedMap={expandedMap}
              setExpandedMap={setExpandedMap}
              selectedId={selectedId}
              onSelect={onSelect}
            />
          ))
        : null}
    </Box>
  );
};
export default FolderNode