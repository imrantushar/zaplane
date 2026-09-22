import { __ } from "@wordpress/i18n";
import { API, namespace } from "@ZAPUtils/helper";

const base = namespace + "inbox/";

export const inboxApi = {
  list: (params) => API.get(base + "conversations", { params }).then((r) => r.data),
  get: (id) => API.get(base + "conversations/" + id).then((r) => r.data),
  messagesAfter: (id, afterId) =>
    API.get(base + "conversations/" + id + "/messages", { params: { after_id: afterId } }).then((r) => r.data),
  send: (id, body, isNote, replyTo) =>
    API.post(base + "conversations/" + id + "/messages", { body, is_note: !!isNote, reply_to: replyTo || 0 }).then((r) => r.data),
  editMessage: (id, messageId, body) =>
    API.post(base + "conversations/" + id + "/messages/" + messageId, { body }).then((r) => r.data),
  deleteMessage: (id, messageId) => API.delete(base + "conversations/" + id + "/messages/" + messageId).then((r) => r.data),
  update: (id, data) => API.post(base + "conversations/" + id, data).then((r) => r.data),
  markRead: (id) => API.post(base + "conversations/" + id + "/read").then((r) => r.data),
  settings: () => API.get(base + "settings").then((r) => r.data),
  saveSettings: (data) => API.post(base + "settings", data).then((r) => r.data),
  visitors: (countOnly = false) => API.get(base + "visitors" + (countOnly ? "?count_only=1" : "")).then((r) => r.data),
  messageVisitor: (visitorId, body) => API.post(base + "visitors/" + visitorId + "/message", { body }).then((r) => r.data),
  canned: () => API.get(base + "canned-replies").then((r) => r.data),
  saveCanned: (data) => API.post(base + "canned-replies", data).then((r) => r.data),
  deleteCanned: (id) => API.delete(base + "canned-replies/" + id).then((r) => r.data),
  products: (search) => API.get(base + "products", { params: { search } }).then((r) => r.data),
  sendProduct: (id, productId, message, optionId = 0) =>
    API.post(base + "conversations/" + id + "/product", { product_id: productId, option_id: optionId, message }).then((r) => r.data),
  placeOrder: (id, data) => API.post(base + "conversations/" + id + "/order", data).then((r) => r.data),
  testKnowledge: (question) => API.post(base + "knowledge/test", { question }).then((r) => r.data),
  resolveGap: (key, data) => API.post(base + "knowledge/gaps/" + key + "/resolve", data).then((r) => r.data),
  dismissGap: (key) => API.delete(base + "knowledge/gaps/" + key).then((r) => r.data),
  webhookConfig: (slug, values) =>
    API.post(namespace + "incoming/" + slug + "/config", values).then((r) => r.data),
};

/** Only http(s) links are ever rendered. */
export const safeUrl = (url) => (/^https?:\/\//i.test(url || "") ? url : "");

/** "3m", "2h", "Mon", "Sep 4" — short enough for a list row. */
export function shortTime(iso) {
  if (!iso) return "";
  const d = new Date(iso);
  if (isNaN(d)) return "";
  const diff = (Date.now() - d.getTime()) / 1000;
  if (diff < 60) return __("now", "zaplane");
  if (diff < 3600) return Math.floor(diff / 60) + "m";
  if (diff < 86400) return Math.floor(diff / 3600) + "h";
  if (diff < 7 * 86400) return d.toLocaleDateString([], { weekday: "short" });
  return d.toLocaleDateString([], { month: "short", day: "numeric" });
}

export function clockTime(iso) {
  if (!iso) return "";
  const d = new Date(iso);
  return isNaN(d) ? "" : d.toLocaleString([], { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
}

/** Run `fn` every `ms` while the tab is visible; returns a stop function. */
export function visiblePoll(fn, ms) {
  let timer = null;
  let stopped = false;
  const tick = async () => {
    if (stopped) return;
    if (document.visibilityState === "visible") {
      try {
        await fn();
      } catch (e) {
        // A failed poll is retried on the next tick.
      }
    }
    if (!stopped) timer = window.setTimeout(tick, ms);
  };
  timer = window.setTimeout(tick, ms);
  return () => {
    stopped = true;
    window.clearTimeout(timer);
  };
}
