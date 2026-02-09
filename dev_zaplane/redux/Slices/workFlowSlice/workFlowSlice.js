import { createSlice } from '@reduxjs/toolkit';

import { createWorkflows, deleteWorkFlow, getSingleWorkFlow, getWorkFlow, updateWorkFlow, updateWorkFlowStatus } from './actions/workflow';
import { getRunWorkFlow,getSingleRun } from './actions/workFlowRuns';
import { getAllVersion, getPreviewOldVersion, versionActive } from './actions/workFlowVersion';
import { nodeLogsRunDetails ,getNodeLogDetails} from './actions/workFlowLogs';
import { workFLowSingeNodeExction } from './actions/workflowExctions';
import { workflowNodeListiner, workflowNodeListinerStop } from './actions/workFlowListiner';


const workflowsSlice = createSlice({
	name: 'workflows',
	initialState: {
		data: [],
		runs: [],
		versions: [],
		nodeDetails: [],
		isLoading: true,
		singleNodeExecution: null,
		apiCountdown: 0,
		apiRequestRunning: false,


	},
	reducers: {
		resetSingleNodeExecution(state) {
			state.singleNodeExecution = null;
			state.isLoading = false;
		},
		startApiCountdown(state, action) {
			state.apiCountdown = action.payload;
			state.apiRequestRunning = true;
		},

		decrementApiCountdown(state) {
			if (state.apiCountdown > 1 && state.apiRequestRunning) {
				state.apiCountdown -= 1;
			}
		},
	},
	extraReducers: (builder) => {
		builder
			.addCase(createWorkflows.fulfilled, (state, action) => {
				state.data = action.payload;
			})
			.addCase(getWorkFlow.fulfilled, (state, action) => {
				state.data = [...action.payload].reverse();
				state.isLoading = false
			})

			.addCase(getSingleWorkFlow.fulfilled, (state, action) => {
				if (!action.payload) return;
				state.data = [action.payload];
			})
			.addCase(updateWorkFlow.fulfilled, (state, action) => {
				state.data = state.data.map((item) => {
					if (parseInt(item.id) === parseInt(action.payload.id)) {
						return { ...item, ...action.payload };
					}
					return item;
				});
			})
			.addCase(deleteWorkFlow.fulfilled, (state, action) => {
				state.data = state.data.filter(
					(item) => parseInt(item.id) !== parseInt(action.payload)
				);
			})
			.addCase(updateWorkFlowStatus.fulfilled, (state, action) => {
				state.data = state.data.map((item) =>
					parseInt(item.id) === parseInt(action.payload?.id)
						? { ...item, status: action.payload.status }
						: item
				);
			})
			.addCase(getRunWorkFlow.fulfilled, (state, action) => {
				state.runs = action.payload;
				state.isLoading = false
			})
			.addCase(getPreviewOldVersion.fulfilled, (state, action) => {
				if (!state.data.length) return;
				state.data[0] = {
					...state.data[0],
					graph: action.payload.graph,
					is_preview: true,
					preview_version_id: action.payload.version?.id,
				};
			})
			.addCase(getAllVersion.fulfilled, (state, action) => {
				state.versions = action.payload;
				state.isLoading = false
			})
			.addCase(versionActive.fulfilled, (state, action) => {
				const activeVersionId = action.meta.arg.versionID;
				state.versions = state.versions.map((version) => ({
					...version,
					is_active:
						parseInt(version.id) === parseInt(activeVersionId)
							? "1"
							: "0",
				}));
			})
			.addCase(nodeLogsRunDetails.fulfilled, (state, action) => {
				state.nodeDetails = action.payload;
				state.isLoading = false
			})
			.addCase(workFLowSingeNodeExction.fulfilled, (state, action) => {
				state.isLoading = false;
				state.singleNodeExecution = action.payload?.data || null;
			})


			.addCase(workflowNodeListiner.fulfilled, (state) => {
				state.isLoading = false;
				state.apiRequestRunning = false;
				state.apiCountdown = 0;
			})
			.addCase(workflowNodeListiner.rejected, (state) => {
				state.isLoading = false;
				state.apiRequestRunning = false;
				state.apiCountdown = 0;
			})
			.addCase(workflowNodeListinerStop.fulfilled, (state) => {
				state.isLoading = false;
				state.apiRequestRunning = false;
				state.apiCountdown = 0;
			})
	},
});




export const {
	resetSingleNodeExecution,
	startApiCountdown,
	decrementApiCountdown,
} = workflowsSlice.actions;

export default workflowsSlice.reducer;
