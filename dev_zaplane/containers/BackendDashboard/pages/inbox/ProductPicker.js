import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import { inboxApi, safeUrl } from "./api";

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
          <li key={p.id}>
            <span className="zaplane-inbox-thumb">{safeUrl(p.image) && <img src={p.image} alt="" />}</span>
            <span className="min-w-0 flex-1">
              <strong>{p.name}</strong>
              <span className="zaplane-inbox-sub">
                {p.price_text}
                {!p.in_stock && " · " + __("Out of stock", "zaplane")}
              </span>
            </span>
            <button type="button" className="zaplane-inbox-small" onClick={() => onPick(p)}>
              {actionLabel}
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
};

export default ProductPicker;
