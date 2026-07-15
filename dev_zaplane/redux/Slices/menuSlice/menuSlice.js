import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { menu, API, namespace } from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';

// Refetch the feature-filtered admin menu so the SPA sidebar reflects a module
// being toggled on/off without a full page reload.
export const fetchAdminMenuItems = createAsyncThunk(
	'Zaplane/fetchAdminMenuItems',
	async ( _, thunkAPI ) => {
		try {
			const res = await API.get( namespace + 'menu' );
			return res.data;
		} catch ( error ) {
			thunkAPI.dispatch(
				showNotification( {
					message: error?.response?.data?.message ?? error?.message,
					isShow: true,
					type: 'error',
				} )
			);
			return thunkAPI.rejectWithValue( error?.message );
		}
	}
);

const menuSlice = createSlice( {
	name: 'menus',
	initialState: {
		data: menu ? JSON.parse( menu ) : [],
		loading: false,
	},
	reducers: {
		setMenu: ( state, action ) => {
			state.data = action.payload;
		},
	},
	extraReducers: ( builder ) => {
		builder
			.addCase( fetchAdminMenuItems.pending, ( state ) => {
				state.loading = true;
			} )
			.addCase( fetchAdminMenuItems.fulfilled, ( state, action ) => {
				state.loading = false;
				state.data = action.payload;
			} )
			.addCase( fetchAdminMenuItems.rejected, ( state ) => {
				state.loading = false;
			} );
	},
} );

export const { setMenu } = menuSlice.actions;
export default menuSlice.reducer;
