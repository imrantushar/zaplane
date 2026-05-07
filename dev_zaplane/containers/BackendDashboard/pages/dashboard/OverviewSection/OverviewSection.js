import React from "react";
import { FiLink, FiPlayCircle, FiActivity } from "react-icons/fi";
import { useSelector } from "react-redux";
import StatCard from "./StatCard";
const OverviewSection = () => {
  const {
    summary,
    isLoading
  } = useSelector(state => state.dashboard);
  const {
    total_workflows,
    total_executions,
    active_workflows
  } = summary || {};
  return <div className="flex gap-6 flex-wrap">
            <StatCard title="Total Flows" value={total_workflows} isLoading={isLoading} icon={FiLink} />
            <StatCard title="Total Executions" value={total_executions} isLoading={isLoading} icon={FiPlayCircle} />
            <StatCard title="Active Flows" value={active_workflows} isLoading={isLoading} icon={FiActivity} />
        </div>;
};
export default OverviewSection;