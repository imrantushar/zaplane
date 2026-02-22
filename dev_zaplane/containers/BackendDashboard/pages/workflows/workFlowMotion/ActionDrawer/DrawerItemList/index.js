import { VStack, Button, Box, Flex } from "@chakra-ui/react";
import DrawerItemButton from "./DrawerItemButton";
import { __ } from "@wordpress/i18n";
import { FaWordpress } from "react-icons/fa6";
import { secondPrimaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";

const DrawerItemList = ({ list, setSelectedItem, setMode }) => {
  return (
    <VStack alignItems="left" overflow="hidden" pt='16px'>
      {list.map(item => (
        <Flex>
          <Box
            w="40px"
            h="40px"
            p="10px"
            justifyContent="center"
            alignItems="center"
            gap="10px"
            bg='var(--zaplane-body-background)'>
            <FaWordpress />
          </Box>
          <DrawerItemButton
            key={item.id}
            item={item}
            onClick={() => setSelectedItem(item)}
          />
        </Flex>

      ))}
    </VStack>
  );
}
export default DrawerItemList