import { Button, Flex, Text } from "@chakra-ui/react";
import { sprintf, __ } from "@wordpress/i18n";

const DrawerItemButton = ({ item, onClick, showType = true, icon,  toolIcon: ToolIcon, }) => {
  return (
    <Button
      w="100%"
      background="var(--zaplane-background)"
      color="var(--zaplane-font-color)"
      justifyContent="space-between"
      _hover={{ bg: "var(--zaplane-body-background)"}}
      onClick={onClick}
    >
      <Flex gap="8px">
        {ToolIcon && <ToolIcon />}
        <Text as="span" m='0'>{sprintf(__("%s", "zaplane"), item.name)}</Text>
      </Flex>
      {icon && icon}
      {showType && item.type && (
        <Text fontSize="xs" className="zaplane-label">
          {item.type === "tools" ? __('Tool', 'zaplane') : __('App', 'zaplane')}
        </Text>
      )}
    </Button>
  );
}
export default DrawerItemButton