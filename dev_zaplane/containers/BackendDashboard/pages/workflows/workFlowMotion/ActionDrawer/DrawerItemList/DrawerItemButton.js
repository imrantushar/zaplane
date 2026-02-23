import { Button, Text } from "@chakra-ui/react";
import { sprintf, __ } from "@wordpress/i18n";

const  DrawerItemButton=({ item, onClick, showType = true, icon })=> {
  return (
    <Button
      w="100%"
      background="var(--zaplane-background)"
      color="var(--zaplane-font-color)"
      justifyContent="space-between"
      _hover={{ bg: "var(--zaplane-body-background)", "& svg": { transform: "translateX(4px)" } }}
      onClick={onClick}
    >
      <span>{sprintf(__("%s", "zaplane"), item.name)}</span>
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