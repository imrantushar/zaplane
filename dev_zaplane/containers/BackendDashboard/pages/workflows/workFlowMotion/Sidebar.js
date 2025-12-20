import React, { useState } from "react";
import {
  Box,
  Flex,
  Text,
  Input,
  Button,
  VStack,
  HStack,
} from "@chakra-ui/react";

const items = [
  { id: "123form", label: "123FormBuilder", icon: "🧾" },
  { id: "academy", label: "Academy LMS", icon: "🎓" },
  { id: "acpt", label: "ACPT", icon: "📦" },
  { id: "activecampaign", label: "ActiveCampaign", icon: "📨" },
  { id: "activepieces", label: "ActivePieces", icon: "🧩" },
  { id: "affiliatewp", label: "Affiliate WP", icon: "🔗" },
  { id: "aidaform", label: "AidaForm", icon: "📝" },
  { id: "airtable", label: "Airtable", icon: "🗂️" },
  { id: "albato", label: "Albato", icon: "🔄" },
  { id: "amelia", label: "Amelia Booking", icon: "📅" },
];

export default function Sidebar() {
  const [search, setSearch] = useState("");

  const onDragStart = (event, nodeType) => {
    event.dataTransfer.setData("application/reactflow", nodeType);
    event.dataTransfer.effectAllowed = "move";
  };

  const filteredItems = items.filter((item) =>
    item.label.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <Box
      w="280px"
      h="100vh"
      borderRight="1px solid"
      borderColor="gray.200"
      bg="gray.50"
      p={3}
    >
      <Text fontWeight="semibold" mb={3} margin={0}>
        Untitled Flow
      </Text>
      <Input
        placeholder="Search"
        size="sm"
        mb={3}
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        bg="white"
      />
      <HStack mb={3} spacing={2}>
        <Button size="sm" colorScheme="blue" variant="solid">
          Apps
        </Button>
        <Button size="sm" variant="ghost">
          Tools
        </Button>
      </HStack>
      <VStack spacing={3} align="stretch" overflowY="auto">
        {filteredItems.map((item) => (
          <Flex
            key={item.id}
            draggable
            onDragStart={(e) => onDragStart(e, item.label)}
            align="center"
            gap={3}
            p={3}
            bg="white"
            border="1px solid"
            borderColor="gray.200"
            borderRadius="lg"
            cursor="grab"
            _hover={{ bg: "gray.100" }}
          >
            <Box fontSize="xl">{item.icon}</Box>
            <Text margin={0} fontSize="sm" fontWeight="medium">
              {item.label}
            </Text>
          </Flex>
        ))}
      </VStack>
    </Box>
  );
}
