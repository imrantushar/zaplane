import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
	API,
	current_user_can,
	current_user_id,
	is_admin,
	handleSliceSuccess,
	handleSliceError,
	namespace,
	makeRequest,
} from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';
import { version } from 'react';
export const createWorkflows = createAsyncThunk(
	'zaplane/createWorkflows',
	async (payload, thunkAPI) => {
		return await API.post(namespace + 'workflows', payload)
			.then((res) => {
				return res?.data;
			})
			.catch((err) => {
				return handleSliceError(thunkAPI, "Data fetching failed");
			});
	}
);

export const getWorkFlow = createAsyncThunk(
	'zaplane/getWorkFlow',
	async (thunkAPI) => {
		try {
			const res = await API.get(namespace + "workflows");
			return res.data
		} catch (e) {
			return handleSliceError(thunkAPI, e)
		}
	}
);
export const updateWorkFlow = createAsyncThunk(
	'zaplane/updateWorkFlow',
	async ({ id, payload }, thunkAPI) => {
		try {
			const res = await API.put(
				namespace + "workflows/" + parseInt(id),
				payload
			);
			handleSliceSuccess(thunkAPI, __('Updated workflow Successfully', 'workflow'));
			return res.data;
		} catch (e) {
			handleSliceError(thunkAPI, e)
		}
	}
);
export const getSingleWorkFlow = createAsyncThunk(
	'zaplane/getSingleWorkFlow',
	async (id, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + "workflows/" + parseInt(id) + "/graph", {
			});
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e)
		}
	}
);
export const deleteWorkFlow = createAsyncThunk(
	'zaplane/deleteWorkFlow',
	async (id, thunkAPI) => {
		try {
			const res = await API.delete(
				namespace + "workflows/" + parseInt(id),
				{
					data: { force: true },
					headers: {
						'X-HTTP-Method-Override': 'DELETE',
					},
				}
			);

			thunkAPI.dispatch(
				showNotification({
					message: 'workflow Deleted',
					isShow: true,
					type: 'success',
				})
			);
			return res?.data?.data?.odd?.id || id;
		} catch (e) {

			return handleSliceError(thunkAPI, e);
		}
	}
);
export const updateWorkFlowStatus = createAsyncThunk(
	'zaplane/updateWorkFlowStatus',
	async (payload, thunkAPI) => {
		try {
			await makeRequest('update_workflow_status', {
				id: payload.id,
				...payload,
			});
			return payload;
		} catch (e) {
			thunkAPI.dispatch(
				showNotification({
					message: e,
					isShow: true,
					type: 'error',
				})
			);
		}
	}
);

export const getRunWorkFlow = createAsyncThunk(
	'zaplane/getRunWorkFlow',
	async (id, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `workflows/${id}/runs`
			);
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getSingleRun = createAsyncThunk(
	'zaplane/getSingleRun',
	async (runId, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + `node-runs/${parseInt(runId)}/retry`
			);

			handleSliceSuccess(thunkAPI, __('Run fetched successfully', 'workflow'));

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
// workflow exctions api
export const workFLowExction = createAsyncThunk(
	'zaplane/workFLowExction',
	async (payload, thunkAPI) => {

		try {
			const res = await API.post(
				namespace + 'execute',
				payload
			);

			handleSliceSuccess(thunkAPI, __('Run fetched successfully', 'workflow'));
			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const workFLowSingeNodeExction = createAsyncThunk(
	'zaplane/workFLowSingeNodeExction',
	async (payload, thunkAPI) => {

		try {
			const res = await API.post(
				namespace + 'execute-node',
				payload
			);
			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
// work flow listnner
export const workflowNodeListiner = createAsyncThunk(
	'zaplane/workflowNodeListiner',
	async (id, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `node-listener/${id}`
			);
			handleSliceSuccess(thunkAPI, __('Work Flow run successfully', 'workflow'));
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const workflowNodeListinerStop = createAsyncThunk(
	'zaplane/workflowNodeListinerStop',
	async (id, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + `node-listener/${id}/stop`
			);
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
// log api
export const nodeLogsRunDetails = createAsyncThunk(
	'zaplane/nodeLogsRunDetails',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs/${parseInt(runId)}`
			);

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getNodeLogDetails = createAsyncThunk(
	'zaplane/getNodeLogDetails',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs/${parseInt(runId)}`
			);

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
// version releted api
export const getAllVersion = createAsyncThunk(
	'zaplane/getAllVersion',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `workflows/${parseInt(runId)}/versions`
			);
			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getPreviewOldVersion = createAsyncThunk(
	'zaplane/getPreviewOldVersion',
	async ({ id, versionID }, thunkAPI) => {

		try {
			const res = await API.get(
				namespace + `workflows/${id}/versions/${parseInt(versionID)}`
			);

			handleSliceSuccess(thunkAPI, __(' fetched prevews version successfully', 'workflow'));

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const versionActive = createAsyncThunk(
	'zaplane/versionActive',
	async ({ id, versionID }, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + `workflows/${id}/versions/${parseInt(versionID)}/activate`
			);
			handleSliceSuccess(thunkAPI, __('version active successfully', 'workflow'));

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);


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


export async function fetchDynamic({
	integration,
	query,
	select,
	where = {},
	search = "",
	limit = 20,
}) {
	const { data } = await API.post(namespace + "dynamic", {
		integration,
		query,
		select,
		where,
		search,
		limit,
	});

	return data;
}


export const {
	resetSingleNodeExecution,
	startApiCountdown,
	decrementApiCountdown,
} = workflowsSlice.actions;

export default workflowsSlice.reducer;
