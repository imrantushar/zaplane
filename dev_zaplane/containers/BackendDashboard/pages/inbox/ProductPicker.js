import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import { FiShoppingBag } from "react-icons/fi";
import { inboxApi, safeUrl } from "./api";

/** The product as it will be sent: priced with the chosen option. */
const withOption = (p, optionId) => {
  const o = (p.options || []).find((x) => x.id === optionId);
  return o ? { ...p, option_id: o.id, option_label: o.label, price: o.price, price_text: o.price_text, compare_text: o.compare_text, in_stock: o.in_stock } : p;
};

const Row = ({ p, actionLabel, onPick }) => {
  const options = p.options || [];
  const [optionId, setOptionId] = useState(p.option_id || 0);
  const shown = withOption(p, optionId);
  return (
    <li>
      <span className="zaplane-inbox-thumb">{safeUrl(p.image) ? <img src={p.image} alt="" /> : <FiShoppingBag />}</span>
      <span className="min-w-0 flex-1">
        <strong>{p.name}</strong>
        <span className="zaplane-inbox-sub">
          {shown.price_text}
          {shown.compare_text && <s className="zaplane-inbox-was">{shown.compare_text}</s>}
          {!shown.in_stock && " · " + __("Out of stock", "zaplane")}
          {options.length > 1 && !shown.option_label && " · " + options.length + " " + __("options", "zaplane")}
        </span>
        {options.length > 1 && (
          <select
            className="zaplane-inbox-select zaplane-inbox-option-select"
            value={optionId}
            onChange={(e) => setOptionId(parseInt(e.target.value, 10))}
            aria-label={__("Price option", "zaplane") + " — " + p.name}
          >
            {options.map((o) => (
              <option key={o.id} value={o.id}>
                {o.label === o.price_text ? o.price_text : o.label + " — " + o.price_text}
                {o.in_stock ? "" : " (" + __("out of stock", "zaplane") + ")"}
              </option>
            ))}
          </select>
        )}
      </span>
      <button type="button" className="zaplane-inbox-small" onClick={() => onPick(shown)}>
        {actionLabel}
      </button>
    </li>
  );
};

/**
 * Search the shop and hand one product back. Used to send a card from the
 * reply box and to add lines to an order.
 */
const ProductPicker = ({ onPick, actionLabel, onClose }) => {
  const [search, setSearch] = useState("");
  const [state, setState] = useState({ loading: true, store: null, products: [] });

  useEffect(() => {
    let live = true;
    const t = window.setTimeout(async () => {
      try {
        const res = await inboxApi.products(search);
        if (live) setState({ loading: false, store: res.store, products: res.products || [] });
      } catch (e) {
        if (live) setState({ loading: false, store: null, products: [] });
      }
    }, 250);
    return () => {
      live = false;
      window.clearTimeout(t);
    };
  }, [search]);

  return (
    <div className="zaplane-inbox-picker" role="dialog" aria-label={__("Choose a product", "zaplane")}>
      <div className="flex items-center gap-2">
        <input
          className="zaplane-inbox-input"
          autoFocus
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder={__("Search products…", "zaplane")}
          aria-label={__("Search products", "zaplane")}
          onKeyDown={(e) => e.key === "Escape" && onClose?.()}
        />
        {onClose && (
          <button type="button" className="zaplane-inbox-link" onClick={onClose}>
            {__("Close", "zaplane")}
          </button>
        )}
      </div>
      {!state.loading && !state.store && (
        <p className="zaplane-inbox-hint">{__("No shop is active. Activate StoreEngine or WooCommerce to sell from the inbox.", "zaplane")}</p>
      )}
      {!state.loading && state.store && state.products.length === 0 && (
        <p className="zaplane-inbox-hint">{__("No products match.", "zaplane")}</p>
      )}
      <ul>
        {state.products.map((p) => (
          <Row key={p.id} p={p} actionLabel={actionLabel} onPick={onPick} />
        ))}
      </ul>
    </div>
  );
};

export default ProductPicker;
