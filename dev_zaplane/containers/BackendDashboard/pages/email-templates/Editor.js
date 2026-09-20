import { useEffect, useMemo, useState } from "react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import PageLayout from "@ZAPComponents/PageLayout";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPLoading from "@ZAPComponents/Loading";
import EmbEmailBuilder from "@ZAPComponents/EmbEmailBuilder";
import { primaryBtn, outlineBtn } from "../../../../../assets/scss/chakra/recipe";
import {
  getEmailTemplate,
  createEmailTemplate,
  updateEmailTemplate,
  clearCurrentTemplate,
} from "@ZAPRedux/Slices/emailTemplatesSlice/emailTemplatesSlice";

const listUrl = `${route_path}admin.php?page=zaplane-email-templates`;

const EmailTemplateEditor = ({ id }) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const isNew = !id;
  const { current, loadingCurrent } = useSelector((state) => state.emailTemplates || {});

  const [title, setTitle] = useState("");
  const [subject, setSubject] = useState("");
  const [content, setContent] = useState(null); // builder JSON tree string
  const [saving, setSaving] = useState(false);
  const [ready, setReady] = useState(isNew);

  useEffect(() => {
    if (isNew) {
      dispatch(clearCurrentTemplate());
      setReady(true);
      return;
    }
    dispatch(getEmailTemplate(id));
  }, [dispatch, id, isNew]);

  // Hydrate local state once the template loads (edit mode).
  useEffect(() => {
    if (isNew || !current) return;
    setTitle(current.title || "");
    setSubject(current.subject || "");
    setContent(current.content || null);
    setReady(true);
  }, [current, isNew]);

  // The builder reads its tree once at mount, so key it by the loaded id.
  const builderKey = useMemo(() => (isNew ? "new" : `tpl-${id}`), [isNew, id]);

  const handleSave = async () => {
    setSaving(true);
    const payload = {
      title: title?.trim() || __("Untitled template", "zaplane"),
      subject: subject?.trim() || "",
      content: content || "",
    };
    try {
      if (isNew) {
        const created = await dispatch(createEmailTemplate(payload)).unwrap();
        if (created?.id) {
          navigate(`${listUrl}&action=edit&id=${created.id}`);
        }
      } else {
        await dispatch(updateEmailTemplate({ id, payload })).unwrap();
      }
    } catch (e) {
      /* error surfaced via notification */
    }
    setSaving(false);
  };

  if (!ready || (!isNew && loadingCurrent)) {
    return <ZAPLoading />;
  }

  return (
    <PageLayout
      breadcrumbs={[
        { label: "Email Templates", href: listUrl },
        { label: isNew ? "New Template" : title || "Edit Template" },
      ]}
      heading={isNew ? __("New Template", "zaplane") : title || __("Edit Template", "zaplane")}
      actions={
        <div className="flex items-center gap-3">
          <button style={outlineBtn} onClick={() => navigate(listUrl)}>
            {__("Back", "zaplane")}
          </button>
          <button style={primaryBtn} onClick={handleSave} disabled={saving}>
            {saving ? __("Saving...", "zaplane") : __("Save Template", "zaplane")}
          </button>
        </div>
      }
    >
      <div className="flex flex-col gap-5">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <ZAPInput
            label={__("Template name", "zaplane")}
            placeholder={__("e.g. Welcome email", "zaplane")}
            value={title}
            onChange={(e) => setTitle(e.target.value)}
          />
          <ZAPInput
            label={__("Subject (optional default)", "zaplane")}
            placeholder={__("Default subject line", "zaplane")}
            value={subject}
            onChange={(e) => setSubject(e.target.value)}
          />
        </div>

        <div className="rounded-xl border border-[var(--zaplane-border-color)] overflow-hidden bg-[var(--zaplane-background)]">
          <EmbEmailBuilder
            key={builderKey}
            source={content}
            onChange={({ tree }) => setContent(JSON.stringify(tree))}
          />
        </div>
      </div>
    </PageLayout>
  );
};

export default EmailTemplateEditor;
