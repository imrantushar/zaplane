import {
  Table,
  Badge,
  Button,
  HStack,
  Text,
} from "@chakra-ui/react";
import { CheckCircle, Eye } from "lucide-react";
import { useDispatch } from "react-redux";

const VersionHistoryTable = ({
  versions = [],
}) => {
    const dispatch=useDispatch()
  const statusStyle = (isActive) => {
    if (isActive === "1") {
      return {
        color: "green.600",
        bg: "green.50",
      };
    }
    return {
      color: "gray.600",
      bg: "gray.100",
    };
  };

  return (
    <Table.Root size="sm" variant="line">
      <Table.Caption>Version History</Table.Caption>

      <Table.Header>
        <Table.Row>
          <Table.ColumnHeader>ID</Table.ColumnHeader>
          <Table.ColumnHeader>Graph Hash</Table.ColumnHeader>
          <Table.ColumnHeader>Status</Table.ColumnHeader>
          <Table.ColumnHeader>Created At</Table.ColumnHeader>
          <Table.ColumnHeader textAlign="right">
            Actions
          </Table.ColumnHeader>
        </Table.Row>
      </Table.Header>

      <Table.Body>
        {versions.map((version) => (
          <Table.Row key={version.id}>
            <Table.Cell>
              <Text fontWeight="medium">#{version.id}</Text>
            </Table.Cell>

            <Table.Cell>
              <Text fontSize="sm">
                {version.graph_hash.slice(0, 12)}…
              </Text>
            </Table.Cell>

            <Table.Cell>
              <Badge
                px="2"
                py="0.5"
                rounded="md"
                fontSize="xs"
                {...statusStyle(version.is_active)}
              >
                {version.is_active === "1" ? "Active" : "Inactive"}
              </Badge>
            </Table.Cell>

            <Table.Cell>{version.created_at}</Table.Cell>

            <Table.Cell textAlign="right">
              <HStack justify="flex-end">
                {version.is_active !== "1" && (
                  <Button
                    size="xs"
                    variant="outline"

                   
                  >
                    <CheckCircle size={14} />
                  </Button>
                )}

                <Button
                  size="xs"
                  variant="ghost"
                  onClick={()=>dispatch(getPreviewOldVersion())}
                 
                >
                  <Eye size={14} />
                </Button>
              </HStack>
            </Table.Cell>
          </Table.Row>
        ))}
      </Table.Body>
    </Table.Root>
  );
};

export default VersionHistoryTable;
