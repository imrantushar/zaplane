import Tooltip from "@ZAPComponents/chakra/Tooltip";


const ZAPTooltip = ({
    content,
    children,
    placement = "top",
    positioning

}) => {
    return (
        <Tooltip
            contentProps={{bg:"var(--zaplane-primary)"}}
            content={content}
            positioning={{
                placement :placement,
                ...positioning
            }}

        >
            {children}
        </Tooltip>
    );
};

export default ZAPTooltip;
