import {
    Drawer,
    Portal,
    Button,
    CloseButton,
    VStack,
    Text,
} from "@chakra-ui/react";
import { useState } from "react";
const APPS = [
    {
        id: "openai",
        name: "OpenAI",
        actions: [
            { id: "chat", name: "Create Chat Completion" },
            { id: "image", name: "Generate Image" },
        ],
    },
    {
        id: "http",
        name: "HTTP",
        actions: [
            { id: "get", name: "GET Request" },
            { id: "post", name: "POST Request" },
        ],
    },
    {
        id: "wordpress",
        name: "WordPress",
        actions: [
            { id: "post", name: "Create Post" },
            { id: "user", name: "Create User" },
        ],
    },
];

const ActionDrawer = ({
    open,
    onClose,
    onCreateCondition,
    node,
    edge,
    onSelectAction, // 🔥 edge data save callback
}) => {
    const [step, setStep] = useState("root"); // root | apps | actions | tools
    const [selectedApp, setSelectedApp] = useState(null);

    const closeAll = () => {
        setStep("root");
        setSelectedApp(null);
        onClose();
    };

    return (
        <Drawer.Root open={open} onOpenChange={(e) => !e.open && closeAll()}>
            <Portal>
                <Drawer.Backdrop />
                <Drawer.Positioner>
                    <Drawer.Content>
                        <Drawer.Header>
                            <Drawer.Title>
                                {step === "root" && "Select App & Action"}
                                {step === "apps" && "Select App"}
                                {step === "actions" && "Select Action"}
                                {step === "tools" && "Tools"}
                            </Drawer.Title>

                            <Drawer.CloseTrigger asChild>
                                <CloseButton />
                            </Drawer.CloseTrigger>
                        </Drawer.Header>

                        <Drawer.Body>
                            {!edge && node && (
                                <>
                                    <Text><b>ID:</b> {node.id}</Text>
                                    <Text><b>Label:</b> {node.data?.label}</Text>
                                </>
                            )}
                            {edge && step === "root" && (
                                <VStack align="stretch">
                                    <Button onClick={() => setStep("apps")}>
                                        Apps
                                    </Button>
                                    <Button onClick={() => setStep("tools")}>
                                        Tools
                                    </Button>
                                </VStack>
                            )}

                            {step === "apps" && (
                                <VStack align="stretch">
                                    {APPS.map((app) => (
                                        <Button
                                            key={app.id}
                                            justifyContent="space-between"
                                            onClick={() => {
                                                setSelectedApp(app);
                                                setStep("actions");
                                            }}
                                        >
                                            {app.name} →
                                        </Button>
                                    ))}
                                </VStack>
                            )}
                            {step === "actions" && selectedApp && (
                                <VStack align="stretch">
                                    <Text fontWeight="bold">
                                        {selectedApp.name} Actions
                                    </Text>

                                    {selectedApp.actions.map((action) => (
                                        <Button
                                            key={action.id}
                                            onClick={() => {
                                                onSelectAction({
                                                    appId: selectedApp.id,
                                                    appName: selectedApp.name,
                                                    actionId: action.id,
                                                    actionName: action.name,
                                                });
                                                closeAll();
                                            }}
                                        >
                                            {action.name}
                                        </Button>
                                    ))}

                                    <Button
                                        variant="ghost"
                                        onClick={() => setStep("apps")}
                                    >
                                        ← Back
                                    </Button>
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
                            )}
                        </Drawer.Body>
                    </Drawer.Content>
                </Drawer.Positioner>
            </Portal>
        </Drawer.Root>
    );
};

export default ActionDrawer;
