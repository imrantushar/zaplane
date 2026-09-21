import { __ } from "@wordpress/i18n";
import { FiGlobe, FiMail } from "react-icons/fi";
import { FaFacebookMessenger, FaWhatsapp, FaInstagram, FaTelegramPlane } from "react-icons/fa";

/** How each channel is labelled and marked in the list, thread and details. */
export const CHANNELS = {
  web: { label: __("Website", "zaplane"), Icon: FiGlobe, color: "var(--zaplane-primary)" },
  messenger: { label: __("Messenger", "zaplane"), Icon: FaFacebookMessenger, color: "#0084FF" },
  whatsapp: { label: __("WhatsApp", "zaplane"), Icon: FaWhatsapp, color: "#25D366" },
  instagram: { label: __("Instagram", "zaplane"), Icon: FaInstagram, color: "#E4405F" },
  email: { label: __("Email", "zaplane"), Icon: FiMail, color: "#6B7384" },
  telegram: { label: __("Telegram", "zaplane"), Icon: FaTelegramPlane, color: "#229ED9" },
};

export const channelOf = (slug) => CHANNELS[slug] || { label: slug || "", Icon: FiGlobe, color: "#6B7384" };

export const CHANNEL_LABELS = Object.fromEntries(Object.entries(CHANNELS).map(([k, v]) => [k, v.label]));

export function initials(name) {
  return (name || "?")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join("");
}

// A steady colour per contact, so the same person is easy to spot again.
const TINTS = ["#006BFF", "#7C3AED", "#0E9F6E", "#D97706", "#DB2777", "#0891B2", "#4F46E5", "#B45309"];
export function tintFor(seed) {
  let h = 0;
  for (const ch of String(seed || "")) h = (h * 31 + ch.charCodeAt(0)) | 0;
  return TINTS[Math.abs(h) % TINTS.length];
}

/** Avatar with the channel's mark in the corner. */
export const Avatar = ({ contact, channel, size = "md" }) => {
  const { Icon, color, label } = channelOf(channel);
  const tint = tintFor(contact?.id || contact?.name);
  return (
    <span className={"zaplane-inbox-avatar is-" + size} aria-hidden="true" style={{ "--tint": tint }}>
      {contact?.avatar_url ? <img src={contact.avatar_url} alt="" /> : <span>{initials(contact?.name)}</span>}
      {channel && (
        <span className="zaplane-inbox-avatar-channel" style={{ color }} title={label}>
          <Icon />
        </span>
      )}
    </span>
  );
};

/** "Today", "Yesterday", or a date — for the separators between messages. */
export function dayLabel(iso) {
  const d = new Date(iso);
  if (isNaN(d)) return "";
  const today = new Date();
  const start = (x) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
  const days = Math.round((start(today) - start(d)) / 86400000);
  if (days === 0) return __("Today", "zaplane");
  if (days === 1) return __("Yesterday", "zaplane");
  return d.toLocaleDateString([], { weekday: "long", month: "short", day: "numeric", year: d.getFullYear() === today.getFullYear() ? undefined : "numeric" });
}

export function hourTime(iso) {
  const d = new Date(iso);
  return isNaN(d) ? "" : d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
}
