import React from "react";
import { Box, Flex, Text, Icon,  } from "@chakra-ui/react";
import { FiLayers, FiPlayCircle, FiActivity } from "react-icons/fi";
import {  useSelector } from "react-redux";
import StatCard from "./StatCard";



const OverviewSection = () => {
     const{ summary, isLoading} =useSelector((state)=>state.dashboard)
   const {total_workflows, total_executions, active_workflows} = summary || {}
    return (
        <Flex gap="24px" flexWrap="wrap">
            <StatCard title="Total Flows" value={total_workflows} isLoading={isLoading} icon={FiLayers} />
            <StatCard title="Total Executions" value={total_executions} isLoading={isLoading} icon={FiPlayCircle} />
            <StatCard title="Active Flows" value={active_workflows} isLoading={isLoading} icon={FiActivity} />
        </Flex>
    );
};

export default OverviewSection;