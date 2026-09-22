<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answer a customer straight from Business Knowledge, without a model.
 *
 * Runs as a background job for each new customer message the Inbox may
 * answer (see Router). In order:
 *
 * 1. A reply to "Did this answer your question?" is feedback: 👍 closes the
 *    loop, 👎 hands the question to the assistant (or the team).
 * 2. A strong match is sent as the answer: an FAQ's answer word for word, or
 *    the page it came from as a card. A medium match offers up to three pages
 *    to read.
 * 3. Otherwise the assistant answers, if it owns the conversation; if not,
 *    the team does, as before.
 *
 * Questions nothing answered are kept as "knowledge gaps" for the team to
 * turn into FAQs, and a weekly tally shows how many were answered this way.
 */
class KnowledgeAnswer {

	public const HOOK = 'zaplane_inbox_knowledge_answer';

	public const GAPS_OPTION  = 'zaplane_inbox_knowledge_gaps';
	public const STATS_OPTION = 'zaplane_inbox_knowledge_stats';

	/** Automatic answers per conversation in 24 hours. */
	private const DAILY_CAP = 3;

	/** How long "Did this answer your question?" waits for a reply. */
	private const FEEDBACK_WINDOW = DAY_IN_SECONDS;

	/** A teammate who replied this recently is handling the conversation. */
	private const TEAM_ACTIVE = WEEK_IN_SECONDS;

	private const MAX_GAPS = 100;

	/** "Our team will reply soon" is said at most this often per conversation. */
	private const ACK_EVERY = 12 * HOUR_IN_SECONDS;

	/** Button labels; Messenger and WhatsApp cut them at 20 characters. */
	public static function yes_label(): string {
		return __( 'Yes, thanks', 'zaplane' );
	}

	public static function no_label(): string {
		return __( 'No, I need help', 'zaplane' );
	}

	public static function person_label(): string {
		return __( 'Talk to a person', 'zaplane' );
	}

	public static function more_label(): string {
		return __( 'Other questions', 'zaplane' );
	}

	public static function assistant_label(): string {
		return __( 'Try our assistant', 'zaplane' );
	}

	/**
	 * Whether a message asks for a human: the button, or the usual ways of
	 * typing it ("talk to a person", "agent", "মানুষের সাথে কথা বলতে চাই").
	 */
	public static function wants_person( string $text ): bool {
		$said = trim( mb_strtolower( wp_strip_all_tags( $text ) ), " \t\n.!?" );
		if ( mb_strtolower( self::person_label() ) === $said ) {
			return true;
		}
		return (bool) preg_match(
			'/\b(talk|speak|chat)\s+(to|with)\s+(a\s+|an\s+|the\s+|some\s*)?(person|human|agent|someone|staff|team|representative|real person)\b|^(human|agent|person|representative|operator|real person|customer (care|service))$|মানুষ|এজেন্ট|কাস্টমার কেয়ার|প্রতিনিধি/iu',
			$said
		);
	}

