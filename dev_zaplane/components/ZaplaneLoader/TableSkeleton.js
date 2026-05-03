import React from 'react';
const TableSkeleton = ({
  makeItems = 5
}) => {
  return <table className="min-w-full">
            <tbody>
                {[...Array(makeItems)].map((_, i) => <tr key={i} className="border-b border-gray-200">
                        {[...Array(makeItems)].map((_, j) => <td key={j} className="px-4 py-3"><div className="h-4 w-full bg-gray-200 rounded animate-pulse" /></td>)}
                    </tr>)}
            </tbody>
        </table>;
};
export default TableSkeleton;