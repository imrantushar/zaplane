import React from "react";
import { Button, FileUpload, Flex } from "@chakra-ui/react";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { HiUpload } from "react-icons/hi";
import { primaryBtn } from "../../../../../../../assets/scss/chakra/recipe";
import { __ } from "@wordpress/i18n";

export default function ImportJSONModal({ isOpen, onClose, file, setFile, handleImport }) {
  return (
    <WPModal
      title={__("Import JSON File", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
      size="medium"
    >
      <FileUpload.Root
        maxFiles={1}
        accept={{ "application/json": [".json"] }}
        maxFileSize={1024 * 1024 * 2}
        allowDrop
        preventDocumentDrop
        onFileChange={(details) => {
          const selected = details.acceptedFiles?.[0];
          setFile(selected || null);
        }}
        onFileReject={() => {
          alert("Invalid file. Only JSON under 2MB allowed.");
        }}
        validate={(file) => {
          if (!file.name.endsWith(".json")) {
            return [{ message: "Only JSON files allowed" }];
          }
          return null;
        }}
      >
        <FileUpload.HiddenInput />
        <FileUpload.Trigger asChild>
          <Button variant="outline" width='100%'>
            <HiUpload /> {__("Select JSON File", "zaplane")}
          </Button>
        </FileUpload.Trigger>
        <FileUpload.List showSize clearable />
      </FileUpload.Root>
      <Flex justify="flex-end" mt="24px" gap="12px">
        <Button mt="16px" variant={'outline'}  onClick={onClose}>
          {__("cancel", "zaplane")}
        </Button>
        <Button mt="16px" {...primaryBtn} onClick={handleImport}>
          {__("Import", "zaplane")}
        </Button>
      </Flex>

    </WPModal>
  );
}