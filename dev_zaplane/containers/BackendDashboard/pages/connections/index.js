import { __ } from "@wordpress/i18n";
import { FaSlack } from "react-icons/fa";
import ConnectionTable from "./ConnectionTable";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import useConnection from "@ZAPHooks/useConnection/useConnection";
import ConnectionDrawer from "@ZAPComponents/ConnectionDrawer";
import PageLayout from "@ZAPComponents/PageLayout";
import Teaser from "@ZAPComponents/Teaser";
const Connections = () => {
    const connection = useConnection();
    const { openDrawer, startEdit } = connection;
    return <PageLayout
        title="Connections"
        heading="Connections"
        actions={
            <button
                style={primaryBtn}
                onClick={openDrawer}
            >
                {__("Create credential", "zaplane")}
            </button>
        }
    >
        <Teaser screen="connections" />

        <ConnectionTable onEdit={startEdit} />

        <ConnectionDrawer {...connection} />
    </PageLayout>;
};
export default Connections;