import { VStack, Button } from "@chakra-ui/react";
import DrawerItemButton from "./DrawerItemButton";
import { __ } from "@wordpress/i18n";

const DrawerItemList=({ list, setSelectedItem, setMode }) =>{
  return (
    <VStack>
      {list.map(item => (
        <DrawerItemButton
          key={item.id}
          item={item}
          onClick={() => setSelectedItem(item)}
        />
      ))}

      <Button size="sm" variant="ghost" onClick={() => setMode(null)}>
        {__('Back', 'zaplane')}
      </Button>
    </VStack>
  );
}
export default DrawerItemList