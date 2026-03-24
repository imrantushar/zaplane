import React, { useState } from "react";
import { Box, Button, Input, Text, Flex } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";

const ZAPFileUpload = ({ onFileSelect, label = "Upload JSON File" }) => {
  const [fileName, setFileName] = useState("");

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Optional: validate file type
    if (file.type !== "application/json" && !file.name.endsWith(".json")) {
      alert("Please upload a valid JSON file");
      return;
    }

    setFileName(file.name);
    onFileSelect(file);
  };

  return (
    <Box>
      <Text mb={2}>{label}</Text>
      <Flex align="center" gap={4}>
        <Input
          type="file"
          accept=".json,application/json"
          onChange={handleFileChange}
          display="none"
          id="zap-file-upload"
        />
        <label htmlFor="zap-file-upload">
          <Button as="span" colorScheme="blue">
            Choose File
          </Button>
        </label>
        <Text>{fileName || "No file selected"}</Text>
      </Flex>
    </Box>
  );
};

export default ZAPFileUpload;