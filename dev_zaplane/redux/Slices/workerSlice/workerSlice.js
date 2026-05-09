import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
	API,
	handleSliceSuccess,
	handleSliceError,
	namespace,
} from '@ZAPUtils/helper';

export const fetchWorkerStatus = createAsyncThunk(
	'zaplane/fetchWorkerStatus',
	async (_, thunkAPI) => {
		try {
			const res = await API.get(namespace + 'worker/status');
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const fetchWorkerEnvironment = createAsyncThunk(
	'zaplane/fetchWorkerEnvironment',
	async (_, thunkAPI) => {
		try {
			const res = await API.get(namespace + 'worker/environment');
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const fetchWorkerSettings = createAsyncThunk(
	'zaplane/fetchWorkerSettings',
	async (_, thunkAPI) => {
		try {
			const res = await API.get(namespace + 'worker/settings');
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const saveWorkerSettings = createAsyncThunk(
	'zaplane/saveWorkerSettings',
	async (payload, thunkAPI) => {
		try {
			const res = await API.post(namespace + 'worker/settings', payload);
			handleSliceSuccess(thunkAPI, __('Worker settings saved', 'zaplane'));
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const setWorkerEnabled = createAsyncThunk(
	'zaplane/setWorkerEnabled',
	async (enabled, thunkAPI) => {
		try {
			const res = await API.post(namespace + 'worker/settings', { enabled: !!enabled });
			handleSliceSuccess(
				thunkAPI,
				enabled
					? __('Background Worker enabled', 'zaplane')
					: __('Background Worker disabled', 'zaplane')
			);
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const fetchWorkerUnitFile = createAsyncThunk(
	'zaplane/fetchWorkerUnitFile',
	async (type, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + 'worker/unit-file' + (type ? `?type=${encodeURIComponent(type)}` : '')
			);
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const fetchWorkerFailures = createAsyncThunk(
	'zaplane/fetchWorkerFailures',
	async (limit, thunkAPI) => {
		try {
			const qs = limit ? `?limit=${encodeURIComponent(limit)}` : '';
			const res = await API.get(namespace + 'worker/failures' + qs);
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const fetchWorkerRetention = createAsyncThunk(
	'zaplane/fetchWorkerRetention',
	async (_, thunkAPI) => {
		try {
			const res = await API.get(namespace + 'worker/retention');
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const saveWorkerRetention = createAsyncThunk(
	'zaplane/saveWorkerRetention',
	async (days, thunkAPI) => {
		try {
			const res = await API.post(namespace + 'worker/retention', { days });
			handleSliceSuccess(thunkAPI, __('Retention updated', 'zaplane'));
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const runWorkerCleanup = createAsyncThunk(
	'zaplane/runWorkerCleanup',
	async (_, thunkAPI) => {
		try {
			const res = await API.post(namespace + 'worker/cleanup', {});
			if (res.data?.skipped) {
				handleSliceSuccess(thunkAPI, __('Cleanup skipped (retention disabled)', 'zaplane'));
			} else {
				handleSliceSuccess(
					thunkAPI,
					`${__('Deleted', 'zaplane')} ${res.data?.runs_deleted || 0} ${__('runs', 'zaplane')}`
				);
			}
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

const initialState = {
	status: null,
	jobsPerMin: 0,
	environment: null,
	recommendedRunner: '',
	settings: {
		memory: 256,
		max_jobs: 1000,
		max_time: 3600,
		runner: 'systemd',
		enabled: false,
	},
	unitFile: null,
	failures: [],
	failuresTotal: 0,
	retention: {
		days: 30,
		default: 30,
		last_run: {},
	},
	isLoadingStatus: false,
	isLoadingEnv: false,
	isLoadingSettings: false,
	isSavingSettings: false,
	isLoadingUnitFile: false,
	isLoadingFailures: false,
	isLoadingRetention: false,
	isSavingRetention: false,
	isRunningCleanup: false,
};

const workerSlice = createSlice({
	name: 'worker',
	initialState,
	reducers: {},
	extraReducers: (builder) => {
		builder
			.addCase(fetchWorkerStatus.pending, (state) => {
				state.isLoadingStatus = true;
			})
			.addCase(fetchWorkerStatus.fulfilled, (state, action) => {
				state.status = action.payload?.status ?? null;
				state.jobsPerMin = action.payload?.jobs_per_min ?? 0;
				state.isLoadingStatus = false;
			})
			.addCase(fetchWorkerStatus.rejected, (state) => {
				state.isLoadingStatus = false;
			})

			.addCase(fetchWorkerEnvironment.pending, (state) => {
				state.isLoadingEnv = true;
			})
			.addCase(fetchWorkerEnvironment.fulfilled, (state, action) => {
				state.environment = action.payload?.environment ?? null;
				state.recommendedRunner = action.payload?.recommended_runner ?? '';
				state.isLoadingEnv = false;
			})
			.addCase(fetchWorkerEnvironment.rejected, (state) => {
				state.isLoadingEnv = false;
			})

			.addCase(fetchWorkerSettings.pending, (state) => {
				state.isLoadingSettings = true;
			})
			.addCase(fetchWorkerSettings.fulfilled, (state, action) => {
				state.settings = { ...state.settings, ...(action.payload || {}) };
				state.isLoadingSettings = false;
			})
			.addCase(fetchWorkerSettings.rejected, (state) => {
				state.isLoadingSettings = false;
			})

			.addCase(saveWorkerSettings.pending, (state) => {
				state.isSavingSettings = true;
			})
			.addCase(saveWorkerSettings.fulfilled, (state, action) => {
				state.settings = { ...state.settings, ...(action.payload || {}) };
				state.isSavingSettings = false;
			})
			.addCase(saveWorkerSettings.rejected, (state) => {
				state.isSavingSettings = false;
			})

			.addCase(setWorkerEnabled.fulfilled, (state, action) => {
				state.settings = { ...state.settings, ...(action.payload || {}) };
			})

			.addCase(fetchWorkerUnitFile.pending, (state) => {
				state.isLoadingUnitFile = true;
			})
			.addCase(fetchWorkerUnitFile.fulfilled, (state, action) => {
				state.unitFile = action.payload || null;
				state.isLoadingUnitFile = false;
			})
			.addCase(fetchWorkerUnitFile.rejected, (state) => {
				state.isLoadingUnitFile = false;
			})

			.addCase(fetchWorkerFailures.pending, (state) => {
				state.isLoadingFailures = true;
			})
			.addCase(fetchWorkerFailures.fulfilled, (state, action) => {
				state.failures = action.payload?.failures ?? [];
				state.failuresTotal = action.payload?.total ?? 0;
				state.isLoadingFailures = false;
			})
			.addCase(fetchWorkerFailures.rejected, (state) => {
				state.isLoadingFailures = false;
			})

			.addCase(fetchWorkerRetention.pending, (state) => {
				state.isLoadingRetention = true;
			})
			.addCase(fetchWorkerRetention.fulfilled, (state, action) => {
				state.retention = { ...state.retention, ...(action.payload || {}) };
				state.isLoadingRetention = false;
			})
			.addCase(fetchWorkerRetention.rejected, (state) => {
				state.isLoadingRetention = false;
			})

			.addCase(saveWorkerRetention.pending, (state) => {
				state.isSavingRetention = true;
			})
			.addCase(saveWorkerRetention.fulfilled, (state, action) => {
				state.retention = { ...state.retention, ...(action.payload || {}) };
				state.isSavingRetention = false;
			})
			.addCase(saveWorkerRetention.rejected, (state) => {
				state.isSavingRetention = false;
			})

			.addCase(runWorkerCleanup.pending, (state) => {
				state.isRunningCleanup = true;
			})
			.addCase(runWorkerCleanup.fulfilled, (state, action) => {
				if (action.payload && !action.payload.skipped) {
					state.retention.last_run = { ...action.payload, finished_at: Math.floor(Date.now() / 1000) };
				}
				state.isRunningCleanup = false;
			})
			.addCase(runWorkerCleanup.rejected, (state) => {
				state.isRunningCleanup = false;
			});
	},
});

export default workerSlice.reducer;
