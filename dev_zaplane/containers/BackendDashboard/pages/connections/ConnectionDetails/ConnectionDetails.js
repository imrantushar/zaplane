import WPModal from "@ZAPComponents/Modal/WPModal";
import { __ } from "@wordpress/i18n";
const ConnectionDetails = ({
  isOpen,
  onClose,
  connection
}) => {
  return <WPModal title={__("Connection Details", "zaplane")} isOpen={isOpen} onRequestClose={onClose}>
      {!connection ? <div justify="center" align="center" className="flex py-12">
          <div size="lg" />
        </div> : <div>
          <div borderWidth="1px" boxShadow="sm" className="p-5 rounded-[lg] bg-var(--zaplane-background) mb-5">
            <div justify="space-between" align="center" className="flex">
              <div>
                <span className="zaplane-label text-[xl] font-[semibold]">
                  {__(connection.name, "zaplane")}
                </span>
                <span className="zaplane-label text-[sm] text-gray-500">
                  {__(connection.app, "zaplane")}
                </span>
              </div>
              <span colorPalette={connection.status === "active" ? "green" : "gray"} textTransform="capitalize" className="px-4 py-1.5 text-[sm] rounded-full">
                {__(connection.status, "zaplane")}
              </span>
            </div>
          </div>
          <div gap={4} wrap="wrap" className="flex">
            <div borderWidth="1px" className="flex-[1 1 45%] p-4 rounded-[lg] bg-gray-50">
              <span>
                {__('AUTH TYPE', 'zaplane')}
              </span>
              <span className="zaplane-label text-[md] font-[medium]">
                {__(connection.auth_type, 'zaplane')}
              </span>
            </div>

            <div borderWidth="1px" className="flex-[1 1 45%] p-4 rounded-[lg] bg-gray-50">
              <span>
                {__('CREATED AT', 'zaplane')}
              </span>
              <span className="zaplane-label text-[md] font-[medium]">
                {__(connection.created_at, "zaplane")}
              </span>
            </div>

            <div borderWidth="1px" className="flex-[1 1 45%] p-4 rounded-[lg] bg-gray-50">
              <span>
                {__('LAST USED', 'zaplane')}
              </span>
              <span className="zaplane-label text-[md] font-[medium]">
                {__(connection.last_used_at || "--", "zaplane")}
              </span>
            </div>

            <div borderWidth="1px" className="flex-[1 1 45%] p-4 rounded-[lg] bg-gray-50">
              <span>
                {__('LAST TESTED', 'zaplane')}
              </span>
              <span className="zaplane-label text-[md] font-[medium]">
                {connection.last_tested_at || "--"}
              </span>
            </div>
          </div>
        </div>}
    </WPModal>;
};
export default ConnectionDetails;