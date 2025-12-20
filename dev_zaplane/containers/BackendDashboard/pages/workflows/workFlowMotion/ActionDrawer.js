import {
    Drawer,
    Portal,
    Button,
    CloseButton,
    VStack,
    Text,
} from "@chakra-ui/react";
import { useState } from "react";

const ActionDrawer = ({ open, onClose, onCreateCondition, node, edge }) => {
    const [step, setStep] = useState("root"); // root | tools

    const closeAll = () => {
        setStep("root");
        onClose();
    };
    if (edge) {
        console.log("Edge clicked:", edge);
    }
    if (node) {
        console.log("Node clicked:", node);
    }

    return (
        <Drawer.Root open={open} onOpenChange={(e) => !e.open && closeAll()}>
            <Portal>
                <Drawer.Backdrop />
                <Drawer.Positioner>
                    <Drawer.Content>
                        <Drawer.Header>
                            <Drawer.Title>
                                {step === "root" ? "Select App & Action" : "Tools"}
                            </Drawer.Title>
                            <Drawer.CloseTrigger asChild>
                                <CloseButton />
                            </Drawer.CloseTrigger>
                        </Drawer.Header>

                        <Drawer.Body>
                            {!edge ? <>
                                <Text><b>ID:</b> {node?.id}</Text>
                                <Text><b>Label:</b> {node?.data?.label}</Text>
                                <Text><b>Action:</b> {node?.data?.action}</Text>

                                {node?.data?.conditions && (
                                    <>
                                        <Text mt={3}><b>Conditions:</b></Text>
                                        {node?.data?.conditions.map((c) => (
                                            <Text key={c.id}>• {c.title}</Text>
                                        ))}
                                    </>
                                )}
                            </> : <>  {step === "root" && (
                                <VStack align="stretch">
                                    <Button onClick={() => setStep("apps")}>Apps</Button>
                                    <Button onClick={() => setStep("tools")}>Tools</Button>
                                </VStack>
                            )}

                                {step === "tools" && (
                                    <VStack align="stretch">
                                        <Button
                                            onClick={() => {
                                                onCreateCondition();
                                                closeAll();
                                            }}
                                        >
                                            Condition
                                        </Button>

                                        <Button disabled>Router</Button>
                                    </VStack>
                                )}</>}

                        </Drawer.Body>
                    </Drawer.Content>
                </Drawer.Positioner>
            </Portal>
        </Drawer.Root>
    );
};

export default ActionDrawer;
