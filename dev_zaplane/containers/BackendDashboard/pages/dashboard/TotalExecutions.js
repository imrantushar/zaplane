import React from "react";
import { Box, Text } from "@chakra-ui/react";
import {
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from "recharts";
import { __ } from "@wordpress/i18n";
import { useSelector } from "react-redux";

const TotalExecutions = () => {
    const { summary } = useSelector((state) => state.dashboard)
    const monthly_executions = summary?.monthly_executions || [];

    return (
        <Box
            bg="var(--zaplane-background)"
            borderRadius="4px"
            boxShadow="md"
            w="100%"
            h="388px"
        >
            <Text className="zaplane-label" fontSize='14px' p="24px">
                {__("Total Executions", "zaplane")}
            </Text>

            <Box h="315px" px="16px" pb="16px">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={monthly_executions}>

                        <defs>
                            <linearGradient id="colorValue" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#90CDF4" stopOpacity={0.6} />
                                <stop offset="95%" stopColor="#90CDF4" stopOpacity={0} />
                            </linearGradient>
                        </defs>

                        <CartesianGrid
                            strokeDasharray="3 3"
                            vertical={false}
                            stroke="#E2E8F0"
                        />

                        <XAxis
                            dataKey="month"
                            tick={{ fontSize: 12 }}
                            axisLine={false}
                            tickLine={false}
                        />

                        <YAxis
                            allowDecimals={false}
                            tick={{ fontSize: 12 }}
                            axisLine={false}
                            tickLine={false}
                        />

                        <Tooltip />

                        <Area
                            type="monotone"
                            dataKey="runs"
                            name="Runs"
                            stroke="#63B3ED"
                            fill="url(#colorValue)"
                            strokeWidth={2}
                            dot={false}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </Box>
        </Box>
    );
};

export default TotalExecutions;