import { useEffect, useState, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import PageLayout from "@ZAPComponents/PageLayout";
import Search from "@ZAPComponents/Search";
import CustomTableMessage from "@ZAPComponents/Oops/CustomTableMessage";
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
  }, [loadBusinesses]);

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

  const syncStoreEngine = async () => {
    const target = business || window.prompt(__("Enter the business key to sync StoreEngine products into:", "zaplane"), "");
    if (!target || !target.trim()) return;
    setSyncing(true);
    try {
      const res = await API.post(namespace + "knowledge/sync-storeengine", { business_key: target.trim() });
      const synced = res?.data?.synced ?? 0;
      const pruned = res?.data?.pruned ?? 0;
      window.alert(
        __("Synced", "zaplane") + " " + synced + " " + __("products", "zaplane") +
        (pruned ? " · " + __("removed", "zaplane") + " " + pruned + " " + __("deleted", "zaplane") : "")
      );
      if (!business) setBusiness(target.trim());
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

  const actions = (
    <div className="flex items-center gap-3">
      <select
        value={business}
        onChange={(e) => setBusiness(e.target.value)}
        className="h-9 px-3 rounded-[4px] border border-[var(--zaplane-border-color)] bg-white"
      >
        <option value="">{__("All businesses", "zaplane")}</option>
        {businesses.map((b) => (
          <option key={b} value={b}>
            {b}
          </option>
        ))}
      </select>
      <Search placeholder={__("Search knowledge...", "zaplane")} onSearchHandler={setSearch} />
      <button
        onClick={syncStoreEngine}
        disabled={syncing}
        className="h-9 px-4 rounded-[4px] border border-[var(--zaplane-border-color)] bg-white font-medium hover:opacity-90 disabled:opacity-60"
      >
        {syncing ? __("Syncing...", "zaplane") : __("Sync StoreEngine", "zaplane")}
      </button>
      <button
        onClick={openFaq}
        className="h-9 px-4 rounded-[4px] border border-[var(--zaplane-primary)] text-[var(--zaplane-primary)] font-medium hover:opacity-90"
      >
        {__("FAQ Builder", "zaplane")}
      </button>
      <button
        onClick={openAdd}
        className="h-9 px-4 rounded-[4px] bg-[var(--zaplane-primary)] text-white font-medium hover:opacity-90"
      >
        {__("Add Entry", "zaplane")}
      </button>
    </div>
  );

  return (
    <PageLayout title={__("Business Knowledge", "zaplane")} isLoading={loading} actions={actions}>
      {faq && (
        <div className="mb-5 p-4 border rounded-md bg-[var(--zaplane-gray,#f7f7f8)]">
          <div className="font-bold mb-1">{__("FAQ Builder", "zaplane")}</div>
          <div className="text-sm text-[var(--zaplane-text-secondary,#6b7280)] mb-3">
            {__("Add questions and answers — each pair is saved as its own searchable entry.", "zaplane")}
          </div>
          <div className="mb-3 max-w-[320px]">
            <label className="block text-sm mb-1">{__("Business Key", "zaplane")}</label>
            <input
              type="text"
              value={faq.business_key}
              placeholder="business_a"
              onChange={(e) => setFaq({ ...faq, business_key: e.target.value })}
              className="w-full h-9 px-3 rounded-[4px] border border-[var(--zaplane-border-color)]"
            />
          </div>
          <div className="flex flex-col gap-3">
            {faq.rows.map((row, i) => (
              <div key={i} className="grid grid-cols-1 md:grid-cols-[1fr_2fr_auto] gap-2 items-start">
                <input
                  type="text"
                  value={row.q}
                  placeholder={__("Question", "zaplane")}
                  onChange={(e) => setFaqRow(i, "q", e.target.value)}
                  className="h-9 px-3 rounded-[4px] border border-[var(--zaplane-border-color)]"
                />
                <textarea
                  value={row.a}
                  rows={2}
                  placeholder={__("Answer", "zaplane")}
                  onChange={(e) => setFaqRow(i, "a", e.target.value)}
                  className="p-2 rounded-[4px] border border-[var(--zaplane-border-color)]"
                />
                <button
                  onClick={() => removeFaqRow(i)}
                  title={__("Remove", "zaplane")}
                  className="h-9 px-3 rounded-[4px] border border-[var(--zaplane-border-color)] bg-white text-red-600"
                >
                  ✕
                </button>
              </div>
            ))}
          </div>
          <div className="flex gap-3 mt-3">
            <button
              onClick={addFaqRow}
              className="h-9 px-4 rounded-[4px] border border-[var(--zaplane-border-color)] bg-white"
            >
              {__("+ Add another", "zaplane")}
            </button>
            <button
              onClick={saveFaqs}
              disabled={saving}
              className="h-9 px-4 rounded-[4px] bg-[var(--zaplane-primary)] text-white font-medium hover:opacity-90 disabled:opacity-60"
            >
              {saving ? __("Saving...", "zaplane") : __("Save FAQs", "zaplane")}
            </button>
            <button
              onClick={closeFaq}
              className="h-9 px-4 rounded-[4px] border border-[var(--zaplane-border-color)] bg-white"
            >
              {__("Cancel", "zaplane")}
            </button>
          </div>
        </div>
      )}

      {form && (
        <div className="mb-5 p-4 border rounded-md bg-[var(--zaplane-gray,#f7f7f8)]">
          <div className="font-bold mb-3">
            {form.id ? __("Edit Entry", "zaplane") : __("Add Entry", "zaplane")}
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
            <div>
              <label className="block text-sm mb-1">{__("Business Key", "zaplane")}</label>
              <input
                type="text"
                value={form.business_key}
                placeholder="business_a"
                onChange={(e) => setForm({ ...form, business_key: e.target.value })}
                className="w-full h-9 px-3 rounded-[4px] border border-[var(--zaplane-border-color)]"
              />
            </div>
            <div>
              <label className="block text-sm mb-1">{__("Title", "zaplane")}</label>
              <input
                type="text"
                value={form.title}
                placeholder={__("e.g. Blue Widget / Returns policy", "zaplane")}
                onChange={(e) => setForm({ ...form, title: e.target.value })}
                className="w-full h-9 px-3 rounded-[4px] border border-[var(--zaplane-border-color)]"
              />
            </div>
          </div>
          <label className="block text-sm mb-1">{__("Content", "zaplane")}</label>
          <textarea
            value={form.content}
            rows={5}
            placeholder={__("Product name + price + details, or an FAQ/policy answer.", "zaplane")}
            onChange={(e) => setForm({ ...form, content: e.target.value })}
            className="w-full p-3 rounded-[4px] border border-[var(--zaplane-border-color)] mb-3"
          />
          <div className="flex gap-3">
            <button
              onClick={save}
              disabled={saving}
              className="h-9 px-4 rounded-[4px] bg-[var(--zaplane-primary)] text-white font-medium hover:opacity-90 disabled:opacity-60"
            >
              {saving ? __("Saving...", "zaplane") : __("Save", "zaplane")}
            </button>
            <button
              onClick={closeForm}
              className="h-9 px-4 rounded-[4px] border border-[var(--zaplane-border-color)] bg-white"
            >
              {__("Cancel", "zaplane")}
            </button>
          </div>
        </div>
      )}

      {items?.length ? (
        <div className="overflow-x-auto border rounded-md">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-[var(--zaplane-gray,#f7f7f8)]">
                <th className="p-3 text-sm font-bold">{__("Business", "zaplane")}</th>
                <th className="p-3 text-sm font-bold">{__("Title", "zaplane")}</th>
                <th className="p-3 text-sm font-bold">{__("Content", "zaplane")}</th>
                <th className="p-3 text-sm font-bold w-[120px]">{__("Actions", "zaplane")}</th>
              </tr>
            </thead>
            <tbody>
              {items.map((it) => (
                <tr key={it.id} className="border-t">
                  <td className="p-3 align-top text-sm">{it.business_key}</td>
                  <td className="p-3 align-top text-sm font-medium">{it.title || "—"}</td>
                  <td className="p-3 align-top text-sm text-[var(--zaplane-text-secondary,#6b7280)]">
                    {(it.content || "").slice(0, 140)}
                    {(it.content || "").length > 140 ? "…" : ""}
                  </td>
                  <td className="p-3 align-top">
                    <div className="flex gap-2">
                      <button onClick={() => openEdit(it)} className="text-[var(--zaplane-primary)] text-sm">
                        {__("Edit", "zaplane")}
                      </button>
                      <button onClick={() => remove(it)} className="text-red-600 text-sm">
                        {__("Delete", "zaplane")}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <CustomTableMessage
          title={__("No knowledge entries", "zaplane")}
          subText={
            search || business
              ? __("Nothing matches your filter.", "zaplane")
              : __("Add products, prices, and FAQs so the AI can answer from your business data.", "zaplane")
          }
        />
      )}
    </PageLayout>
  );
};

export default KnowledgePage;
