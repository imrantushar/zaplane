

import {
    Button,
    CloseButton,
    Drawer,
    Icon,
    Portal,
    Text,
} from "@chakra-ui/react";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { FiArrowLeft } from "react-icons/fi";
import { IoIosArrowBack } from "react-icons/io";


const ZAPDrawer = ({
    trigger,
    children,
    title,
    footer,
    placement = "end",
    size = "md",
    closeOnOverlayClick = false,
    zIndex = 9999,
    onClose,
    open,
    arrowClose,
    isFullscreen = false,
    arrowOnClick,
    maxWidth
}) => {
    return (
        <Drawer.Root
            placement={placement}
            size={size}
            open={open}
            modal={false}
            closeOnInteractOutside={closeOnOverlayClick}
            closeOnOverlayClick={closeOnOverlayClick}
            onOpenChange={(details) => {
                if (!details.open) {
                    onClose?.();
                }
            }}

        >
            {closeOnOverlayClick && <Drawer.Backdrop />}
            <Drawer.Trigger asChild>
                {trigger}
            </Drawer.Trigger>

            <Portal>
                <Drawer.Positioner marginTop={isFullscreen ? '0' : "32px"}   height={isFullscreen ? '100%' : 'calc(100vh - 32px)'} zIndex={"9999"}
                    pointerEvents="none">
                    <Drawer.Content pointerEvents="auto" mr="15px" maxWidth={maxWidth}>
                        {title && (
                            <Drawer.Header>
                                {
                                    arrowClose && <Icon as={IoIosArrowBack} height='24px' width="24px" onClick={() => {
                                        arrowOnClick ? arrowOnClick() : onClose();
                                    }}
                                    >

                                    </Icon>
                                }
                                <Drawer.Title margin='0'>
                                    <ZAPLabel label={title} type={"bold"} />
                                </Drawer.Title>
                                <Drawer.CloseTrigger asChild>
                                    <CloseButton size="sm" />
                                </Drawer.CloseTrigger>
                            </Drawer.Header>
                        )}

                        <Drawer.Context>
                            {(store) => (
                                <>
                                    <Drawer.Body spaceY="3" overflowX='hidden'>
                                        {typeof children === "function"
                                            ? children(store)
                                            : children}
                                    </Drawer.Body>
                                    {footer && (
                                        <Drawer.Footer>
                                            {typeof footer === "function"
                                                ? footer(store)
                                                : footer}
                                        </Drawer.Footer>
                                    )}
                                </>
                            )}
                        </Drawer.Context>


                    </Drawer.Content>
                </Drawer.Positioner>
            </Portal>
        </Drawer.Root>
    );
};

export default ZAPDrawer;
