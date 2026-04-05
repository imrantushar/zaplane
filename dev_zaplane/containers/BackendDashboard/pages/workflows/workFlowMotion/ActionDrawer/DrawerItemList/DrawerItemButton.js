import { Button, Flex, Text } from "@chakra-ui/react";
import { sprintf, __ } from "@wordpress/i18n";
import ZAPIcon from "@ZAPComponents/ZAPIcon";

const DrawerItemButton = ({ item, onClick, showType = true, arrowIcon, icon, }) => {

  return (
    <Button
      w="100%"
      p='4px'
      height='50px'
      borderRadius="4px"
      background="var(--zaplane-background)"
      color="var(--zaplane-font-color)"
      justifyContent="space-between"
      _hover={{ bg: "#F6F7F8" }}
      onClick={onClick}
    >
      <Flex gap="8px">
        <ZAPIcon icon={icon} name={item.name} />
        <Text className="zaplane-label" fontWeight="400" as="span" display='flex' alignItems="center">{sprintf(__("%s", "zaplane"), item.name)}</Text>
      </Flex>
      {arrowIcon && arrowIcon}
      {showType && item.type && (
        <Text fontSize="xs" className="zaplane-label" >
          {item.type === "tools" ? __('Tool', 'zaplane') : __('App', 'zaplane')}
        </Text>
      )}
    </Button>
  );
}
export default DrawerItemButton