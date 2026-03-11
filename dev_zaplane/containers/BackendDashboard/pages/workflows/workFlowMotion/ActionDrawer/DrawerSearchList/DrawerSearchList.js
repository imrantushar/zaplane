import { VStack, Button, Text, Flex } from "@chakra-ui/react";
import { sprintf, __ } from "@wordpress/i18n";


const DrawerSearchList = ({ searchList, setMode, setSelectedItem, setSearch }) => {
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
          height='50px'
          p="4px"
          color="var(--zaplane-font-color)"
          background="var(--zaplane-background)"
          _hover={{ bg: "#F6F7F8" }}
        >
          <Flex gap='12px' alignItems='center'>
            <Flex w="40px"
              h="40px"
              p="10px"
              borderRadius='4px'
              justifyContent='center'
              border="1px solid var(--zaplane-border-color)">
              <Text as={'span'} className={`zaplane-icon zaplane-icon--${item?.icon}`} />
            </Flex>
            <Text className="zaplane-label" fontWeight='400' >{sprintf(__("%s", "zaplane"), item.name)}</Text>
          </Flex>

          <Text fontSize="xs" className="zaplane-label">
            {item.type === 'tools' ? __('Tool', 'zaplane') : __('App', 'zaplane')}
          </Text>
        </Button>
      ))}
    </VStack>
  );
}
export default DrawerSearchList