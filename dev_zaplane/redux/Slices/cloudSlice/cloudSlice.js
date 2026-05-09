import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
	API,
	handleSliceSuccess,
	handleSliceError,
	namespace,
} from '@ZAPUtils/helper';

export const fetchCloudStatus = createAsyncThunk(
	'zaplane/fetchCloudStatus',
	async (_, thunkAPI) => {
		try {
			const res = await API.get(namespace + 'cloud/status');
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const pairCloud = createAsyncThunk(
	'zaplane/pairCloud',
	async ({ cloud_url, code }, thunkAPI) => {
		try {
			const res = await API.post(namespace + 'cloud/pair', { cloud_url, code });
			handleSliceSuccess(thunkAPI, __('Connected to Zaplane Cloud', 'zaplane'));
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const disconnectCloud = createAsyncThunk(
	'zaplane/disconnectCloud',
	async (_, thunkAPI) => {
		try {
			const res = await API.post(namespace + 'cloud/disconnect', {});
			handleSliceSuccess(thunkAPI, __('Disconnected from Zaplane Cloud', 'zaplane'));
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const testCloud = createAsyncThunk(
	'zaplane/testCloud',
	async (_, thunkAPI) => {
		try {
			const res = await API.post(namespace + 'cloud/test', {});
			handleSliceSuccess(thunkAPI, __('Connection healthy', 'zaplane'));
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

const initialState = {
	status: { paired: false },
	isLoadingStatus: false,
	isPairing: false,
	isDisconnecting: false,
	isTesting: false,
	lastTestResult: null,
};

const cloudSlice = createSlice({
	name: 'cloud',
	initialState,
	reducers: {},
	extraReducers: (builder) => {
		builder
			.addCase(fetchCloudStatus.pending, (state) => {
				state.isLoadingStatus = true;
			})
			.addCase(fetchCloudStatus.fulfilled, (state, action) => {
				state.status = action.payload || { paired: false };
				state.isLoadingStatus = false;
			})
			.addCase(fetchCloudStatus.rejected, (state) => {
				state.isLoadingStatus = false;
			})

			.addCase(pairCloud.pending, (state) => {
				state.isPairing = true;
			})
			.addCase(pairCloud.fulfilled, (state, action) => {
				state.status = action.payload || { paired: true };
				state.isPairing = false;
			})
			.addCase(pairCloud.rejected, (state) => {
				state.isPairing = false;
			})

			.addCase(disconnectCloud.pending, (state) => {
				state.isDisconnecting = true;
			})
			.addCase(disconnectCloud.fulfilled, (state, action) => {
				state.status = action.payload || { paired: false };
				state.isDisconnecting = false;
				state.lastTestResult = null;
			})
			.addCase(disconnectCloud.rejected, (state) => {
				state.isDisconnecting = false;
			})

			.addCase(testCloud.pending, (state) => {
				state.isTesting = true;
			})
			.addCase(testCloud.fulfilled, (state, action) => {
				state.lastTestResult = { ok: true, at: Math.floor(Date.now() / 1000), data: action.payload };
				state.isTesting = false;
			})
			.addCase(testCloud.rejected, (state) => {
				state.lastTestResult = { ok: false, at: Math.floor(Date.now() / 1000) };
				state.isTesting = false;
			});
	},
});

export default cloudSlice.reducer;
