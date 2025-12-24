export const parseFlowJson = (flowJson) => {
  try {
    return typeof flowJson === 'string'
      ? JSON.parse(flowJson)
      : flowJson;
  } catch {
    return null;
  }
};
