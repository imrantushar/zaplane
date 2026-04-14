import { Flex, Text, Image } from "@chakra-ui/react";
import { plugin_root_url } from "@ZAPUtils/helper";

const ZAPIconGroup = ({ icons = [], maxVisible = 3 }) => {
    const safeIcons = Array.isArray(icons) ? icons : [];
    const visibleIcons = safeIcons.slice(0, maxVisible);
    const remaining = Math.max(0, safeIcons.length - maxVisible);
    const totalItems = visibleIcons.length + (remaining > 0 ? 1 : 0);
    return (
        <Flex
            border="1px solid var(--zaplane-border-color)"
            borderRadius="6px"
            overflow="hidden"
            display="inline-flex"
        >
            {visibleIcons.map((icon, index) => {
                const isSvg = icon?.endsWith(".svg");
                const isLast = index === totalItems - 1;
                return (
                    <Flex
                        key={index}
                        h="32px"
                        w='36px'
                        justifyContent="center"
                        alignItems="center"
                        bg="var(--zaplane-background)"
                        p="4px 8px"
                        borderRight={
                            isLast ? "none" : "1px solid var(--zaplane-border-color)"
                        }
                    >
                        {isSvg ? (
                            <Image
                                src={`${plugin_root_url}assets/images/icons/${icon}`}
                                alt={icon}
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
            })}

            {remaining > 0 && (
                <Flex
                   w='36px'
                    h="32px"
                    justifyContent="center"
                    alignItems="center"
                    bg="var(--zaplane-background)"
                >
                    <Text
                        className="zapane-title"
                        fontWeight="400"
                        color="var(--zaplane-text-muted, #888)"
                        m="0"
                    >
                        +{remaining}
                    </Text>
                </Flex>
            )}
        </Flex>
    );
};

export default ZAPIconGroup;