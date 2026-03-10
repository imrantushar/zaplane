
export const getBranchNodes = (startId,edges) => {
    const branch = new Set();
    const stack = [startId];

    while (stack.length) {
        const current = stack.pop();
        branch.add(current);

        edges.forEach((e) => {
            if (e.source === current) {
                stack.push(e.target);
            }
        });
    }

    return branch;
};