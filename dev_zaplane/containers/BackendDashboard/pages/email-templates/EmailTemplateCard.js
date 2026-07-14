import { __ } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import { FiMail } from "react-icons/fi";
import { IoIosPlay } from "react-icons/io";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import { deleteEmailTemplate } from "@ZAPRedux/Slices/emailTemplatesSlice/emailTemplatesSlice";

const EmailTemplateCard = ({ template }) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();

  const goToEdit = () =>
    navigate(`${route_path}admin.php?page=zaplane-email-templates&action=edit&id=${template.id}`);

  const handleDelete = () => {
    if (window.confirm(__("Are you sure you want to delete this template?", "zaplane"))) {
      dispatch(deleteEmailTemplate(template.id));
    }
  };

  return (
    <div className="flex flex-col rounded-[4px] p-5 bg-[var(--zaplane-background)] border border-[var(--zaplane-border-color)] [box-shadow:var(--zaplane-shadow)] hover:shadow-md hover:border-[var(--zaplane-primary)]/30 transition-all duration-200 group h-full">
      <div className="flex justify-between items-center mb-5">
        <div className="flex items-center justify-center h-9 min-w-[36px] px-2 rounded-full bg-white border border-[var(--zaplane-border-color)] [box-shadow:var(--zaplane-shadow)]">
          <FiMail className="w-[18px] h-[18px] text-[var(--zaplane-primary)]" />
        </div>
        <ZAPMenu
          isIcon
          items={[
            { label: __("Edit", "zaplane"), onClick: goToEdit },
            { label: __("Delete", "zaplane"), onClick: handleDelete },
          ]}
        />
      </div>

      <div className="flex flex-col gap-2 flex-grow">
        <h3 className="zaplane-label truncate" title={template?.title}>
          {template?.title || __("Untitled template", "zaplane")}
        </h3>
        <p className="text-[13px] text-[var(--zaplane-font-secondary-color)] leading-relaxed line-clamp-2 min-h-[38px]" title={template?.subject}>
          {template?.subject || __("No subject", "zaplane")}
        </p>
      </div>

      <div className="mt-5">
        <button
          onClick={goToEdit}
          className="flex w-full items-center justify-center gap-2 px-4 py-2.5 rounded bg-[var(--zaplane-primary)] text-white text-[13px] font-semibold hover:opacity-90 active:opacity-80 transition-all"
        >
          <IoIosPlay size={14} />
          {__("Edit Template", "zaplane")}
        </button>
      </div>
    </div>
  );
};

export default EmailTemplateCard;
