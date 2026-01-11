import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
	API,
	handleSliceSuccess,
	handleSliceError,
	namespace,
} from '@ZAPUtils/helper';

import { showNotification } from '../notificationSlice/notificationSlice';

// get all connection
export const fetchConnections = createAsyncThunk(
	'zaplane/fetchConnections',
	async ({}, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + 'connections',
			);

			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

// get auth filed with key
export const fetchAuthFields = createAsyncThunk(
	'zaplane/connections/authFields',
	async (app, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `connections/auth-fields/${app}`
			);

			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

// start auth
export const initOAuth = createAsyncThunk(
	'zaplane/initOAuth',
	async ({ app, name }, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + 'connections/oauth/init',
				{ app, name }
			);

			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

// test connection
export const testConnection = createAsyncThunk(
	'zaplane/testConnection',
	async (connectionId, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + `connections/${connectionId}/test`
			);

			handleSliceSuccess(
				thunkAPI,
				res.data?.message || __('Connection successful', 'workflow')
			);

			return {
				id: connectionId,
				result: res.data,
			};
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

//  deleted connection 
export const deleteConnection = createAsyncThunk(
	'zaplane/deleteConnection',
	async (connectionId, thunkAPI) => {
		try {
			await API.delete(
				namespace + `connections/${connectionId}`
			);

			thunkAPI.dispatch(
				showNotification({
					type: 'success',
					message: __('Connection deleted', 'workflow'),
				})
			);

			return connectionId;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

const connectionsSlice = createSlice({
	name: 'connections',
	initialState: {
		list: [],
	
	},
	reducers: {
	},
	extraReducers: (builder) => {
		builder
			
	},
});

export const {
	resetAuthFields,
	resetOAuth,
} = connectionsSlice.actions;

export default connectionsSlice.reducer;
