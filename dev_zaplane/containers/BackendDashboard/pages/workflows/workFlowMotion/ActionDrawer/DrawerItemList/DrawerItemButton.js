import { Button, Flex, Icon, Text } from "@chakra-ui/react";
import { sprintf, __ } from "@wordpress/i18n";

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

        <Flex w="40px"
          h="40px"
          justifyContent='center'
          bg='var(--zaplane-background)'
          p="10px"
          borderRadius='4px'
          border="1px solid var(--zaplane-border-color)">
          <Text as={'span'} className={`zaplane-icon zaplane-icon--${icon}`} />
        </Flex>

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