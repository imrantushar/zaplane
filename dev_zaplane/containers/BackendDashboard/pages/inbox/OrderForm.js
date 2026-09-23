import { useState } from "react";
import { __ } from "@wordpress/i18n";
import ProductPicker from "./ProductPicker";

/**
 * Place a cash-on-delivery order for the customer in this conversation.
 * Contact details are prefilled and can be corrected before sending.
 */
const OrderForm = ({ conversation, onPlace, onCancel }) => {
  const [lines, setLines] = useState([]);
  const [customer, setCustomer] = useState({
    name: conversation.contact?.name || "",
    phone: conversation.contact?.phone || "",
    email: conversation.contact?.email || "",
    address: "",
    city: "",
    note: "",
  });
  const [notify, setNotify] = useState(true);
  const [picking, setPicking] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  // One line per product and price option: "Kurta (Large)" and "Kurta (Small)" are two lines.
  const keyOf = (p) => p.id + "/" + (p.option_id || 0);
  const addLine = (p) => {
    const k = keyOf(p);
    setLines((ls) => (ls.some((l) => keyOf(l) === k) ? ls.map((l) => (keyOf(l) === k ? { ...l, qty: l.qty + 1 } : l)) : [...ls, { ...p, qty: 1 }]));
    setPicking(false);
  };

  const submit = async (e) => {
    e.preventDefault();
    setError("");
    if (!lines.length) return setError(__("Add at least one product.", "zaplane"));
    if (!customer.phone.trim() || !customer.address.trim()) return setError(__("A phone number and delivery address are needed.", "zaplane"));
    setBusy(true);
    try {
      await onPlace({
        items: lines.map((l) => ({ product_id: l.id, option_id: l.option_id || 0, qty: l.qty })),
        customer,
        notify,
      });
    } catch (err) {
      setError(err?.response?.data?.message || __("The order could not be placed.", "zaplane"));
    } finally {
      setBusy(false);
    }
  };

  const field = (key, label, type = "text") => (
    <label className="zaplane-inbox-field" key={key}>
      <span>{label}</span>
      <input className="zaplane-inbox-input" type={type} value={customer[key]} onChange={(e) => setCustomer({ ...customer, [key]: e.target.value })} />
    </label>
  );

  return (
    <form className="zaplane-inbox-order" onSubmit={submit}>
      <ul className="zaplane-inbox-lines">
        {lines.map((l) => (
          <li key={keyOf(l)}>
            <span className="min-w-0 flex-1">
              <strong>{l.name}</strong>
              {l.option_label && <span className="zaplane-inbox-sub">{l.option_label}</span>}
              <span className="zaplane-inbox-sub">{l.price_text}</span>
            </span>
            <input
              className="zaplane-inbox-input zaplane-inbox-qty"
              type="number"
              min={1}
              max={99}
              value={l.qty}
              aria-label={__("Quantity", "zaplane")}
              onChange={(e) => setLines(lines.map((x) => (keyOf(x) === keyOf(l) ? { ...x, qty: Math.max(1, parseInt(e.target.value, 10) || 1) } : x)))}
            />
            <button type="button" className="zaplane-inbox-link" aria-label={__("Remove", "zaplane") + " " + l.name} onClick={() => setLines(lines.filter((x) => keyOf(x) !== keyOf(l)))}>
              ×
            </button>
          </li>
        ))}
      </ul>
      {picking ? (
        <ProductPicker actionLabel={__("Add", "zaplane")} onPick={addLine} onClose={lines.length ? () => setPicking(false) : undefined} />
      ) : (
        <button type="button" className="zaplane-inbox-small" onClick={() => setPicking(true)}>
          {__("Add a product", "zaplane")}
        </button>
      )}

      {field("name", __("Name", "zaplane"))}
      {field("phone", __("Phone", "zaplane"), "tel")}
      {field("address", __("Delivery address", "zaplane"))}
      {field("city", __("City", "zaplane"))}
      {field("note", __("Note for delivery", "zaplane"))}

      <label className="zaplane-inbox-check">
        <input type="checkbox" checked={notify} onChange={(e) => setNotify(e.target.checked)} />
        <span>{__("Send the order number to the customer", "zaplane")}</span>
      </label>

      {error && <div className="zaplane-inbox-failed">{error}</div>}
      <div className="flex items-center gap-3">
        <button type="submit" className="zaplane-inbox-send" disabled={busy}>
          {busy ? __("Placing…", "zaplane") : __("Place order (cash on delivery)", "zaplane")}
        </button>
        <button type="button" className="zaplane-inbox-link" onClick={onCancel}>
          {__("Cancel", "zaplane")}
        </button>
      </div>
    </form>
  );
};

export default OrderForm;
