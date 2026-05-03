import WPModal from "@ZAPComponents/Modal/WPModal";
import { __ } from "@wordpress/i18n";
const ConnectionDetails = ({
  isOpen,
  onClose,
  connection
}) => {
  return <WPModal title={__("Connection Details", "zaplane")} isOpen={isOpen} onRequestClose={onClose} size="medium">
      {!connection ? (
        <div className="flex justify-center items-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#6366F1]" />
        </div>
      ) : (
        <div className="flex flex-col gap-6">
          {/* Hero Section */}
          <div className="p-6 rounded-[12px] bg-white border border-[#E5E7EB] shadow-sm">
            <div className="flex justify-between items-center">
              <div className="flex flex-col gap-1">
                <h3 className="text-[18px] font-bold text-[#111827]">
                  {__(connection.name, "zaplane")}
                </h3>
                <span className="text-[14px] text-[#6B7280] font-medium uppercase tracking-wider">
                  {__(connection.app, "zaplane")}
                </span>
              </div>
              <span className={`px-4 py-1.5 text-[12px] font-bold rounded-full uppercase tracking-wide ${
                connection.status === "active" 
                  ? "bg-[#ECFDF5] text-[#10B981] border border-[#A7F3D0]" 
                  : "bg-[#F3F4F6] text-[#6B7280] border border-[#E5E7EB]"
              }`}>
                {__(connection.status, "zaplane")}
              </span>
            </div>
          </div>

          {/* Details Grid */}
          <div className="grid grid-cols-2 gap-4">
            {[
              { label: __('AUTH TYPE', 'zaplane'), value: connection.auth_type },
              { label: __('CREATED AT', 'zaplane'), value: connection.created_at },
              { label: __('LAST USED', 'zaplane'), value: connection.last_used_at || "--" },
              { label: __('LAST TESTED', 'zaplane'), value: connection.last_tested_at || "--" },
            ].map((item, idx) => (
              <div key={idx} className="p-5 rounded-[12px] bg-[#F9FAFB] border border-[#F3F4F6] flex flex-col gap-2">
                <span className="text-[11px] font-bold text-[#9CA3AF] uppercase tracking-widest">
                  {item.label}
                </span>
                <span className="text-[14px] font-semibold text-[#374151]">
                  {__(item.value, 'zaplane')}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </WPModal>;
};
export default ConnectionDetails;