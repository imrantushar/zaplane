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
import './styles.scss'
import ZAPDivider from "@ZAPComponents/ZAPDivider";


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
            borderRadius="lg"
            boxShadow="md"
            w="100%"
            h="388px"
        >
            <Text className="zaplane-label" fontSize='14px' p="24px">
                {__("Total Executions", "zaplane")}
            </Text>
            <ZAPDivider />
            <Box h="315px">
                <ResponsiveContainer style={{padding:"24px",marginLeft:'-42px'}} width="106%" height="100%">
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
        </Box>
    );
};

export default TotalExecutions;
