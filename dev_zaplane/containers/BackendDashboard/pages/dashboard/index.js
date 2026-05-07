import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { useDispatch, useSelector } from 'react-redux';
import RecentLogs from './RecentLogs';
import ExecutedFlows from './ExecutedFlows';
import { getRunsList } from '@ZAPRedux/Slices/logsSlice/logsSlice';
import TotalExecutions from './TotalExecutions';
import { deshboardSumary, topExecutedFlows } from '@ZAPRedux/Slices/dashboardSlice/dashboardSlice';
import OverviewSection from './OverviewSection/OverviewSection';
import CreateWorkflowModal from '@ZAPComponents/CreateWorkflowModal';
import PageLayout from '@ZAPComponents/PageLayout';
export default function Dashboard() {
  const dispatch = useDispatch();
  const {
    data = []
  } = useSelector(state => state.logs || {});
  const [isModalOpen, setIsModalOpen] = useState(false);
  useEffect(() => {
    dispatch(getRunsList());
    dispatch(topExecutedFlows());
    dispatch(deshboardSumary());
  }, [dispatch]);
  return (
    <PageLayout 
      title="Dashboard" 
      actions={
        <button 
          onClick={() => setIsModalOpen(true)} 
          style={primaryBtn}
        >
          {__("Create New Workflow", "zaplane")}
        </button>
      }
    >
      <div className="flex flex-col gap-6">
        <OverviewSection />
        
        <TotalExecutions />
        
        <div className="flex gap-6 items-start">
          <div className="w-[35%]">
            <ExecutedFlows />
          </div>
          <div className="w-[65%]">
            <RecentLogs data={data} />
          </div>
        </div>
      </div>
      <CreateWorkflowModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} />
    </PageLayout>
  );
}
