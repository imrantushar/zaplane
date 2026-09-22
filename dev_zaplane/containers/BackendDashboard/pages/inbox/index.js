import { useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import { __ } from "@wordpress/i18n";
import { FiSettings, FiArrowLeft, FiInfo } from "react-icons/fi";
import PageLayout from "@ZAPComponents/PageLayout";
import { outlineBtn } from "../../../../../assets/scss/chakra/recipe";
import ConversationList from "./ConversationList";
import Thread from "./Thread";
import Details from "./Details";
import Settings from "./Settings";
import { inboxApi, visiblePoll } from "./api";
import "./styles.scss";

const LIST_POLL = 5000;
const THREAD_POLL = 3000;
const DETAILS_KEY = "zaplane-inbox-details";
const COLLAPSE_KEY = "zaplane-inbox-list-collapsed";

const readPref = (key, fallback) => {
  try {
    const v = window.localStorage.getItem(key);
    return v === null ? fallback : v === "1";
  } catch (e) {
    return fallback;
  }
};

const writePref = (key, on) => {
  try {
    window.localStorage.setItem(key, on ? "1" : "0");
  } catch (e) {
    // Remembering the choice is a convenience only.
  }
};

// Below this width the details panel slides over the thread, so it starts closed.
const WIDE = 1200;

const readDetailsPref = () => {
  if (window.innerWidth < WIDE) return false;
  try {
    return window.localStorage.getItem(DETAILS_KEY) !== "0";
  } catch (e) {
    return true;
  }
};

/**
 * Size the inbox to the rest of the window, so the three panes scroll on
 * their own and the page itself never does.
 */
const useFillHeight = (ref, deps) => {
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return undefined;
    const fit = () => {
      const top = el.getBoundingClientRect().top + window.scrollY;
      el.style.setProperty("--zaplane-inbox-top", Math.max(0, Math.round(top)) + "px");
    };
    fit();
    window.addEventListener("resize", fit);
    return () => window.removeEventListener("resize", fit);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
};

const InboxPage = () => {
  const [view, setView] = useState("inbox");
  const [filters, setFilters] = useState({ status: "open", assignee: "any", search: "" });
  const [list, setList] = useState({ items: [], counts: {}, loading: true });
  const [activeId, setActiveId] = useState(0);
  const [thread, setThread] = useState({ conversation: null, messages: [], other: [] });
  const [meta, setMeta] = useState({ team: [], canned: [], aiReady: false, widgetOn: false, store: null, channelsOn: false });
  const [sending, setSending] = useState(false);
  const [detailsOpen, setDetailsOpen] = useState(readDetailsPref);
  const [pane, setPane] = useState("list"); // which pane a phone shows
  const [collapsed, setCollapsed] = useState(() => readPref(COLLAPSE_KEY, false));
  const toggleCollapsed = () =>
    setCollapsed((c) => {
      writePref(COLLAPSE_KEY, !c);
      return !c;
    });
  const lastIdRef = useRef(0);
  const revisionRef = useRef(0); // bumped by the server on every edit/delete
  const inboxRef = useRef(null);

  const toggleDetails = () => {
    setDetailsOpen((open) => {
      if (window.innerWidth < WIDE) return !open; // an overlay choice isn't remembered
      try {
        window.localStorage.setItem(DETAILS_KEY, open ? "0" : "1");
      } catch (e) {
        // Remembering the choice is a convenience only.
      }
      return !open;
    });
  };

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
    revisionRef.current = res.conversation.revision || 0;
    setThread({ conversation: res.conversation, messages: res.messages, other: res.other || [] });
    if (res.conversation.unread_count > 0) {
      inboxApi.markRead(id).then(loadList);
    }
  }, [loadList]);

  const pollThread = useCallback(async () => {
    if (!activeId) return;
    const res = await inboxApi.messagesAfter(activeId, lastIdRef.current);
    // Someone edited or deleted a message: reload the whole thread.
    if ((res.conversation?.revision || 0) !== revisionRef.current) {
      const full = await inboxApi.get(activeId);
      revisionRef.current = full.conversation.revision || 0;
      lastIdRef.current = full.messages.length ? full.messages[full.messages.length - 1].id : 0;
      setThread((t) => ({ ...t, conversation: full.conversation, messages: full.messages }));
      return;
    }
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

  // "?page=zaplane-inbox&conversation=12" (e.g. "View chat" in settings)
  // opens that conversation, whatever its status.
  useEffect(() => {
    const id = parseInt(new URLSearchParams(window.location.search).get("conversation") || "0", 10);
    if (id > 0) {
      setFilters((f) => ({ ...f, status: "all" }));
      setPane("thread");
      openConversation(id);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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

  const send = async (body, isNote, replyTo) => {
    setSending(true);
    try {
      const res = await inboxApi.send(activeId, body, isNote, replyTo);
      lastIdRef.current = Math.max(lastIdRef.current, res.message.id);
      setThread((t) => ({ ...t, conversation: res.conversation, messages: [...t.messages, res.message] }));
      loadList();
    } finally {
      setSending(false);
    }
  };

  // An edit or delete comes back as the changed message; swap it in place.
  const replaceMessage = (res) => {
    revisionRef.current = res.conversation.revision || 0;
    setThread((t) => ({
      ...t,
      conversation: res.conversation,
      messages: t.messages.map((m) => (m.id === res.message.id ? res.message : m)),
    }));
    loadList();
  };

  const editMessage = async (messageId, body) => replaceMessage(await inboxApi.editMessage(activeId, messageId, body));
  const deleteMessage = async (messageId) => replaceMessage(await inboxApi.deleteMessage(activeId, messageId));

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

  const showSetup = !meta.widgetOn && !meta.channelsOn;
  useFillHeight(inboxRef, [view, showSetup]);

  const selectConversation = (id) => {
    setPane("thread");
    openConversation(id);
  };

  const topBarActions =
    view === "inbox" ? (
      <button type="button" style={outlineBtn} className="zaplane-inbox-head-btn" onClick={() => setView("settings")}>
        <FiSettings />
        <span>{__("Settings", "zaplane")}</span>
      </button>
    ) : (
      <button type="button" style={outlineBtn} className="zaplane-inbox-head-btn" onClick={() => setView("inbox")}>
        <FiArrowLeft />
        <span>{__("Back to inbox", "zaplane")}</span>
      </button>
    );

  const hasDetails = !!thread.conversation && detailsOpen;
  const classes = ["zaplane-inbox", hasDetails ? "has-details" : "", collapsed ? "is-collapsed" : "", "is-pane-" + (thread.conversation ? pane : "list")].filter(Boolean).join(" ");

  return (
    <div className="zaplane-inbox-page">
      <PageLayout
        breadcrumbs={view === "inbox" ? [{ label: __("Inbox", "zaplane") }] : [{ label: __("Inbox", "zaplane") }, { label: __("Settings", "zaplane") }]}
        topBarActions={topBarActions}
        hideHeading
      >
        {view === "settings" ? (
          <Settings onSaved={loadMeta} />
        ) : (
          <>
            {showSetup && (
              <div className="zaplane-inbox-setup" role="status">
                <FiInfo />
                <span>{__("Your website chat widget is off and no channel is connected, so customers can't reach this inbox yet.", "zaplane")}</span>
                <button type="button" className="zaplane-inbox-link" onClick={() => setView("settings")}>
                  {__("Set it up", "zaplane")}
                </button>
              </div>
            )}
            <div className={classes} ref={inboxRef}>
              <ConversationList
                items={list.items}
                counts={list.counts}
                loading={list.loading}
                filters={filters}
                setFilters={setFilters}
                activeId={activeId}
                onSelect={selectConversation}
                collapsed={collapsed && window.innerWidth > 900}
                onToggleCollapsed={toggleCollapsed}
              />
              <Thread
                conversation={thread.conversation}
                messages={thread.messages}
                canned={meta.canned}
                sending={sending}
                onSend={send}
                onEditMessage={editMessage}
                onDeleteMessage={deleteMessage}
                onSendProduct={sendProduct}
                hasStore={!!meta.store}
                onToggleAi={(on) => update({ handler: on ? "bot" : "human" })}
                onUpdate={update}
                onBack={() => setPane("list")}
                detailsOpen={hasDetails}
                onToggleDetails={toggleDetails}
              />
              {hasDetails && <div className="zaplane-inbox-scrim" onClick={toggleDetails} aria-hidden="true" />}
              {hasDetails && (
                <Details
                  conversation={thread.conversation}
                  other={thread.other}
                  team={meta.team}
                  aiReady={meta.aiReady}
                  onUpdate={update}
                  store={meta.store}
                  onPlaceOrder={placeOrder}
                  onClose={toggleDetails}
                />
              )}
            </div>
          </>
        )}
      </PageLayout>
    </div>
  );
};

export default InboxPage;
