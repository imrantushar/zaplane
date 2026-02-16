import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { menu, makeRequest } from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';

export const fetchAdminMenuItems = createAsyncThunk(
	'Zaplane/fetchAdminMenuItems',
	( thunkAPI ) => {
		try {
			return makeRequest( 'get_admin_menu_items' ).then( ( res ) => {
				return JSON.parse( res?.data?.data );
			} );
		} catch ( error ) {
			thunkAPI.dispatch(
				showNotification( {
					message: error?.response?.data?.message ?? error?.message,
					isShow: true,
					type: 'error',
				} )
			);
		}
	}
);

const menuSlice = createSlice( {
	name: 'menus',
	initialState: {
		data: JSON.parse( menu ),
		loading: false,
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

export default menuSlice.reducer;
