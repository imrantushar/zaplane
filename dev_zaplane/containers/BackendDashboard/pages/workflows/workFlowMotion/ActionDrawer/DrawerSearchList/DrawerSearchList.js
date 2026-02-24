import { VStack, Button, Text } from "@chakra-ui/react";
import { sprintf, __ } from "@wordpress/i18n";

const DrawerSearchList=({ searchList, setMode, setSelectedItem, setSearch })=> {
  if (!searchList?.length) return null;

  return (
    <VStack spacing={2} align="stretch">
      {searchList.map(item => (
        <Button
          key={`${item.type}-${item.id}`}
          justifyContent="space-between"
          onClick={() => {
            setMode(item.type);
            setSelectedItem(item);
            setSearch("");
          }}
          background="var(--zaplane-background)"
          _hover={{ bg: "var(--zaplane-body-background)" }}
        >
          <Text className="zaplane-label">{sprintf(__("%s", "zaplane"), item.name)}</Text>
          <Text fontSize="xs" className="zaplane-label">
            {item.type === 'tools' ? __('Tool', 'zaplane') : __('App', 'zaplane')}
          </Text>
        </Button>
      ))}
    </VStack>
  );
}
export default DrawerSearchList