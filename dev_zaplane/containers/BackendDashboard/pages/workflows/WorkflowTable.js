import { useMemo, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import { Text, Box, Icon } from "@chakra-ui/react";
import Select from "react-select";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";

import ListTable from "@ZAPComponents/ListTable";
import OptionMenu from "@ZAPComponents/OptionMenu";

import { FiEdit, FiTrash2 } from "react-icons/fi";
import { route_path } from "@ZAPUtils/helper";
import { statusOptions } from "./helper";

import {
  deleteWorkFlow,
  updateWorkFlowStatus,
} from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import StatusOptions from "@ZAPComponents/StatusOptions";

const WorkflowTable = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();

  const { allWorkFlows = [], isLoading } = useSelector(
    (state) => state.workflows
  );

  const columns = [
    {
      name: (
        <Text className="zaplane-label">
          {__("Title", "zaplane")}
        </Text>
      ),
      cell: (row) => (
        <Text
          className="zaplane-label"
          fontWeight="500"
          cursor="pointer"
          onClick={() =>
            navigate(
              `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`
            )
          }
        >
          {row.title}
        </Text>
      ),
      columnWidth: "100px",
      textAlign: 'start'
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("Created At", "zaplane")}
        </Text>
      ),
      cell: (row) => (
        <Text fontSize="sm">{row.created_at}</Text>
      ),
      columnWidth: "160px",
      textAlign: "center",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("Updated At", "zaplane")}
        </Text>
      ),
      cell: (row) => (
        <Text className="zaplane-label">{row.updated_at}</Text>
      ),
      columnWidth: "160px",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("Status", "zaplane")}
        </Text>
      ),
      cell: (row) => {
        const handleStatusChange = (row, newStatus) => {
          if (!row?.id || !newStatus) return;
          dispatch(updateWorkFlowStatus({ id: row.id, status: newStatus }));
        };

        return (
          <StatusOptions
            value={row?.status}
            options={{
              items: [...statusOptions],
            }}
            onChangeHandler={(newStatus) => handleStatusChange(row, newStatus)}
          />
        )
      },
      columnWidth: "170px",
      textAlign: "center",
    },
    {
      name: (
        <Text className="zaplane-label">
          {__("Action", "zaplane")}
        </Text>
      ),
      cell: (row) => (
        <OptionMenu
          options={[
            {
              label: __('Edit', 'zaplane'),
              icon: <Icon as={FiEdit} />,
              type: 'button',
              onClick: () =>
                navigate(
                  `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`
                ),
            },
            {
              label: __('Delete', 'zaplane'),
              suffix: 'trash',
              icon: <Icon as={FiTrash2} />,
              type: 'button',
              onClick: (id) => {
                if (window.confirm(
                  __("Are you sure you want to permanently delete ?", "zaplane")
                )) {
                  dispatch(deleteWorkFlow(row.id));
                }
              },

              hasBorder: false,
            },
          ]}

        />
      ),
      columnWidth: "90px",
      textAlign: "center",
    },
  ]


  return (
    <ListTable
      columns={columns}
      data={Array.isArray(allWorkFlows) ? allWorkFlows : []}
      isRowSelectable={true}
      showSubHeader={false}
      showColumnFilter={false}
      showPagination={false}
      noDataText={__("No workflows found", "zaplane")}
      totalItems={allWorkFlows?.length || 0}
      dataFetchingStatus={isLoading}
      suffix="workflow-table"
    />
  );
};

export default WorkflowTable;
