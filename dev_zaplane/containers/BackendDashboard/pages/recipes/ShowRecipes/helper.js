export const findFolder = (list, targetId) => {
    for (const f of list) {
        if (f.id === targetId) return f;
        const found = findFolder(f.children || [], targetId);
        if (found) return found;
    }
    return null;
};