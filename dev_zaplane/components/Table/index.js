import React from "react";
import {
  Table,
  Text,
  HStack,
  Button,
  Badge,
  Flex,
} from "@chakra-ui/react";
import ZAPLoading from "@ZAPComponents/Loading";
import ZAPText from "@ZAPComponents/Text";
import { __ } from "@wordpress/i18n";
import CustomTableMessage from "@ZAPComponents/Oops/CustomTableMessage";

const ZAPTable = ({
  data = [],
  columns = [],
  rowKey = "id",
  actionsRenderer,
  caption,
  variant = "line",
  size = "sm",
  isLoading = false,
  noDataText = __(
    'Please, create data to see the available list here.',
    'easy-content-manager'
  )
}) => {
  const colSpan = columns.length + (actionsRenderer ? 1 : 0);

  return (
    <Flex
      direction="column"
      bg="white"
      border="1px solid"
      borderColor="gray.200"
      borderRadius="lg"
      boxShadow="sm"
      overflow="hidden"
    >
      <Table.Root size={size} variant={variant}>
        {caption && <Table.Caption>{caption}</Table.Caption>}
        <Table.Header>
          <Table.Row>
            {columns.map((col, i) => (
              <Table.ColumnHeader
                key={i}
                textAlign={col.textAlign || "left"}
                w={col.width}
                py="20px"
              >
                {__(col.label, 'zaplane')}
              </Table.ColumnHeader>
            ))}
            {actionsRenderer && (
              <Table.ColumnHeader textAlign="center">
                {__('Actions', 'zaplane')}
              </Table.ColumnHeader>
            )}
          </Table.Row>
        </Table.Header>

        <Table.Body>
          {isLoading && (
            <Table.Row>
              <Table.Cell colSpan={colSpan}>
                <Flex justify="center" py={6}>
                  <ZAPLoading />
                </Flex>
              </Table.Cell>
            </Table.Row>
          )}
          {!isLoading &&
            Array.isArray(data) &&
            data.map((row) => (
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
          {!isLoading && Array.isArray(data) && data.length === 0 && (
            <Table.Row>
              <Table.Cell colSpan={colSpan} textAlign="center">
                <ZAPText fontSize="sm" color="gray.500">

                  <CustomTableMessage
                    title={__(
                      'No Data Available!!!',
                      'easy-content-manager'
                    )}
                    subText={noDataText}
                  />
                </ZAPText>
              </Table.Cell>
            </Table.Row>
          )}
        </Table.Body>
      </Table.Root>
    </Flex>

  );
};

export default ZAPTable;
