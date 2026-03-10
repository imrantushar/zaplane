import { ActionBar, Portal, Button, CloseButton } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";

const ZAPActionBar = ({
  selection = [],
  onDelete,
  onClose,
  deleteLabel = __("Delete", "zaplane"),
}) => {

  const hasSelection = selection?.length > 0;

  return (
    <ActionBar.Root open={hasSelection}>
      <Portal>
        <ActionBar.Positioner>
          <ActionBar.Content>

            <ActionBar.SelectionTrigger>
              {selection.length} {__("items selected", "zaplane")}
            </ActionBar.SelectionTrigger>

            <ActionBar.Separator />

            <Button
              variant="outline"
              size="sm"
              colorScheme="red"
              onClick={onDelete}
            >
              {deleteLabel}
            </Button>

            <ActionBar.Separator />

            <ActionBar.CloseTrigger asChild>
              <CloseButton
                size="sm"
                onClick={onClose}
              />
            </ActionBar.CloseTrigger>

          </ActionBar.Content>
        </ActionBar.Positioner>
      </Portal>
    </ActionBar.Root>
  );
};

export default ZAPActionBar;