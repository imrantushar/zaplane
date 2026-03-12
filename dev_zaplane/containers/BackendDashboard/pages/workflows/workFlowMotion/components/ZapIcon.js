import { Image, Text } from "@chakra-ui/react";
import { plugin_root_url } from "@ZAPUtils/helper";

const ZapIcon = ({ icon, name }) => {
  const isSvg = icon.endsWith(".svg")
  if (isSvg) {
    return (
      <Image
        src={`${plugin_root_url}assets/icons/${icon}.svg`}
        boxSize={20}
        alt={name}
      />
    );
  }

  return <Text as="span" className={`zaplane-icon zaplane-icon--${icon}`} />;
};

export default ZapIcon;