import Tooltip from "@ZAPComponents/chakra/Tooltip";


const ZAPTooltip = ({
    content,
    children,
    placement = "top",
    positioning = {
        // offset: [0, 0],
        offset: {
            mainAxis: 0,
            crossAxis: 0,
        }
    }
}) => {
    return (
        <Tooltip
            contentProps={{ bg: "var(--zaplane-primary)" }}
            content={content}
            positioning={{
                placement: placement,
                ...positioning
            }}
        >
            {children}
        </Tooltip>
    );
};

export default ZAPTooltip;
