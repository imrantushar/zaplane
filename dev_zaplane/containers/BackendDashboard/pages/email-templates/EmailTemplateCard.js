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
    <div className="flex flex-col rounded-xl p-5 bg-[var(--zaplane-background)] border border-[var(--zaplane-border-color)] shadow-sm hover:shadow-md transition-all duration-200 group h-full">
      <div className="flex justify-between items-center mb-4">
        <div className="flex items-center justify-center w-10 h-10 rounded-[8px] bg-[var(--zaplane-second-primary)]">
          <FiMail className="w-5 h-5 text-[var(--zaplane-primary)]" />
        </div>
        <ZAPMenu
          isIcon
          items={[
            { label: __("Edit", "zaplane"), onClick: goToEdit },
            { label: __("Delete", "zaplane"), onClick: handleDelete },
          ]}
        />
      </div>

      <div className="border-t border-[var(--zaplane-border-color)] -mx-5 mb-4 opacity-60" />

      <div className="flex flex-col gap-1 flex-grow">
        <h3 className="zaplane-label" title={template?.title}>
          {template?.title || __("Untitled template", "zaplane")}
        </h3>
        {template?.subject && (
          <p className="text-[13px] text-[var(--zaplane-font-secondary-color)] line-clamp-2" title={template.subject}>
            {template.subject}
          </p>
        )}
      </div>

      <div className="mt-5">
        <button
          onClick={goToEdit}
          className="flex w-full items-center justify-center gap-2 px-4 py-2 rounded-md border border-[var(--zaplane-border-color)] text-[13px] font-semibold text-[var(--zaplane-primary)] hover:bg-[var(--zaplane-second-primary)] hover:border-[var(--zaplane-primary)] transition-all"
        >
          <IoIosPlay size={14} />
          {__("Edit Template", "zaplane")}
        </button>
      </div>
    </div>
  );
};

export default EmailTemplateCard;
