import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import { getEmailTemplates } from "@ZAPRedux/Slices/emailTemplatesSlice/emailTemplatesSlice";
import PageLayout from "@ZAPComponents/PageLayout";
import Search from "@ZAPComponents/Search";
import CustomTableMessage from "@ZAPComponents/Oops/CustomTableMessage";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import EmailTemplateCard from "./EmailTemplateCard";

const EmailTemplatesPage = () => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState("");
  const { templates, loading } = useSelector((state) => state.emailTemplates || {});

  useEffect(() => {
    dispatch(getEmailTemplates());
  }, [dispatch]);

  const filtered = searchTerm
    ? (templates || []).filter((t) =>
        (t.title || "").toLowerCase().includes(searchTerm.toLowerCase())
      )
    : (templates || []);

  const createNew = () =>
    navigate(`${route_path}admin.php?page=zaplane-email-templates&action=new`);

  return (
    <PageLayout
      title="Email Templates"
      isLoading={loading}
      actions={
        <div className="flex items-center gap-3">
          <Search placeholder={__("Search templates...", "zaplane")} onSearchHandler={setSearchTerm} />
          <button style={primaryBtn} onClick={createNew}>
            {__("New Template", "zaplane")}
          </button>
        </div>
      }
    >
      {filtered?.length ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 items-start">
          {filtered.map((template) => (
            <EmailTemplateCard key={template.id} template={template} />
          ))}
        </div>
      ) : (
        <CustomTableMessage
          title={__("No email templates yet", "zaplane")}
          subText={
            searchTerm
              ? __("No templates match your search.", "zaplane")
              : __("Create a template to design beautiful emails with the builder.", "zaplane")
          }
        />
      )}
    </PageLayout>
  );
};

export default EmailTemplatesPage;
