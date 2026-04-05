import { Image, Text, Flex } from "@chakra-ui/react";
import { plugin_root_url } from "@ZAPUtils/helper";

const ZAPIcon = ({ icon, name,isAction=false }) => {
  const isSvg = icon?.endsWith(".svg");

  return (
    <Flex
      w={isAction ? '40px': "32px"}
      h={isAction ? '40px': "32px"}
      justifyContent="center"
      alignItems="center"
      bg={!isAction && "#F6F7F8"}
      p='8px'
      borderRadius="4px"
      border="1px solid var(--zaplane-border-color)"
    >
      {isSvg ? (
        <Image
          src={`${plugin_root_url}assets/images/icons/${icon}`}
          alt={name}
          boxSize={isAction ? '20px': "16px"}
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