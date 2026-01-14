import React from "react";
import {
  Table,
  Text,
  HStack,
  Button,
  Badge,
} from "@chakra-ui/react";

export default function ZAPTable({
  data = [],
  columns = [],
  rowKey = "id",
  actionsRenderer, 
  caption,
  variant = "line",
  size = "sm",
}) {
  return (
    <Table.Root size={size} variant={variant}>
      {caption && <Table.Caption>{caption}</Table.Caption>}

      <Table.Header>
        <Table.Row>
          {columns.map((col, i) => (
            <Table.ColumnHeader
              key={i}
              textAlign={col.textAlign || "left"}
              w={col.width}
            >
              {col.label}
            </Table.ColumnHeader>
          ))}
          {actionsRenderer && (
            <Table.ColumnHeader textAlign="center">
              Actions
            </Table.ColumnHeader>
          )}
        </Table.Row>
      </Table.Header>

      <Table.Body>
        {Array.isArray(data) && data?.map((row) => (
          <Table.Row key={row[rowKey]}>
            {columns.map((col, i) => (
              <Table.Cell key={i} textAlign={col.textAlign || "left"}>
                {col.render ? col.render(row) : row[col.key] || "--"}
              </Table.Cell>
            ))}
            {actionsRenderer && (
              <Table.Cell textAlign="center">
                <HStack justify="center" spacing="1">
                  {actionsRenderer(row)}
                </HStack>
              </Table.Cell>
            )}
          </Table.Row>
        ))}
      </Table.Body>
    </Table.Root>
  );
}
