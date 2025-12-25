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
} from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';
export const createWorkflows = createAsyncThunk(
	'zaplane/createWorkflows',
	async (payload, thunkAPI) => {
		return await API.post(namespace + 'workflows', payload)
			.then((res) => {
				handleSliceSuccess(thunkAPI, "data fetched successfully");
				return res.data;
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
			return handleSliceError( thunkAPI, e )
		}
	}
);
export const updateWorkFlow = createAsyncThunk(
	'zaplane/updateWorkFlow',
	async ({ id, payload }, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + "workflows/" + parseInt(id),
				payload
			);
			handleSliceSuccess( thunkAPI, __('Updated workflow Successfully', 'workflow') );
			return res.data;
		} catch (e) {
			handleSliceError( thunkAPI, e )
		}
	}
);
export const getSingleWorkFlow = createAsyncThunk(
	'zaplane/getSingleWorkFlow',
	async (id, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + "workflows/" + parseInt(id), {
			});
			return res.data;
		} catch (e) {
	           return	handleSliceError( thunkAPI, e )
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

			return handleSliceError( thunkAPI, e );
		}
	}
);

const workflowsSlice = createSlice({
	name: 'workflows',
	initialState: {
		data: [],

	},
	reducers: {

	},
	extraReducers: (builder) => {
		builder
			.addCase(createWorkflows.fulfilled, (state, action) => {
				state.data = action.payload;
			})
			.addCase(getWorkFlow.fulfilled, (state, action) => {
				state.data = [...action.payload].reverse();
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


	},
});

export const { createWorkflowTitle } = workflowsSlice.actions;
export default workflowsSlice.reducer;