	/**
	 * Whether automatic answers may be tried in this conversation: switched on,
	 * and the conversation belongs to the assistant, or to the team while
	 * nobody on it has replied recently.
	 */
	public static function applies( Conversation $conversation ): bool {
		$settings = InboxSettings::get()['answers'];
		if ( empty( $settings['enabled'] ) || 'closed' === $conversation->status ) {
			return false;
		}
		// The customer asked for a person: stay out of the way for a day.
		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];
		if ( (int) ( $meta['kb_person_at'] ?? 0 ) > time() - DAY_IN_SECONDS ) {
			return false;
		}
		if ( 'bot' === $conversation->handler ) {
			return true;
		}
		return 'human' === $conversation->handler && ! self::team_active( $conversation );
	}

	public static function handle( int $conversation_id, int $message_id ): void {
		$conversation = Conversations::find( $conversation_id );
		$message      = Message::where( 'id', $message_id )->fresh()->first();
		if ( ! $conversation || ! $message || (int) $message->conversation_id !== $conversation_id || 'contact' !== $message->sender_type ) {
			return;
		}

		// A newer customer message has its own job, which answers with this one in view.
		if ( Message::where( 'conversation_id', $conversation_id )->where( 'id', '>', $message_id )->where( 'sender_type', 'contact' )->fresh()->first() ) {
			return;
		}

		// Already answered (this job's queued copy ran, or someone replied
		// first). A workflow notification isn't an answer.
		$answered = Message::where( 'conversation_id', $conversation_id )
			->where( 'id', '>', $message_id )
			->where( 'direction', 'out' )
			->where( 'is_note', 0 )
			->where( 'sender_type', '!=', 'workflow' )
			->fresh()
			->first();
		if ( $answered ) {
			return;
		}

		$in_flow = self::applies( $conversation ) || self::pending( $conversation ) || self::menu_pending( $conversation );

		// "Talk to a person", from a button or typed, at any point.
		if ( $in_flow && self::wants_person( (string) $message->body ) ) {
			self::to_person( $conversation );
			return;
		}

		// A tap on the quick-answers menu: a category, a question, or "All topics".
		if ( $in_flow && self::menu_navigation( $conversation, $message ) ) {
			return;
		}

		if ( $in_flow ) {
			if ( self::menu_choice( $conversation, $message ) || self::feedback( $conversation, $message ) || ( self::applies( $conversation ) && self::answer( $conversation, $message ) ) ) {
				return;
			}
		}

		// Nothing to send from knowledge: whoever owns the conversation answers.
		$fresh = Conversations::find( $conversation_id );
		if ( $fresh && 'bot' === $fresh->handler && $fresh->ai_enabled ) {
			AiResponder::handle( $conversation_id, $message_id );
			return;
		}

		// A person will answer. Say so (once in a while), so the customer isn't
		// left wondering whether anyone saw the message.
		if ( $fresh && self::applies( $fresh ) ) {
			$meta = is_array( $fresh->meta ) ? $fresh->meta : [];
			if ( (int) ( $meta['kb_ack_at'] ?? 0 ) < time() - self::ACK_EVERY ) {
				Outbound::send( $fresh, __( 'Thanks! Our team will reply here soon.', 'zaplane' ), [ 'sender_type' => 'auto' ] );
				self::set_meta( $fresh, [ 'kb_ack_at' => time() ] );
			}
		}
	}

	/**
	 * Common questions this customer hasn't asked yet in this conversation.
	 *
	 * @return array<int,string>
	 */
	private static function remaining_questions( Conversation $conversation ): array {
		// Grouped menus offer their categories again; flat ones the questions
		// not asked yet.
		if ( AnswerMenu::grouped() ) {
			return AnswerMenu::top_level();
		}
		$common = AnswerMenu::top_level();
		if ( ! $common ) {
			return [];
		}
		// Compare meaningful words, so "what is your return policy" counts as
		// having asked "What is your return policy?".
		$key   = static function ( string $text ): string {
			$words = KnowledgeMatch::words( $text );
			sort( $words );
			return implode( ' ', $words );
		};
		$asked = [];
		foreach ( Message::where( 'conversation_id', (int) $conversation->id )->where( 'sender_type', 'contact' )->fresh()->get()->all() as $m ) {
			$asked[ $key( (string) $m->body ) ] = true;
		}
		return array_values( array_filter( $common, static fn( $q ) => ! isset( $asked[ $key( (string) $q ) ] ) ) );
	}

	/**
	 * What Business Knowledge would send for a question, without sending it.
	 * Backs the "Test a question" box in Inbox settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function preview( string $question ): array {
		$settings = InboxSettings::get();
		$match    = KnowledgeMatch::find( (string) $settings['ai']['business_key'], $question, (string) $settings['answers']['strictness'] );
		$reply    = 'none' === $match['tier'] ? null : self::compose( 'web', $match, false );

		return $match + [
			'reply' => $reply,
		];
	}

	/**
	 * A reply to the feedback question. Anything that isn't a clear yes or no
	 * is a new question and goes on as usual.
	 */
	private static function feedback( Conversation $conversation, Message $message ): bool {
		$pending = self::pending( $conversation );
		if ( ! $pending ) {
			return false;
		}
		self::set_meta( $conversation, [ 'kb_pending' => null ] );

		$said = trim( mb_strtolower( wp_strip_all_tags( (string) $message->body ) ), " \t\n.!" );
		$yes  = in_array( $said, array_map( 'mb_strtolower', [ self::yes_label(), 'yes', 'yes thanks', 'thanks', 'thank you', 'ok', 'okay', '👍', 'হ্যাঁ', 'ধন্যবাদ' ] ), true );
		$no   = in_array( $said, array_map( 'mb_strtolower', [ self::no_label(), 'no', 'nope', 'not really', '👎', 'না' ] ), true );
		if ( ! $yes && ! $no ) {
			return false;
		}

		$answer = Message::where( 'id', (int) $pending['message_id'] )->fresh()->first();
		if ( $answer ) {
			$meta             = is_array( $answer->meta ) ? $answer->meta : [];
			$meta['feedback'] = $yes ? 'yes' : 'no';
			$answer->meta     = $meta;
			$answer->save();
		}

		if ( $yes ) {
			self::count( 'helpful' );
			$more = self::remaining_questions( $conversation );
			Outbound::send( $conversation, $more ? __( 'Glad that helped! 😊 Anything else?', 'zaplane' ) : __( 'Glad that helped! 😊', 'zaplane' ), [
				'sender_type' => 'auto',
				'meta'        => [
					'quick_replies' => $more,
					'quick_prompt'  => $more ? __( 'You can also ask:', 'zaplane' ) : '',
				],
			] );
			return true;
		}

		self::count( 'not_helpful' );
		$question = (string) ( $answer ? ( $answer->meta['question'] ?? '' ) : '' );
		self::gap( $question, (int) $conversation->id );

		// Let the customer choose what happens next.
		$options = [ self::more_label(), self::person_label() ];
		if ( self::assistant_ready( $conversation ) ) {
			array_unshift( $options, self::assistant_label() );
		}
		Outbound::send( $conversation, __( "Sorry that didn't help. What would you like to do?", 'zaplane' ), [
			'sender_type' => 'auto',
			'meta'        => [ 'quick_replies' => $options ],
		] );
		self::set_meta( $conversation, [
			'kb_menu' => [
				'question' => $question,
				'at'       => time(),
			],
		] );
		return true;
	}

	/**
	 * Follow a tap on the quick-answers menu. Menu picks are explicit, so they
	 * aren't limited by the daily cap or the "never twice" rule.
	 */
	private static function menu_navigation( Conversation $conversation, Message $message ): bool {
		$hit = AnswerMenu::lookup( (string) $message->body );
		if ( ! $hit ) {
			return false;
		}
		self::set_meta( $conversation, [
			'kb_pending' => null,
			'kb_menu'    => null,
		] );

		if ( 'topics' === $hit['type'] ) {
			Outbound::send( $conversation, __( 'What can we help you with?', 'zaplane' ), [
				'sender_type' => 'auto',
				'meta'        => [ 'quick_replies' => array_merge( AnswerMenu::top_level(), [ self::person_label() ] ) ],
			] );
			return true;
		}

		if ( 'category' === $hit['type'] ) {
			$questions = array_column( (array) $hit['item']['questions'], 'question' );
			Outbound::send( $conversation, $questions
				/* translators: %s: category name, e.g. Delivery. */
				? sprintf( __( '%s: which question?', 'zaplane' ), $hit['item']['title'] )
				: __( "We don't have questions here yet. Our team can help.", 'zaplane' ), [
				'sender_type' => 'auto',
				'meta'        => [ 'quick_replies' => array_merge( $questions, [ AnswerMenu::topics_label(), self::person_label() ] ) ],
			] );
			return true;
		}

		// A question: send its linked answer. Without one it's an ordinary
		// question, and matching (or the team) takes it.
		$row = AnswerMenu::knowledge_row( (int) $hit['item']['knowledge_id'] );
		if ( ! $row || '' === trim( (string) $row['content'] ) ) {
			return false;
		}

		$settings = InboxSettings::get();
		$ask      = ! empty( $settings['answers']['feedback'] );
		$match    = [
			'tier'       => 'strong',
			'confidence' => 1.0,
			'method'     => 'menu',
			'reason'     => '',
			// A menu answer is the answer: send the text even for a page entry.
			'entries'    => [ [ 'source' => 'faq' ] + KnowledgeMatch::entry( $row ) ],
		];
		$reply = self::compose( (string) $conversation->channel, $match, $ask );
		$sent  = Outbound::send( $conversation, $reply['body'], [
			'sender_type' => 'auto',
			'attachments' => $reply['attachments'],
			'meta'        => [
				'knowledge'     => [
					'tier'       => 'strong',
					'confidence' => 1.0,
					'method'     => 'menu',
					'entries'    => [ (int) $row['id'] ],
				],
				'question'      => (string) $hit['item']['question'],
				'quick_replies' => $ask ? [ self::yes_label(), self::no_label() ] : [],
				'quick_prompt'  => $ask ? __( 'Did this answer your question?', 'zaplane' ) : '',
			],
		] );
		if ( 'failed' === $sent->delivery_status ) {
			return false;
		}

		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];
		self::set_meta( $conversation, [
			'kb_sent'    => array_values( array_unique( array_merge( array_map( 'intval', (array) ( $meta['kb_sent'] ?? [] ) ), [ (int) $row['id'] ] ) ) ),
			'kb_pending' => $ask ? [
				'message_id' => (int) $sent->id,
				'at'         => time(),
			] : null,
		] );
		self::count( 'answered' );
		return true;
	}

	/**
	 * The customer's pick after "Sorry that didn't help". Anything else is a
	 * new question and goes on as usual.
	 */
	private static function menu_choice( Conversation $conversation, Message $message ): bool {
		$menu = self::menu_pending( $conversation );
		if ( ! $menu ) {
			return false;
		}
		self::set_meta( $conversation, [ 'kb_menu' => null ] );

		$said = trim( mb_strtolower( wp_strip_all_tags( (string) $message->body ) ), " \t\n.!?" );

		if ( mb_strtolower( self::assistant_label() ) === $said && self::assistant_ready( $conversation ) ) {
			// Answer the question that started it, not the button's text.
			AiResponder::handle( (int) $conversation->id, (int) $message->id, (string) ( $menu['question'] ?? '' ) );
			return true;
		}

		if ( mb_strtolower( self::more_label() ) === $said ) {
			$questions = self::remaining_questions( $conversation );
			if ( ! $questions ) {
				$questions = self::faq_questions( 4 );
			}
			if ( ! $questions ) {
				self::to_person( $conversation );
				return true;
			}
			$questions[] = self::person_label();
			Outbound::send( $conversation, __( 'Here are some questions people often ask:', 'zaplane' ), [
				'sender_type' => 'auto',
				'meta'        => [ 'quick_replies' => $questions ],
			] );
			return true;
		}

		return false;
	}

	/**
	 * Hand the conversation to the team because the customer asked, tell the
	 * customer, and keep automatic answers out of it for a day.
	 */
	public static function to_person( Conversation $conversation ): void {
		$reason = __( 'the customer asked to talk to a person', 'zaplane' );
		if ( 'bot' === $conversation->handler || $conversation->ai_enabled ) {
			Conversations::hand_to_human( $conversation, $reason );
		} else {
			Conversations::system_note( $conversation, __( 'The customer asked to talk to a person.', 'zaplane' ) );
			do_action( 'zaplane/inbox/handed_to_human', Conversations::payload( $conversation, [ 'reason' => $reason ] ) );
		}

		Outbound::send( $conversation, __( "Sure! I've let our team know, and someone will reply here soon.", 'zaplane' ), [ 'sender_type' => 'auto' ] );
		self::set_meta( $conversation, [
			'kb_person_at' => time(),
			'kb_ack_at'    => time(),
			'kb_pending'   => null,
			'kb_menu'      => null,
		] );
	}

	private static function assistant_ready( Conversation $conversation ): bool {
		$ai = InboxSettings::get()['ai'];
		return 'bot' === $conversation->handler && $conversation->ai_enabled && ! empty( $ai['enabled'] ) && ! empty( $ai['connection_id'] );
	}

	/** @return array{question:string,at:int}|null */
	private static function menu_pending( Conversation $conversation ): ?array {
		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];
		$menu = $meta['kb_menu'] ?? null;
		return is_array( $menu ) && (int) ( $menu['at'] ?? 0 ) > time() - self::FEEDBACK_WINDOW ? $menu : null;
	}

	/**
	 * FAQ questions from the knowledge in use, for "Other questions" when no
	 * common questions are set.
	 *
	 * @return array<int,string>
	 */
	private static function faq_questions( int $limit ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$titles = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT title FROM %i WHERE business_key = %s AND source = 'faq' AND title <> '' AND CHAR_LENGTH(title) <= 80 ORDER BY id ASC LIMIT %d",
				\Zaplane\Models\Knowledge::getTable(),
				(string) InboxSettings::get()['ai']['business_key'],
				$limit
			)
		);
		return array_values( array_map( 'strval', (array) $titles ) );
	}

	private static function answer( Conversation $conversation, Message $message ): bool {
		$settings = InboxSettings::get();
		$meta     = is_array( $conversation->meta ) ? $conversation->meta : [];

		$recent = array_filter( (array) ( $meta['kb_log'] ?? [] ), static fn( $at ) => (int) $at > time() - DAY_IN_SECONDS );
		if ( count( $recent ) >= self::DAILY_CAP ) {
			return false;
		}

		$question = (string) $message->body;
		$match    = KnowledgeMatch::find( (string) $settings['ai']['business_key'], $question, (string) $settings['answers']['strictness'] );

		// Don't send the same thing twice: if it didn't settle it the first
		// time, a person or the assistant should take this one.
		$sent             = array_map( 'intval', (array) ( $meta['kb_sent'] ?? [] ) );
		$match['entries'] = array_values( array_filter( $match['entries'], static fn( $e ) => ! in_array( (int) $e['id'], $sent, true ) ) );

		if ( 'none' === $match['tier'] || empty( $match['entries'] ) ) {
			if ( 'no_match' === $match['reason'] ) {
				self::count( 'unmatched' );
				self::gap( $question, (int) $conversation->id );
			}
			return false;
		}

		$ask   = ! empty( $settings['answers']['feedback'] );
		$reply = self::compose( (string) $conversation->channel, $match, $ask );

		$sent_message = Outbound::send( $conversation, $reply['body'], [
			'sender_type' => 'auto',
			'attachments' => $reply['attachments'],
			'meta'        => [
				'knowledge'     => [
					'tier'       => $match['tier'],
					'confidence' => $match['confidence'],
					'method'     => $match['method'],
					'entries'    => array_column( $match['entries'], 'id' ),
				],
				'question'      => mb_substr( $question, 0, 500 ),
				'quick_replies' => $ask ? [ self::yes_label(), self::no_label() ] : [],
				'quick_prompt'  => $ask ? __( 'Did this answer your question?', 'zaplane' ) : '',
			],
		] );

		if ( 'failed' === $sent_message->delivery_status ) {
			return false;
		}

		$recent[] = time();
		self::set_meta( $conversation, [
			'kb_log'     => array_values( $recent ),
			'kb_sent'    => array_values( array_unique( array_merge( $sent, array_map( 'intval', array_column( $match['entries'], 'id' ) ) ) ) ),
			'kb_pending' => $ask ? [
				'message_id' => (int) $sent_message->id,
				'at'         => time(),
			] : null,
		] );
		self::count( 'strong' === $match['tier'] ? 'answered' : 'suggested' );

		return true;
	}

	/**
	 * The reply for a match: text for every channel, cards where the channel
	 * shows them (the website chat), links in the text everywhere else.
	 *
	 * @param array<string,mixed> $match
	 * @return array{body:string,attachments:array<int,array<string,mixed>>}
	 */
	private static function compose( string $channel, array $match, bool $ask ): array {
		$web     = 'web' === $channel;
		$entries = $match['entries'];
		$cards   = [];
		$lines   = [];

		if ( 'strong' === $match['tier'] ) {
			$entry = $entries[0];
			if ( '' === $entry['url'] || 'faq' === $entry['source'] ) {
				// An FAQ answer (or a manual entry) is the answer itself.
				$body = self::clip( $entry['answer'], 900 );
				if ( '' !== $entry['url'] ) {
					$cards[] = self::card( $entry );
				}
			} else {
				$body    = __( "Here's what we have on that:", 'zaplane' );
				$cards[] = self::card( $entry );
				$lines[] = $entry['title'] . "\n" . $entry['url'];
			}
		} else {
			$body = __( 'These might help:', 'zaplane' );
			foreach ( $entries as $entry ) {
				$cards[] = self::card( $entry );
				$lines[] = '• ' . $entry['title'] . "\n" . $entry['url'];
			}
		}

		if ( ! $web && $lines ) {
			$body .= "\n\n" . implode( "\n\n", $lines );
		}
		// The website chat shows the question right above its Yes / No buttons,
		// after any cards; elsewhere the buttons hang off this text.
		if ( $ask && ! $web ) {
			$body .= "\n\n" . __( 'Did this answer your question?', 'zaplane' );
		}

		return [
			'body'        => $body,
			'attachments' => $web ? $cards : [],
		];
	}

	/**
	 * @param array<string,mixed> $entry
	 * @return array<string,mixed>
	 */
	private static function card( array $entry ): array {
		return [
			'type'    => 'article',
			'title'   => (string) $entry['title'],
			'excerpt' => (string) $entry['excerpt'],
			'url'     => (string) $entry['url'],
			'image'   => (string) $entry['image'],
		];
	}

	private static function clip( string $text, int $max ): string {
		return mb_strlen( $text ) > $max ? rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…' : $text;
	}

	/** @return array{message_id:int,at:int}|null */
	private static function pending( Conversation $conversation ): ?array {
		$meta    = is_array( $conversation->meta ) ? $conversation->meta : [];
		$pending = $meta['kb_pending'] ?? null;
		if ( ! is_array( $pending ) || (int) ( $pending['at'] ?? 0 ) < time() - self::FEEDBACK_WINDOW ) {
			return null;
		}
		return $pending;
	}

	private static function team_active( Conversation $conversation ): bool {
		$last = Message::where( 'conversation_id', (int) $conversation->id )
			->where( 'sender_type', 'agent' )
			->where( 'is_note', 0 )
			->where( 'sender_id', '>', 0 )
			->orderBy( 'id', 'desc' )
			->fresh()
			->first();
		if ( ! $last ) {
			return false;
		}
		$at = strtotime( get_gmt_from_date( (string) $last->created_at ) . ' UTC' );
		return $at && $at > time() - self::TEAM_ACTIVE;
	}

	/**
	 * @param array<string,mixed> $changes Keys set to null are removed.
	 */
	private static function set_meta( Conversation $conversation, array $changes ): void {
		$fresh = Conversations::find( (int) $conversation->id ) ?: $conversation;
		$meta  = is_array( $fresh->meta ) ? $fresh->meta : [];
		foreach ( $changes as $key => $value ) {
			if ( null === $value ) {
				unset( $meta[ $key ] );
			} else {
				$meta[ $key ] = $value;
			}
		}
		$fresh->meta        = $meta;
		$conversation->meta = $meta;
		$fresh->save();
	}

	/* Knowledge gaps and the weekly tally ------------------------------- */

	/**
	 * Remember a question nothing answered, so the team can add it as an FAQ.
	 */
	public static function gap( string $question, int $conversation_id ): void {
		$question = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $question ) ) );
		if ( '' === $question ) {
			return;
		}

		$gaps = get_option( self::GAPS_OPTION, [] );
		$gaps = is_array( $gaps ) ? $gaps : [];
		$key  = md5( mb_strtolower( $question ) );

		$gaps[ $key ] = [
			'question'        => mb_substr( $question, 0, 300 ),
			'count'           => (int) ( $gaps[ $key ]['count'] ?? 0 ) + 1,
			'last_at'         => time(),
			'conversation_id' => $conversation_id,
		];

		uasort( $gaps, static fn( $a, $b ) => $b['last_at'] <=> $a['last_at'] );
		update_option( self::GAPS_OPTION, array_slice( $gaps, 0, self::MAX_GAPS, true ), false );
	}

	/**
	 * @return array<int,array<string,mixed>> Most asked first.
	 */
	public static function gaps(): array {
		$gaps = get_option( self::GAPS_OPTION, [] );
		$out  = [];
		foreach ( is_array( $gaps ) ? $gaps : [] as $key => $gap ) {
			$out[] = [ 'key' => (string) $key ] + (array) $gap;
		}
		usort( $out, static fn( $a, $b ) => [ $b['count'], $b['last_at'] ] <=> [ $a['count'], $a['last_at'] ] );
		return $out;
	}

	public static function dismiss_gap( string $key ): void {
		$gaps = get_option( self::GAPS_OPTION, [] );
		if ( is_array( $gaps ) && isset( $gaps[ $key ] ) ) {
			unset( $gaps[ $key ] );
			update_option( self::GAPS_OPTION, $gaps, false );
		}
	}

	private static function count( string $what ): void {
		$stats = get_option( self::STATS_OPTION, [] );
		$stats = is_array( $stats ) ? $stats : [];
		$week  = gmdate( 'o-\WW' );

		$stats[ $week ]          = (array) ( $stats[ $week ] ?? [] );
		$stats[ $week ][ $what ] = (int) ( $stats[ $week ][ $what ] ?? 0 ) + 1;

		krsort( $stats );
		update_option( self::STATS_OPTION, array_slice( $stats, 0, 8, true ), false );
	}

	/**
	 * This week's tally.
	 *
	 * @return array{answered:int,suggested:int,helpful:int,not_helpful:int,unmatched:int}
	 */
	public static function stats(): array {
		$stats = get_option( self::STATS_OPTION, [] );
		$week  = is_array( $stats ) ? (array) ( $stats[ gmdate( 'o-\WW' ) ] ?? [] ) : [];
		$out   = [];
		foreach ( [ 'answered', 'suggested', 'helpful', 'not_helpful', 'unmatched' ] as $key ) {
			$out[ $key ] = (int) ( $week[ $key ] ?? 0 );
		}
		return $out;
	}
}
