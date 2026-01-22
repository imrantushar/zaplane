import React from "react";
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

const data = [
  { month: "January", value: 8 },
  { month: "February", value: 0 },
  { month: "March", value: 0 },
  { month: "April", value: 0 },
  { month: "May", value: 0 },
  { month: "June", value: 0 },
  { month: "July", value: 0 },
  { month: "August", value: 0 },
  { month: "September", value: 0 },
  { month: "October", value: 0 },
  { month: "November", value: 0 },
  { month: "December", value: 0 },
];

const TotalExecutions = () => {
  return (
    <Box
      bg="white"
      p={4}
      borderRadius="lg"
      boxShadow="md"
      w="100%"
      h="320px"
    >
      <Text fontWeight="semibold" mb={3}>
        Total Executions
      </Text>

      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data}>
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
