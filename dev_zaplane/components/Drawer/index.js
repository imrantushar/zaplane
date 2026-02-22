

import {
    Button,
    CloseButton,
    Drawer,
    Portal,
    Text,
} from "@chakra-ui/react";
import { FiArrowLeft } from "react-icons/fi";


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
    arrowOnClick
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
                <Drawer.Positioner marginTop={isFullscreen ? '0' : "32px"} height={isFullscreen ? '100%' : 'calc(100vh - 32px)'} zIndex={"9999"}
                    pointerEvents="none">
                    <Drawer.Content pointerEvents="auto">
                        {title && (
                            <Drawer.Header>
                                {
                                    arrowClose && <Button height="30px" width="20px" variant="outline"
                                        onClick={() => {
                                            arrowOnClick ? arrowOnClick() : onClose?.();
                                        }} >
                                        <FiArrowLeft />
                                    </Button>
                                }
                                <Drawer.Title margin='0' >{title}</Drawer.Title>
                                <Drawer.CloseTrigger asChild>
                                    <CloseButton size="sm" />
                                </Drawer.CloseTrigger>
                            </Drawer.Header>
                        )}

                        <Drawer.Context>
                            {(store) => (
                                <>
                                    <Drawer.Body spaceY="3">
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
