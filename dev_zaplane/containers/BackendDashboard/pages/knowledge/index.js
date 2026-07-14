import { useEffect, useState, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import { FiEdit2, FiTrash2, FiX } from "react-icons/fi";
import PageLayout from "@ZAPComponents/PageLayout";
import Search from "@ZAPComponents/Search";
import ListTable from "@ZAPComponents/ListTable";
import OptionMenu from "@ZAPComponents/OptionMenu";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPDrawer from "@ZAPComponents/Drawer";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { primaryBtn, outlineBtn } from "../../../../../assets/scss/chakra/recipe";
import { API, namespace } from "@ZAPUtils/helper";

const emptyForm = { id: 0, business_key: "", title: "", content: "" };

const KnowledgePage = () => {
  const [businesses, setBusinesses] = useState([]);
  const [business, setBusiness] = useState("");
  const [search, setSearch] = useState("");
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [form, setForm] = useState(null); // null = closed; object = add/edit
  const [faq, setFaq] = useState(null); // null = closed; { business_key, rows:[{q,a}] }
  const [sync, setSync] = useState(null); // null = closed; sync-content config
  const [postTypes, setPostTypes] = useState([]);
  const [statuses, setStatuses] = useState([]);

  const loadPostTypes = useCallback(async () => {
    try {
      const res = await API.get(namespace + "knowledge/post-types");
      setPostTypes(res?.data?.post_types || []);
      setStatuses(res?.data?.statuses || []);
    } catch (e) {
      setPostTypes([]);
      setStatuses([]);
    }
  }, []);

  const loadBusinesses = useCallback(async () => {
    try {
      const res = await API.get(namespace + "knowledge/businesses");
      setBusinesses(res?.data?.businesses || []);
    } catch (e) {
      setBusinesses([]);
    }
  }, []);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await API.get(namespace + "knowledge", {
        params: { business_key: business, search, per_page: 100 },
      });
      setItems(res?.data?.items || []);
    } catch (e) {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [business, search]);

  useEffect(() => {
    loadBusinesses();
    loadPostTypes();
  }, [loadBusinesses, loadPostTypes]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const openAdd = () =>
    setForm({ ...emptyForm, business_key: business || "" });
  const openEdit = (it) =>
    setForm({ id: it.id, business_key: it.business_key, title: it.title || "", content: it.content || "" });
  const closeForm = () => setForm(null);

  const save = async () => {
    if (!form.business_key.trim() || !form.content.trim()) {
      window.alert(__("Business key and content are required.", "zaplane"));
      return;
    }
    setSaving(true);
    try {
      if (form.id) {
        await API.post(namespace + "knowledge/" + form.id, {
          business_key: form.business_key,
          title: form.title,
          content: form.content,
        });
      } else {
        await API.post(namespace + "knowledge", {
          business_key: form.business_key,
          title: form.title,
          content: form.content,
        });
      }
      closeForm();
      await loadBusinesses();
      await loadItems();
    } catch (e) {
      window.alert(__("Failed to save entry.", "zaplane"));
    } finally {
      setSaving(false);
    }
  };

  const openFaq = () =>
    setFaq({ business_key: business || "", rows: [{ q: "", a: "" }, { q: "", a: "" }] });
  const closeFaq = () => setFaq(null);
  const setFaqRow = (i, field, val) =>
    setFaq((f) => ({ ...f, rows: f.rows.map((r, idx) => (idx === i ? { ...r, [field]: val } : r)) }));
  const addFaqRow = () => setFaq((f) => ({ ...f, rows: [...f.rows, { q: "", a: "" }] }));
  const removeFaqRow = (i) =>
    setFaq((f) => ({ ...f, rows: f.rows.filter((_, idx) => idx !== i) }));

  const saveFaqs = async () => {
    if (!faq.business_key.trim()) {
      window.alert(__("Business key is required.", "zaplane"));
      return;
    }
    const entries = faq.rows
      .filter((r) => r.q.trim() && r.a.trim())
      .map((r) => ({ title: r.q.trim(), content: r.a.trim() }));
    if (!entries.length) {
      window.alert(__("Add at least one question and answer.", "zaplane"));
      return;
    }
    setSaving(true);
    try {
      const res = await API.post(namespace + "knowledge/bulk", {
        business_key: faq.business_key.trim(),
        source: "faq",
        entries,
      });
      window.alert(__("Added", "zaplane") + " " + (res?.data?.created ?? 0) + " " + __("FAQs", "zaplane"));
      if (!business) setBusiness(faq.business_key.trim());
      closeFaq();
      await loadBusinesses();
      await loadItems();
    } catch (e) {
      window.alert(__("Failed to save FAQs.", "zaplane"));
    } finally {
      setSaving(false);
    }
  };

  const openSync = () =>
    setSync({
      business_key: business || "",
      post_type: "",
      post_status: "publish",
      include_content: true,
      include_excerpt: true,
      meta_keys: "",
      taxonomies: [],
      prune: true,
    });
  const closeSync = () => setSync(null);

  const runSync = async () => {
    if (!sync.business_key.trim()) {
      window.alert(__("Business key is required.", "zaplane"));
      return;
    }
    if (!sync.post_type) {
      window.alert(__("Choose a post type to sync.", "zaplane"));
      return;
    }
    setSyncing(true);
    try {
      const res = await API.post(namespace + "knowledge/sync-content", {
        business_key: sync.business_key.trim(),
        post_type: sync.post_type,
        post_status: sync.post_status || "publish",
        include_content: sync.include_content ? "yes" : "no",
        include_excerpt: sync.include_excerpt ? "yes" : "no",
        meta_keys: sync.meta_keys,
        taxonomies: (sync.taxonomies || []).join(","),
        prune: sync.prune ? "yes" : "no",
      });
      const synced = res?.data?.synced ?? 0;
      const pruned = res?.data?.pruned ?? 0;
      window.alert(
        __("Synced", "zaplane") + " " + synced + " " + __("items", "zaplane") +
        (pruned ? " · " + __("removed", "zaplane") + " " + pruned + " " + __("deleted", "zaplane") : "")
      );
      const target = sync.business_key.trim();
      if (!business) setBusiness(target);
      closeSync();
      await loadBusinesses();
      await loadItems();
    } catch (e) {
      window.alert(e?.response?.data?.message || __("Sync failed.", "zaplane"));
    } finally {
      setSyncing(false);
    }
  };

  const remove = async (it) => {
    if (!window.confirm(__("Delete this entry?", "zaplane"))) return;
    try {
      await API.delete(namespace + "knowledge/" + it.id);
      await loadItems();
    } catch (e) {
      window.alert(__("Failed to delete entry.", "zaplane"));
    }
  };

  const businessOptions = businesses.map((b) => ({ label: b, value: b }));
  const postTypeOptions = postTypes.map((p) => ({
    label: p.label + " (" + p.count + ")",
    value: p.slug,
  }));
  const statusOptions = statuses.map((s) => ({ label: s.label, value: s.value }));
  const selectedPostType = sync ? postTypes.find((p) => p.slug === sync.post_type) : null;
  const taxonomyOptions = (selectedPostType?.taxonomies || []).map((t) => ({
    label: t.label,
    value: t.slug,
  }));

  const columns = [
    {
      name: <span>{__("Business", "zaplane")}</span>,
      cell: (row) => <ZAPLabel label={row.business_key} type="simple" />,
      textAlign: "start",
    },
    {
      name: <span>{__("Title", "zaplane")}</span>,
      cell: (row) => <ZAPLabel label={row.title || "—"} type="title" />,
      textAlign: "start",
    },
    {
      name: <span>{__("Content", "zaplane")}</span>,
      cell: (row) => (
        <ZAPLabel
          label={(row.content || "").slice(0, 140) + ((row.content || "").length > 140 ? "…" : "")}
          type="subtitle"
        />
      ),
      textAlign: "start",
    },
    {
      name: <span>{__("Action", "zaplane")}</span>,
      cell: (row) => (
        <OptionMenu
          options={[
            {
              label: __("Edit", "zaplane"),
              icon: <FiEdit2 />,
              type: "button",
              onClick: () => openEdit(row),
            },
            {
              label: __("Delete", "zaplane"),
              icon: <FiTrash2 />,
              type: "button",
              suffix: "trash",
              hasBorder: false,
              onClick: () => remove(row),
            },
          ]}
        />
      ),
      textAlign: "center",
    },
  ];

  const actions = (
    <div className="flex items-center gap-2">
      <button type="button" style={outlineBtn} onClick={openSync} disabled={syncing}>
        {syncing ? __("Syncing...", "zaplane") : __("Sync Content", "zaplane")}
      </button>
      <button type="button" style={outlineBtn} onClick={openFaq}>
        {__("FAQ Builder", "zaplane")}
      </button>
      <button type="button" style={primaryBtn} onClick={openAdd}>
        {__("Add Entry", "zaplane")}
      </button>
    </div>
  );

  return (
    <PageLayout
      title={__("Business Knowledge", "zaplane")}
      heading={__("Business Knowledge", "zaplane")}
      actions={actions}
    >
      <ListTable
        columns={columns}
        data={items}
        isRowSelectable={false}
        showSubHeader
        subHeaderComponent={
          <div className="flex items-center gap-3 w-full">
            <div className="min-w-[220px]">
              <ZAPSelect
                options={businessOptions}
                value={business}
                onChange={(opt) => setBusiness(opt?.value || "")}
                placeholder={__("All businesses", "zaplane")}
                isClearable
              />
            </div>
            <Search placeholder={__("Search knowledge...", "zaplane")} onSearchHandler={setSearch} debounce={500} />
          </div>
        }
        showColumnFilter={false}
        showPagination={false}
        noDataText={
          search || business
            ? __("Nothing matches your filter.", "zaplane")
            : __("Add products, prices, and FAQs so the AI can answer from your business data.", "zaplane")
        }
        totalItems={items.length}
        dataFetchingStatus={loading}
        suffix="knowledge-table"
      />

      <ZAPDrawer
        open={!!form}
        onClose={closeForm}
        title={form?.id ? __("Edit Entry", "zaplane") : __("Add Entry", "zaplane")}
        size="md"
        placement="end"
        closeOnOverlayClick
        footer={
          <div className="flex items-center justify-end gap-3">
            <button type="button" style={outlineBtn} onClick={closeForm}>
              {__("Cancel", "zaplane")}
            </button>
            <button type="button" style={primaryBtn} onClick={save} disabled={saving}>
              {saving ? __("Saving...", "zaplane") : __("Save", "zaplane")}
            </button>
          </div>
        }
      >
        {form && (
          <div className="zaplane-knowledge-page flex flex-col gap-4 pt-2">
            <ZAPInput
              label={__("Business Key", "zaplane")}
              placeholder="business_a"
              value={form.business_key}
              onChange={(e) => setForm({ ...form, business_key: e.target.value })}
              isRequired
            />
            <ZAPInput
              label={__("Title", "zaplane")}
              placeholder={__("e.g. Blue Widget / Returns policy", "zaplane")}
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
            />
            <ZAPInput
              type="textarea"
              label={__("Content", "zaplane")}
              placeholder={__("Product name + price + details, or an FAQ/policy answer.", "zaplane")}
              value={form.content}
              onChange={(e) => setForm({ ...form, content: e.target.value })}
              isRequired
              inputStyle={{ minHeight: "140px" }}
            />
          </div>
        )}
      </ZAPDrawer>

      <ZAPDrawer
        open={!!faq}
        onClose={closeFaq}
        title={__("FAQ Builder", "zaplane")}
        size="md"
        placement="end"
        closeOnOverlayClick
        footer={
          <div className="flex items-center justify-end gap-3">
            <button type="button" style={outlineBtn} onClick={closeFaq}>
              {__("Cancel", "zaplane")}
            </button>
            <button type="button" style={primaryBtn} onClick={saveFaqs} disabled={saving}>
              {saving ? __("Saving...", "zaplane") : __("Save FAQs", "zaplane")}
            </button>
          </div>
        }
      >
        {faq && (
          <div className="zaplane-knowledge-page flex flex-col gap-4 pt-2">
            <p className="text-sm" style={{ color: "var(--zaplane-font-secondary-color)" }}>
              {__("Add questions and answers — each pair is saved as its own searchable entry.", "zaplane")}
            </p>
            <ZAPInput
              label={__("Business Key", "zaplane")}
              placeholder="business_a"
              value={faq.business_key}
              onChange={(e) => setFaq({ ...faq, business_key: e.target.value })}
              isRequired
            />
            <div className="flex flex-col gap-4">
              {faq.rows.map((row, i) => (
                <div
                  key={i}
                  className="flex flex-col gap-2 p-3 rounded-[6px]"
                  style={{ border: "1px solid var(--zaplane-border-color)" }}
                >
                  <div className="flex items-center justify-between">
                    <span className="zaplane-label">
                      {__("FAQ", "zaplane")} {i + 1}
                    </span>
                    <button
                      type="button"
                      onClick={() => removeFaqRow(i)}
                      title={__("Remove", "zaplane")}
                      className="text-red-600"
                      style={{ background: "transparent", border: "none", cursor: "pointer" }}
                    >
                      <FiX />
                    </button>
                  </div>
                  <ZAPInput
                    placeholder={__("Question", "zaplane")}
                    value={row.q}
                    onChange={(e) => setFaqRow(i, "q", e.target.value)}
                  />
                  <ZAPInput
                    type="textarea"
                    placeholder={__("Answer", "zaplane")}
                    value={row.a}
                    onChange={(e) => setFaqRow(i, "a", e.target.value)}
                  />
                </div>
              ))}
            </div>
            <button type="button" style={outlineBtn} onClick={addFaqRow}>
              {__("+ Add another", "zaplane")}
            </button>
          </div>
        )}
      </ZAPDrawer>

      <ZAPDrawer
        open={!!sync}
        onClose={closeSync}
        title={__("Sync Content", "zaplane")}
        size="md"
        placement="end"
        closeOnOverlayClick
        footer={
          <div className="flex items-center justify-end gap-3">
            <button type="button" style={outlineBtn} onClick={closeSync}>
              {__("Cancel", "zaplane")}
            </button>
            <button type="button" style={primaryBtn} onClick={runSync} disabled={syncing}>
              {syncing ? __("Syncing...", "zaplane") : __("Sync", "zaplane")}
            </button>
          </div>
        }
      >
        {sync && (
          <div className="zaplane-knowledge-page flex flex-col gap-4 pt-2">
            <p className="text-sm" style={{ color: "var(--zaplane-font-secondary-color)" }}>
              {__("Pull any post type — posts, pages, products, docs — into this business's knowledge. Each item becomes one searchable entry. Re-syncing updates existing entries.", "zaplane")}
            </p>
            <ZAPInput
              label={__("Business Key", "zaplane")}
              placeholder="business_a"
              value={sync.business_key}
              onChange={(e) => setSync({ ...sync, business_key: e.target.value })}
              isRequired
            />
            <div>
              <label className="zaplane-label">{__("Post Type", "zaplane")}</label>
              <ZAPSelect
                options={postTypeOptions}
                value={sync.post_type}
                onChange={(opt) =>
                  setSync({ ...sync, post_type: opt?.value || "", taxonomies: [] })
                }
                placeholder={__("Choose a post type", "zaplane")}
              />
            </div>
            <div>
              <label className="zaplane-label">{__("Status", "zaplane")}</label>
              <ZAPSelect
                options={statusOptions}
                value={sync.post_status}
                onChange={(opt) => setSync({ ...sync, post_status: opt?.value || "publish" })}
                placeholder={__("Choose a status", "zaplane")}
              />
            </div>
            <div className="flex items-center gap-6">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={sync.include_content}
                  onChange={(e) => setSync({ ...sync, include_content: e.target.checked })}
                />
                <span className="zaplane-label">{__("Include body content", "zaplane")}</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={sync.include_excerpt}
                  onChange={(e) => setSync({ ...sync, include_excerpt: e.target.checked })}
                />
                <span className="zaplane-label">{__("Include excerpt", "zaplane")}</span>
              </label>
            </div>
            <ZAPInput
              label={__("Custom fields (optional)", "zaplane")}
              placeholder="price, sku"
              value={sync.meta_keys}
              onChange={(e) => setSync({ ...sync, meta_keys: e.target.value })}
            />
            <div>
              <label className="zaplane-label">{__("Taxonomies (optional)", "zaplane")}</label>
              {sync.post_type && taxonomyOptions.length === 0 ? (
                <p className="text-sm pt-1" style={{ color: "var(--zaplane-font-secondary-color)" }}>
                  {__("This post type has no taxonomies.", "zaplane")}
                </p>
              ) : (
                <ZAPSelect
                  isMulti
                  options={taxonomyOptions}
                  value={sync.taxonomies}
                  onChange={(vals) => setSync({ ...sync, taxonomies: vals || [] })}
                  placeholder={
                    sync.post_type
                      ? __("Select taxonomies to include", "zaplane")
                      : __("Choose a post type first", "zaplane")
                  }
                />
              )}
            </div>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={sync.prune}
                onChange={(e) => setSync({ ...sync, prune: e.target.checked })}
              />
              <span className="zaplane-label">{__("Remove entries whose source item was deleted", "zaplane")}</span>
            </label>
          </div>
        )}
      </ZAPDrawer>
    </PageLayout>
  );
};

export default KnowledgePage;
