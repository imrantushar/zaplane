import { createSlice } from '@reduxjs/toolkit';

const initialState = {
    isLoading: false,
    notification: null, // e.g., { type: 'success', message: 'Settings saved!' }
};

const appSlice = createSlice({
    name: 'app',
    initialState,
    reducers: {
        // Reducer to set the global loading state
        setLoading: (state, action) => {
            state.isLoading = action.payload;
        },
        // Reducer to set a notification message
        setNotification: (state, action) => {
            state.notification = action.payload;
        },
        // Reducer to clear the notification
        clearNotification: (state) => {
            state.notification = null;
        },
    },
});

// Export the actions to be dispatched from components
export const { setLoading, setNotification, clearNotification } = appSlice.actions;

// Export the reducer for the store
export default appSlice.reducer;