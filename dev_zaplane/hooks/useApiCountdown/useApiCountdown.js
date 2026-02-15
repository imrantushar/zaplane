import { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { decrementApiCountdown } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";

export const useApiCountdown = () => {
  const dispatch = useDispatch();
  const { apiCountdown, apiRequestRunning } = useSelector(
    (state) => state.workflows
  );

  useEffect(() => {
    if (!apiRequestRunning || apiCountdown <= 0) return;

    const interval = setInterval(() => {
      dispatch(decrementApiCountdown());
    }, 1000);

    return () => clearInterval(interval);
  }, [apiCountdown, apiRequestRunning, dispatch]);
};
