import React, { useState } from "react";
import { Button, Input, Flex } from "@chakra-ui/react";
import { useDispatch } from "react-redux";
import { __ } from "@wordpress/i18n";

import ImportJSONModal from "./ImportJSONModal";
import { importWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/ExportImport";
import { FiUpload } from "react-icons/fi";

const ImportWorkflow = () => {
    const dispatch = useDispatch();

    const [isOpen, setIsOpen] = useState(false);
    const [file, setFile] = useState(null);

    const handleImport = async () => {
        if (!file) {
            alert("Please select a JSON file");
            return;
        }

        try {
            const text = await file.text();
            const json = JSON.parse(text);

            await dispatch(importWorkflows(json));

            setIsOpen(false);
            setFile(null);
        } catch (err) {
            console.error("Import failed:", err);
            alert("Invalid JSON file");
        }
    };

    return (
        <>
            <Button variant='outline' onClick={() => setIsOpen(true)}>
                <FiUpload />
            </Button>

            <ImportJSONModal
                isOpen={isOpen}
                onClose={() => setIsOpen(false)}
                file={file}
                setFile={setFile}
                handleImport={handleImport}
            />
        </>
    );
};

export default ImportWorkflow;