import React, { useMemo } from "react";
import { Box, Text } from "@chakra-ui/react";
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from "recharts";
import { __ } from "@wordpress/i18n";


const TotalExecutions = ({ data = [] }) => {
    const chartData = useMemo(() => {
        const months = [
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December",
        ];
        const result = months.map((month) => ({
            month,
            value: 0,
        }));

        data.forEach((item) => {
            if (!item.started_at) return;

            const date = new Date(item.started_at);
            const monthIndex = date.getMonth();

            result[monthIndex].value += 1;
        });

        return result;
    }, [data]);

    return (
        <Box
            bg="var(--zaplane-background)"
            p={4}
            borderRadius="lg"
            boxShadow="md"
            w="100%"
            h="320px"
        >
            <Text fontWeight="semibold" mb={3}>
                {__("Total Executions", "zaplane")}
            </Text>

            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="month" />
                    <YAxis allowDecimals={false} />
                    <Tooltip />
                    <Line
                        type="monotone"
                        dataKey="value"
                        stroke="#1A202C"
                        strokeWidth={2}
                        dot={{ r: 5 }}
                    />
                </LineChart>
            </ResponsiveContainer>
        </Box>
    );
};

export default TotalExecutions;
