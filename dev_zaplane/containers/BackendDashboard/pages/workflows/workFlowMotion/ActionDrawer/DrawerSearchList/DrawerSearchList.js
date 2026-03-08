import { VStack, Button, Text, Flex, Icon } from "@chakra-ui/react";
import { sprintf, __ } from "@wordpress/i18n";
import { ReactComponent as SlackIcon } from "./slack.svg";

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
          background="var(--zaplane-background)"
          _hover={{ bg: "#F6F7F8" }}
        >
          <Flex gap='12px' alignItems='center'>
            <Flex w="40px"
              h="40px"
              bg='var(--zaplane-background)'
              p="10px"
              borderRadius='4px'
              border="1px solid var(--zaplane-border-color)">
              <Text as={"span"} m='0'className={`zaplan-icon ${searchList?.icon}`}></Text>
            </Flex>
            <Text className="zaplane-label" >{sprintf(__("%s", "zaplane"), item.name)}</Text>
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