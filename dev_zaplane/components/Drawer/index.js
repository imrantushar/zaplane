

import {
    CloseButton,
    Drawer,
    Portal,
    Text,
} from "@chakra-ui/react";


const ZAPDrawer = ({
    trigger,
    children,
    title,
    placement = "end",
    size = "md",
    closeOnOverlayClick = true,
    zIndex = 9999,
    onClose,
    open
}) => {
    return (
        <Drawer.Root
            placement={placement}
            size={size}
             open={open}  
              modal={false}
             closeOnInteractOutside={false}
            closeOnOverlayClick={closeOnOverlayClick}
            onOpenChange={(details) => {
                if (!details.open) {
                    onClose?.();
                }
            }}
           
        >
            <Drawer.Trigger asChild>
                {trigger}
            </Drawer.Trigger>

            <Portal>
                <Drawer.Positioner  marginTop='32px' zIndex={"99999999"}
                 pointerEvents="none">
                    <Drawer.Content  pointerEvents="auto">
                        {title && (
                            <Drawer.Header>
                                <Drawer.Title margin='0' >{title}</Drawer.Title>
                                <Drawer.CloseTrigger asChild>
                                    <CloseButton size="sm" />
                                </Drawer.CloseTrigger>
                            </Drawer.Header>
                        )}

                        <Drawer.Context>
                            {(store) => (
                                <Drawer.Body spaceY="3">
                                    {typeof children === "function"
                                        ? children(store)
                                        : children}
                                </Drawer.Body>
                            )}
                        </Drawer.Context>

                    </Drawer.Content>
                </Drawer.Positioner>
            </Portal>
        </Drawer.Root>
    );
};

export default ZAPDrawer;
