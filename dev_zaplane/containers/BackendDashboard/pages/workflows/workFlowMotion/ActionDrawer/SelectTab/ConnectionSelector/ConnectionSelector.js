import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { Button, Flex } from "@chakra-ui/react";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { fetchConnectionsByApp } from "@ZAPRedux/Slices/workFlowSlice/actions/connectionsSlice";
import ConnectionPopaver from "./ConnectionPopaver";
import { __ } from "@wordpress/i18n";
import { secondPrimaryBtn } from "../../../../../../../../../assets/scss/chakra/recipe";


const ConnectionSelector = ({ appSlug, values, setFieldValue }) => {
    const dispatch = useDispatch();
    const { appConnections } = useSelector(state => state.workflows);
    const [isPopoverOpen, setPopoverOpen] = useState(false);

    useEffect(() => {
        if (appSlug) {
            dispatch(fetchConnectionsByApp(appSlug));
        }
    }, [appSlug]);

    const options = appConnections
        ?.filter(c => c.app === appSlug)
        ?.map(c => ({
            label: c.name,
            value: String(c.id),
        }));

    return (
        <>
            <ZAPSelect
                label="Select Connection"
                options={options}
                value={values?.connection_id}
                onChange={(val) => {
                    setFieldValue("connection_id", val.value)
                }}
                placeholder="Select a connection"
            />
            <Button {...secondPrimaryBtn} size="sm" mt='24px' width='100%' onClick={() => setPopoverOpen(true)}>
               {__('Create New Connection','zaplane')}
            </Button>
            <ConnectionPopaver
                appSlug={appSlug}
                isOpen={isPopoverOpen}
                onClose={() => {
                    setPopoverOpen(false);
                }} />
        </>

    );
};

export default ConnectionSelector;