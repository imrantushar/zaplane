import { useCallback, useEffect, useRef, useState } from "react";
import { __ } from "@wordpress/i18n";
import PageLayout from "@ZAPComponents/PageLayout";
import { primaryBtn, outlineBtn } from "../../../../../assets/scss/chakra/recipe";
import ConversationList from "./ConversationList";
import Thread from "./Thread";
import Details from "./Details";
import Settings from "./Settings";
import { inboxApi, visiblePoll } from "./api";
import "./styles.scss";

const LIST_POLL = 5000;
const THREAD_POLL = 3000;

const InboxPage = () => {
  const [view, setView] = useState("inbox");
  const [filters, setFilters] = useState({ status: "open", assignee: "any", search: "" });
  const [list, setList] = useState({ items: [], counts: {}, loading: true });
  const [activeId, setActiveId] = useState(0);
  const [thread, setThread] = useState({ conversation: null, messages: [], other: [] });
  const [meta, setMeta] = useState({ team: [], canned: [], aiReady: false, widgetOn: false, store: null, channelsOn: false });
  const [sending, setSending] = useState(false);
  const lastIdRef = useRef(0);

  const loadMeta = useCallback(async () => {
    const [s, canned] = await Promise.all([inboxApi.settings(), inboxApi.canned()]);
    setMeta({
      team: s.team || [],
      canned: canned || [],
      aiReady: !!(s.settings?.ai?.enabled && s.settings?.ai?.connection_id),
      widgetOn: !!s.settings?.widget?.enabled,
      channelsOn: Object.values(s.settings?.channels || {}).some((c) => c.enabled),
      store: s.store || null,
    });
  }, []);

  const loadList = useCallback(async () => {
    const res = await inboxApi.list({ ...filters, per_page: 50 });
    setList({ items: res.items || [], counts: res.counts || {}, loading: false });
  }, [filters]);

  const openConversation = useCallback(async (id) => {
    setActiveId(id);
    if (!id) {
      setThread({ conversation: null, messages: [], other: [] });
      return;
    }
    const res = await inboxApi.get(id);
    lastIdRef.current = res.messages.length ? res.messages[res.messages.length - 1].id : 0;
    setThread({ conversation: res.conversation, messages: res.messages, other: res.other || [] });
    if (res.conversation.unread_count > 0) {
      inboxApi.markRead(id).then(loadList);
    }
  }, [loadList]);

  const pollThread = useCallback(async () => {
    if (!activeId) return;
    const res = await inboxApi.messagesAfter(activeId, lastIdRef.current);
    if (res.messages.length) {
      lastIdRef.current = res.messages[res.messages.length - 1].id;
      setThread((t) => ({
        ...t,
        conversation: res.conversation,
        messages: [...t.messages, ...res.messages.filter((m) => !t.messages.some((x) => x.id === m.id))],
      }));
      if (res.conversation.unread_count > 0) inboxApi.markRead(activeId);
    } else {
      setThread((t) => ({ ...t, conversation: res.conversation }));
    }
  }, [activeId]);

  useEffect(() => {
    loadMeta();
  }, [loadMeta]);

  useEffect(() => {
    if (view !== "inbox") return undefined;
    setList((l) => ({ ...l, loading: true }));
    loadList();
    return visiblePoll(loadList, LIST_POLL);
  }, [loadList, view]);

  useEffect(() => {
    if (view !== "inbox" || !activeId) return undefined;
    return visiblePoll(pollThread, THREAD_POLL);
  }, [pollThread, activeId, view]);

  const send = async (body, isNote) => {
    setSending(true);
    try {
      const res = await inboxApi.send(activeId, body, isNote);
      lastIdRef.current = Math.max(lastIdRef.current, res.message.id);
      setThread((t) => ({ ...t, conversation: res.conversation, messages: [...t.messages, res.message] }));
      loadList();
    } finally {
      setSending(false);
    }
  };

  const appendMessage = (res) => {
    lastIdRef.current = Math.max(lastIdRef.current, res.message.id);
    setThread((t) => ({ ...t, conversation: res.conversation, messages: [...t.messages, res.message] }));
    loadList();
  };

  const sendProduct = async (productId, message) => {
    appendMessage(await inboxApi.sendProduct(activeId, productId, message));
  };

  const placeOrder = async (data) => {
    const res = await inboxApi.placeOrder(activeId, data);
    setThread((t) => ({ ...t, conversation: res.conversation }));
    // The system note and any confirmation arrive with the next poll.
    pollThread();
    loadList();
    return res;
  };

  const update = async (data) => {
    const res = await inboxApi.update(activeId, data);
    setThread((t) => ({ ...t, conversation: res.conversation }));
    loadList();
  };

  const actions =
    view === "inbox" ? (
      <button type="button" style={outlineBtn} onClick={() => setView("settings")}>
        {__("Settings", "zaplane")}
      </button>
    ) : (
      <button type="button" style={primaryBtn} onClick={() => setView("inbox")}>
        {__("Back to inbox", "zaplane")}
      </button>
    );

  return (
    <PageLayout title={__("Inbox", "zaplane")} heading={view === "inbox" ? __("Inbox", "zaplane") : __("Inbox settings", "zaplane")} actions={actions}>
      {view === "settings" ? (
        <Settings onSaved={loadMeta} />
      ) : (
        <>
          {!meta.widgetOn && !meta.channelsOn && (
            <div className="zaplane-inbox-setup">
              <span>{__("Your website chat widget is off, so visitors can't reach this inbox yet.", "zaplane")}</span>
              <button type="button" className="zaplane-inbox-link" onClick={() => setView("settings")}>
                {__("Turn it on", "zaplane")}
              </button>
            </div>
          )}
          <div className="zaplane-inbox">
            <ConversationList
              items={list.items}
              counts={list.counts}
              loading={list.loading}
              filters={filters}
              setFilters={setFilters}
              activeId={activeId}
              onSelect={openConversation}
            />
            <Thread
              conversation={thread.conversation}
              messages={thread.messages}
              canned={meta.canned}
              sending={sending}
              onSend={send}
              onSendProduct={sendProduct}
              hasStore={!!meta.store}
              onToggleAi={(on) => update({ handler: on ? "bot" : "human" })}
            />
            <Details
              conversation={thread.conversation}
              other={thread.other}
              team={meta.team}
              aiReady={meta.aiReady}
              onUpdate={update}
              store={meta.store}
              onPlaceOrder={placeOrder}
            />
          </div>
        </>
      )}
    </PageLayout>
  );
};

export default InboxPage;
