import { Image, Text, Flex } from "@chakra-ui/react";
import { plugin_root_url } from "@ZAPUtils/helper";

const ZAPIcon = ({ icon, name }) => {
  const isSvg = icon?.endsWith(".svg");

  return (
    <Flex
      w="40px"
      h="40px"
      justifyContent="center"
      alignItems="center"
      bg="var(--zaplane-background)"
      p="8px"
      borderRadius="4px"
      border="1px solid var(--zaplane-border-color)"
    >
      {isSvg ? (
        <Image
          src={`${plugin_root_url}assets/images/icons/${icon}`}
          alt={name}
          boxSize="20px"
        />
      ) : (
        <Text
          as="span"
          m="0"
          className={`zaplane-icon zaplane-icon--${icon}`}
        />
      )}
    </Flex>
  );
};

export default ZAPIcon;