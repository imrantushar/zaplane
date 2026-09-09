import { __ } from "@wordpress/i18n";
import { BookOpen } from "lucide-react";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ConnectionSelector from "./ConnectionSelector/ConnectionSelector";
import WebhookSetup from "./WebhookSetup/WebhookSetup";

const SelectTab = ({
  isTrigger,
  actionOptions,
  values,
  setFieldValue,
  selectedIntegration,
  appSlug,
}) => {
  // One docs page usually covers both halves of an integration; a few split
  // triggers and actions onto separate pages, so this picks the half that
  // matches what's being configured here. Empty means no doc exists yet —
  // hide the link rather than send someone to a 404.
  const docsUrl = isTrigger
    ? selectedIntegration?.docs_url?.trigger
    : selectedIntegration?.docs_url?.action;

  return (

    <>
      <ZAPSelect
        label={
          isTrigger
            ? __("Trigger Type", "zaplane")
            : __("Action Type", "zaplane")
        }
        options={actionOptions}
        value={values?.actionType}
        onChange={(val) => {
          setFieldValue("actionType", val?.value);
          setFieldValue("hook", val?.hook);
        }}
        placeholder={__("Select Action Type", "zaplane")}
        isClearable
        isRequired
        containerStyle={{ marginBottom: "8px" }}
      />
      {docsUrl && (
        <a
          href={docsUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 text-xs text-[var(--zaplane-primary-color)] no-underline hover:underline"
          style={{ marginTop: "-4px", marginBottom: "8px" }}
        >
          <BookOpen size={12} />
          {isTrigger
            ? __("View trigger docs", "zaplane")
            : __("View action docs", "zaplane")}
        </a>
      )}
      {selectedIntegration?.requires_connection === true && (
        <ConnectionSelector
          appSlug={appSlug}
          values={values}
          setFieldValue={setFieldValue}
          selectedIntegration={selectedIntegration}
        />
      )}
      {/*
        Webhook-delivered triggers can only fire once the provider is pointed at
        this site's callback URL and the handshake secret matches. Both belong
        next to the trigger being configured — there is nowhere else to set them.
      */}
      {isTrigger && (
        <WebhookSetup
          appSlug={appSlug}
          selectedIntegration={selectedIntegration}
        />
      )}
    </>
  );
};

export default SelectTab;
