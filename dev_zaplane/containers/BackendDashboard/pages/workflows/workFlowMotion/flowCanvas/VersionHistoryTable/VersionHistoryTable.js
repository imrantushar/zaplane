import {
  Table,
  Badge,
  Button,
  HStack,
  Text,
  Flex,
  Spinner,
} from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
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
  return (
    <ZAPTable
      data={versions}
      rowKey="id"
      variant="line"
      isLoading={isLoading}
      noDataText={"Right now Have no Version"}
      size="sm"
      columns={[
        {
          label: "ID",
          key: "id",
          textAlign: "center",
          render: (row) => <Text className="zaplane-label" fontWeight="medium">{__(row.id, "zaplane")}</Text>,
        },
        {
          label: "Graph Hash",
          key: "graph_hash",
          render: (row) => (
            <Text fontSize="sm" >{row.graph_hash.slice(0, 12)}</Text>
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
              color={row.is_active  ? "#16A34A" : "#4B5563"}
              bg={row.is_active  ? "#DCFCE7" : "#F3F4F6"}

            >
              {row.is_active ? "Active" : "Inactive"}
            </Badge>
          ),
        },
        {
          label: "Created At",
          key: "created_at",
          render: (row) => <Text>{__(row.created_at, "zaplane")}</Text>,
        },
      ]}
      actionsRenderer={(row) => (
        <HStack justify="flex-end" spacing={1}>
          {!row.is_active && (
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
