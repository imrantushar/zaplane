import React from 'react';

const Sidebar = () => {
    const nodesList = [
        { type: "automation", title: "Automation Node 1" },
        { type: "automation", title: "Automation Node 2" },
        { type: "automation", title: "Automation Node 3" },
        { type: "automation", title: "Automation Node 4" },
        { type: "automation", title: "Automation Node 5" },
        { type: "automation", title: "Automation Node 6" },
        { type: "automation", title: "Automation Node 7" },
        { type: "automation", title: "Automation Node 8" },
        { type: "automation", title: "Automation Node 9" },
        { type: "automation", title: "Automation Node 10" },
    ];

    const onDragStart = (event, node) => {
        event.dataTransfer.setData("application/reactflow", JSON.stringify(node));
        event.dataTransfer.effectAllowed = "move";
    };

    return (
        <aside className='sidebar' style={{ padding: "10px", width: "200px", background: "#f0f0f0" }}>
            {nodesList.map((node, index) => (
                <div
                    key={index}
                    onDragStart={(e) => onDragStart(e, node)}
                    draggable
                    style={{
                        padding: "8px",
                        margin: "5px",
                        background: "#fff",
                        cursor: "grab",
                    }}
                >
                    {node.title}
                </div>
            ))}
        </aside>
    );
};

export default Sidebar;