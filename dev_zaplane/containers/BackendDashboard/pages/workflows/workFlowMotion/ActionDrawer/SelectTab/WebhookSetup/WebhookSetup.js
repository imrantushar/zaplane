import { useCallback, useEffect, useMemo, useState } from "react";
import { useDispatch } from "react-redux";
import { __, sprintf } from "@wordpress/i18n";
import { AlertTriangle, Check, RefreshCw } from "lucide-react";
import { API, namespace, rest_url } from "@ZAPUtils/helper";
import { primaryBtn } from "../../../../../../../../../assets/scss/chakra/recipe";
import { showNotification } from "@ZAPRedux/Slices/notificationSlice/notificationSlice";
import CopyInput from "../../ActionFieldRenderer/CopyInput";

/**
 * Setup panel for app triggers that arrive over an incoming webhook.
 *
 * These triggers are delivered by the provider POSTing to this site, so the
 * callback URL and the secrets that guard it have to be reachable from the
 * place the trigger is chosen. Without this panel there was no way to see the
 * URL or set the secrets at all, so Slack/Telegram/Messenger/Mailchimp triggers
 * could never be subscribed and never fired.
 *
 * Saved values are never sent back to the browser — the API reports only
 * whether each one is set.
 */

// Values a user invents rather than copies from the provider; both sides just
// have to match, so we can offer to generate one.
const generateSecret = () => {
  const bytes = new Uint8Array(24);
  window.crypto.getRandomValues(bytes);
  return Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
};

const WebhookSetup = ({ appSlug, selectedIntegration }) => {
  const dispatch = useDispatch();
  const [values, setValues] = useState({});
  const [configured, setConfigured] = useState({});
  const [saving, setSaving] = useState(false);
  const [open, setOpen] = useState(false);

  const fields = useMemo(
    () => selectedIntegration?.webhook_setup ?? [],
    [selectedIntegration]
  );

  // The manifest stores a namespace-relative route, not an absolute URL: it is
  // written to a file at build time, so an absolute URL there would be the
  // build machine's host on every site.
  const webhookUrl = useMemo(() => {
    const route = selectedIntegration?.webhook_route;
    if (!route) return "";
    return `${String(rest_url || "").replace(/\/$/, "")}/${route}`;
  }, [selectedIntegration]);

  useEffect(() => {
    if (!appSlug || !selectedIntegration?.supports_webhook) return;

    let cancelled = false;
    API.get(`${namespace}incoming/${appSlug}/config`)
      .then((res) => {
        if (cancelled) return;
        setConfigured(res?.data?.configured ?? {});
      })
      .catch(() => {
        // A read failure only costs the "configured" badges; the URL and the
        // form still work, so this shouldn't interrupt building the workflow.
      });

    return () => {
      cancelled = true;
    };
  }, [appSlug, selectedIntegration]);

  const handleSave = useCallback(async () => {
    setSaving(true);
    try {
      const res = await API.post(`${namespace}incoming/${appSlug}/config`, values);
      setConfigured(res?.data?.configured ?? {});
      setValues({});
      dispatch(
        showNotification({
          message: __("Webhook settings saved.", "zaplane"),
          isShow: true,
          type: "success",
        })
      );
    } catch (e) {
      dispatch(
        showNotification({
          message:
            e?.response?.data?.message ??
            __("Could not save the webhook settings.", "zaplane"),
          isShow: true,
          type: "error",
        })
      );
    } finally {
      setSaving(false);
    }
  }, [appSlug, values, dispatch]);

  if (!selectedIntegration?.supports_webhook) return null;

  // A required field with nothing stored means the provider will reject the
  // callback URL (or the endpoint stays unauthenticated), so the trigger can
  // never fire. Surface that on the collapsed header rather than only inside.
  const missingRequired = fields.filter(
    (f) => f.required && !configured[f.key] && !values[f.key]
  );
  const callbackHelp =
    "mailchimp" === appSlug
      ? __(
          "Paste this URL into Mailchimp → Audience → Audience settings → Webhooks so it can deliver events here. This trigger cannot fire until the webhook is saved.",
          "zaplane"
        )
      : sprintf(
          /* translators: %s: integration name, e.g. Slack. */
          __(
            "Paste this into %s so it delivers events here. This trigger cannot fire until it does.",
            "zaplane"
          ),
          selectedIntegration?.name ?? appSlug
        );

  return (
    <div className="mt-4 rounded-md border border-solid border-[var(--zaplane-border-color)]">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="w-full flex items-center justify-between gap-2 px-3 py-2 bg-transparent border-0 cursor-pointer"
      >
        <span className="zaplane-label !mb-0">
          {__("Webhook Setup", "zaplane")}
        </span>
        <span className="flex items-center gap-2">
          {missingRequired.length > 0 ? (
            <AlertTriangle
              size={14}
              className="text-[var(--zaplane-warning-color,#b45309)]"
            />
          ) : (
            <Check size={14} className="text-[var(--zaplane-success-color,#15803d)]" />
          )}
          <span className="text-xs text-[var(--zaplane-font-secondary-color)]">
            {open ? __("Hide", "zaplane") : __("Show", "zaplane")}
          </span>
        </span>
      </button>

      {open && (
        <div className="flex flex-col gap-3 px-3 pb-3">
          <CopyInput
            label={__("Callback URL", "zaplane")}
            value={webhookUrl}
            help={callbackHelp}
          />

          {missingRequired.length > 0 && (
            <p className="text-xs text-[var(--zaplane-warning-color,#b45309)] m-0">
              {sprintf(
                /* translators: %s: comma-separated list of setting names. */
                __(
                  "Set %s below before registering the URL — until then the provider's verification is rejected and deliveries are not authenticated.",
                  "zaplane"
                ),
                missingRequired.map((f) => f.label).join(", ")
              )}
            </p>
          )}

          {fields.map((field) => (
            <div key={field.key} className="flex flex-col gap-2">
              <span className="zaplane-label">
                {field.label}
                {field.required && (
                  <span className="text-[var(--zaplane-danger-color,#dc2626)]"> *</span>
                )}
                {configured[field.key] && (
                  <span className="ml-2 text-xs font-normal text-[var(--zaplane-font-secondary-color)]">
                    {__("saved", "zaplane")}
                  </span>
                )}
              </span>
              <div className="flex items-stretch gap-2">
                <input
                  type={field.type === "password" ? "password" : "text"}
                  className="zaplane-input"
                  value={values[field.key] ?? ""}
                  placeholder={
                    configured[field.key]
                      ? __("••••••••  (leave blank to keep)", "zaplane")
                      : ""
                  }
                  onChange={(e) =>
                    setValues((v) => ({ ...v, [field.key]: e.target.value }))
                  }
                />
                {field.generate && (
                  <button
                    type="button"
                    title={__("Generate a value", "zaplane")}
                    onClick={() =>
                      setValues((v) => ({ ...v, [field.key]: generateSecret() }))
                    }
                    className="px-3 border border-solid border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] cursor-pointer rounded-md flex items-center"
                  >
                    <RefreshCw size={14} />
                  </button>
                )}
              </div>
              {field.help && (
                <p className="text-[var(--zaplane-font-secondary-color)] text-xs m-0">
                  {field.help}
                </p>
              )}
            </div>
          ))}

          {fields.length > 0 && (
            <div>
              <button
                type="button"
                disabled={saving || Object.keys(values).length === 0}
                onClick={handleSave}
                style={primaryBtn}
                className="disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {saving ? __("Saving…", "zaplane") : __("Save", "zaplane")}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default WebhookSetup;
