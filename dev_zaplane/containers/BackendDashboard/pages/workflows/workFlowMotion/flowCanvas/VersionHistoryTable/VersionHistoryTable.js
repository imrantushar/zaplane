import {
  Table,
  Badge,
  Button,
  HStack,
  Text,
  Flex,
  Spinner,
} from "@chakra-ui/react";
import ZAPLoading from "@ZAPComponents/Loading";
import ZAPTable from "@ZAPComponents/Table";
import { getPreviewOldVersion, versionActive } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { CheckCircle, Eye } from "lucide-react";
import { useDispatch, useSelector } from "react-redux";

const VersionHistoryTable = ({
  versions = [],
  id,
}) => {
  const dispatch = useDispatch()
  const { isLoading } = useSelector((state) => state.workflows);
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

  if (isLoading) {
    return (
      <ZAPLoading />

    );
  }
  if (!versions.length) {
    return (
      <Flex align="center" justify="center" h="100%">
        <Text>Right now Have no Version</Text>
      </Flex>

    );
  }

  return (
    <ZAPTable
      data={versions}         
      rowKey="id"             
      variant="line"          
      size="sm"                
      caption="Version History" 
      columns={[
        {
          label: "ID",
          key: "id",
          render: (row) => <Text fontWeight="medium">#{row.id}</Text>,
        },
        {
          label: "Graph Hash",
          key: "graph_hash",
          render: (row) => (
            <Text fontSize="sm">{row.graph_hash.slice(0, 12)}…</Text>
          ),
        },
        {
          label: "Status",
          key: "is_active",
          render: (row) => (
            <Badge
              px="2"
              py="0.5"
              rounded="md"
              fontSize="xs"
              {...statusStyle(row.is_active)}
            >
              {row.is_active === "1" ? "Active" : "Inactive"}
            </Badge>
          ),
        },
        {
          label: "Created At",
          key: "created_at",
          render: (row) => <Text>{row.created_at}</Text>,
        },
      ]}
      actionsRenderer={(row) => (
        <HStack justify="flex-end" spacing={1}>
          {row.is_active !== "1" && (
            <Button
              size="xs"
              variant="outline"
              onClick={() => dispatch(versionActive({ id, versionID: row.id }))}
            >
              <CheckCircle size={14} />
            </Button>
          )}
          <Button
            size="xs"
            variant="ghost"
            onClick={() => dispatch(getPreviewOldVersion({ id, versionID: row.id }))}
          >
            <Eye size={14} />
          </Button>
        </HStack>
      )}
    />

  );
};

export default VersionHistoryTable;
