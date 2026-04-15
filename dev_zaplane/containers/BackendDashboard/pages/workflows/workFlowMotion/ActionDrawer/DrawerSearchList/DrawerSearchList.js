import { VStack, Button, Text, Flex} from "@chakra-ui/react";
import { sprintf, __ } from "@wordpress/i18n";
import ZAPIcon from "@ZAPComponents/ZAPIcon";


const DrawerSearchList = ({ searchList, setMode, setSelectedItem, setSearch }) => {
  if (!searchList?.length) return <Text className="zaplane-label" textAlign='center'>{__("No results found", "zaplane")}</Text>;
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
              <ZAPIcon icon={item.icon}  name={item.name} isAction={true} />
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