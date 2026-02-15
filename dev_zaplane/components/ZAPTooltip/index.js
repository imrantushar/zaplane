import Tooltip from "@ZAPComponents/chakra/Tooltip";


const ZAPTooltip = ({
    content,
    children,
    placement = "top",

}) => {
    return (
        <Tooltip
            content={content}
            positioning={{
                placement,
                offset: {
                    mainAxis: 8,
                    crossAxis: 25,
                }
            }}

        >
            {children}
        </Tooltip>
    );
};

export default ZAPTooltip;
