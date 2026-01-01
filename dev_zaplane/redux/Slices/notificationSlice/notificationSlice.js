import { createSlice } from '@reduxjs/toolkit';

const notificationSlice = createSlice( {
	name: 'notification',
	initialState: {
		message: '',
		isShow: false,
		type: '',
	},
	reducers: {
		showNotification: ( state, actions ) => {
			const payload = actions.payload;
			state.message = payload.message;
			state.isShow = payload.isShow;
			state.type = payload.type;
		},
	},
} );

export default notificationSlice.reducer;
export const { showNotification } = notificationSlice.actions;
