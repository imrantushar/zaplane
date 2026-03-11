import { Image, Text } from "@chakra-ui/react";
import { plugin_root_url } from "@ZAPUtils/helper";

const ZapIcon = ({ icon, svg=false, name }) => {

  if (svg) {
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