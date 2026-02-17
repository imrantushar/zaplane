import React from 'react';
import { Skeleton, Table } from '@chakra-ui/react';

const TableSkeleton = ({ makeItems = 5 }) => {
    return (
        <Table.Root>
            <Table.Body>
                {[...Array(makeItems)].map((_, i) => (
                    <Table.Row>
                        {[...Array(makeItems)].map((_, i) => (
                            <Table.Cell key={i}><Skeleton height="4" width="100%" /></Table.Cell>
                        ))}
                    </Table.Row>
                ))}
            </Table.Body>
        </Table.Root>
    );
};

export default TableSkeleton;