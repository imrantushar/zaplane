<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Models\Knowledge;
use Zaplane\Services\KnowledgeEmbeddings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How well a customer's message matches Business Knowledge, as an absolute
 * confidence the Inbox can act on without asking a model.
 *
 * Retrieval for the AI ranks entries against each other (the best keyword hit
 * always scores 1), which is fine for picking context but says nothing about
 * whether the best entry actually answers the question. This class scores
 * each entry on its own:
 *
 * - With embeddings: the cosine similarity between the question and the entry.
 * - Without: word overlap against the entry's title, and only an FAQ whose
 *   question closely matches may be sent as an answer.
 *
 * Tiers: "strong" (send it as the answer), "medium" (offer up to three
 * articles to read) and "none" (leave it to the assistant or the team).
 */
class KnowledgeMatch {

	/** Rows considered per question. */
	private const SCAN = 500;

	/** Cosine thresholds per strictness: [ strong, medium ]. */
	private const THRESHOLDS = [
		'strict'   => [ 0.78, 0.64 ],
		'balanced' => [ 0.72, 0.58 ],
		'broad'    => [ 0.66, 0.52 ],
	];

	/** Words that say nothing about what is being asked. */
	private const STOPWORDS = [
		'a', 'an', 'the', 'is', 'are', 'am', 'was', 'be', 'do', 'does', 'did', 'can', 'could', 'will', 'would', 'should',
		'i', 'me', 'my', 'we', 'our', 'you', 'your', 'it', 'its', 'this', 'that', 'there', 'here', 'to', 'of', 'in', 'on',
		'at', 'for', 'from', 'with', 'and', 'or', 'if', 'so', 'please', 'pls', 'plz', 'hi', 'hello', 'hey', 'what', 'how',
		'when', 'where', 'which', 'who', 'why', 'any', 'have', 'has', 'get', 'got', 'want', 'need', 'know', 'tell', 'about',
		// How people actually type in chat.
		'whats', 'hows', 'wheres', 'whens', 'whos', 'u', 'ur', 'im', 'ive', 'dont', 'cant', 'wanna', 'gonna', 'plz', 'bro', 'sis', 'vai', 'apu',
		// Bangla.
		'কি', 'কী', 'আমি', 'আমার', 'আপনার', 'আপনি', 'আছে', 'এর', 'কত', 'করতে', 'হবে', 'না', 'এটা', 'এই', 'কেমন', 'ভাই', 'আপু',
	];

	/** Messages that aren't questions: greetings, thanks, acknowledgements. */
	private const SMALL_TALK = '/^\s*(hi+|hello+|hey+|salam|assalamu ?alaikum|thanks?( you)?|thank u|thx|ok(ay)?|k|yes|no|good|great|nice|bye|হ্যালো|ধন্যবাদ|ঠিক আছে|আচ্ছা|জি|হ্যাঁ|না)[\s!.?😊🙏👍]*$/iu';

	/**
	 * @return array{
	 *   tier:string,
	 *   confidence:float,
	 *   method:string,
	 *   reason:string,
	 *   entries:array<int,array<string,mixed>>
	 * }
	 */
	public static function find( string $business_key, string $question, string $strictness = 'balanced' ): array {
		$none = static function ( string $reason, string $method = '' ): array {
			return [
				'tier'       => 'none',
				'confidence' => 0.0,
				'method'     => $method,
				'reason'     => $reason,
				'entries'    => [],
			];
		};

		$question = trim( wp_strip_all_tags( $question ) );
		if ( '' === $question || preg_match( self::SMALL_TALK, $question ) ) {
			return $none( 'small_talk' );
		}

		$words = self::words( $question );
		if ( count( $words ) < 2 ) {
			return $none( 'too_short' );
		}

		$rows = self::rows( $business_key );
		if ( empty( $rows ) ) {
			return $none( 'empty_knowledge' );
		}

		$vector = KnowledgeEmbeddings::enabled() ? KnowledgeEmbeddings::embed( $question ) : null;
		$method = $vector ? 'semantic' : 'keyword';
		$tag    = $vector ? KnowledgeEmbeddings::tag() : '';

		/**
		 * Cosine thresholds [ strong, medium ] for a strictness level. Tune these
		 * to your embedding model; the Inbox settings "Test a question" box shows
		 * the scores your own questions get.
		 *
		 * @param array<int,float> $thresholds
		 * @param string           $strictness
		 */
		list( $strong_at, $medium_at ) = (array) apply_filters(
			'zaplane/inbox/knowledge_thresholds',
			self::THRESHOLDS[ $strictness ] ?? self::THRESHOLDS['balanced'],
			$strictness
		);

		$scored = [];
		foreach ( $rows as $row ) {
			$entry = self::entry( $row );
			$title = self::words( (string) $row['title'] );
			$body  = self::words( mb_substr( (string) $row['content'], 0, 4000 ) );

			if ( $vector ) {
				$row_vec = KnowledgeEmbeddings::usable_vector( $row['embedding'] ?? null, $tag, count( $vector ) );
				if ( ! $row_vec ) {
					continue; // not embedded yet (or with another model)
				}
				$score  = KnowledgeEmbeddings::cosine( $vector, $row_vec );
				$strong = $score >= $strong_at;
				$medium = $score >= $medium_at && '' !== $entry['url'];
			} else {
				$in_title = self::overlap( $words, $title );
				$score    = $in_title / count( $words );
				$back     = $title ? self::overlap( $title, $words ) / count( $title ) : 0.0;
				// Words alone can't tell "do you ship to Dhaka?" from "shipping
				// to Dhaka is suspended", so only an FAQ whose question matches
				// in both directions may be sent as the answer.
				$strong = 'faq' === $entry['source'] && $score >= 0.8 && $back >= 0.5;
				// A page to read is a softer offer: most of the question appears
				// in it with a word in its title, or nearly all of it in the text.
				$anywhere = self::overlap( $words, array_merge( $title, $body ) ) / count( $words );
				$medium   = '' !== $entry['url'] && ( ( $in_title > 0 && $anywhere >= 0.6 ) || $anywhere >= 0.8 );
				$score    = $strong ? $score : max( $score, $anywhere * 0.9 );
			}

			if ( $strong || $medium ) {
				$scored[] = $entry + [
					'score'  => round( $score, 3 ),
					'strong' => $strong,
				];
			}
		}

		if ( empty( $scored ) ) {
			return $none( 'no_match', $method );
		}

		usort( $scored, static fn( $a, $b ) => $b['score'] <=> $a['score'] );

		if ( $scored[0]['strong'] ) {
			return [
				'tier'       => 'strong',
				'confidence' => (float) $scored[0]['score'],
				'method'     => $method,
				'reason'     => '',
				'entries'    => [ $scored[0] ],
			];
		}

		$readable = array_values( array_filter( $scored, static fn( $e ) => '' !== $e['url'] ) );
		if ( empty( $readable ) ) {
			return $none( 'no_match', $method );
		}

		return [
			'tier'       => 'medium',
			'confidence' => (float) $readable[0]['score'],
			'method'     => $method,
			'reason'     => '',
			'entries'    => array_slice( $readable, 0, 3 ),
		];
	}

	/**
	 * Meaningful words of a text: lower-cased, split on anything that isn't a
	 * letter or digit in any script, stopwords dropped, plural "s" folded.
	 *
	 * @return array<int,string>
	 */
	public static function words( string $text ): array {
		// \p{M} keeps vowel signs (Bangla ী, া…) inside their word.
		$parts = preg_split( '/[^\p{L}\p{M}\p{N}]+/u', mb_strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$out   = [];
		foreach ( (array) $parts as $word ) {
			if ( mb_strlen( $word ) < 2 || in_array( $word, self::STOPWORDS, true ) ) {
				continue;
			}
			if ( preg_match( '/^[a-z]{4,}s$/', $word ) && ! preg_match( '/ss$/', $word ) ) {
				$word = substr( $word, 0, -1 );
			}
			$out[ $word ] = true;
		}
		return array_keys( $out );
	}

	/**
	 * How many of $words appear in $in. Words sharing a stem of four letters
	 * or more count ("deliver" / "delivery", "ship" / "shipping").
	 *
	 * @param array<int,string> $words
	 * @param array<int,string> $in
	 */
	private static function overlap( array $words, array $in ): int {
		$found = 0;
		foreach ( $words as $word ) {
			foreach ( $in as $other ) {
				if ( $word === $other ) {
					++$found;
					continue 2;
				}
				$short = mb_strlen( $word ) <= mb_strlen( $other ) ? $word : $other;
				$long  = $short === $word ? $other : $word;
				if ( mb_strlen( $short ) >= 4 && 0 === strpos( $long, $short ) ) {
					++$found;
					continue 2;
				}
			}
		}
		return $found;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function rows( string $business_key ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, title, content, embedding, source, ref_id FROM %i WHERE business_key = %s ORDER BY id DESC LIMIT %d',
				Knowledge::getTable(),
				$business_key,
				(int) apply_filters( 'zaplane_knowledge_max_scan', self::SCAN )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * An entry as the Inbox sends it: its answer text, and a page to read when
	 * it came from a post, page or product.
	 *
	 * @param array<string,mixed> $row
	 * @return array{id:int,title:string,answer:string,source:string,url:string,image:string,excerpt:string}
	 */
	private static function entry( array $row ): array {
		$source  = (string) ( $row['source'] ?? '' );
		$content = trim( wp_strip_all_tags( (string) ( $row['content'] ?? '' ) ) );
		$url     = '';
		$image   = '';

		$post_id = self::post_id( $source, (string) ( $row['ref_id'] ?? '' ) );
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			$url   = (string) get_permalink( $post_id );
			$image = (string) get_the_post_thumbnail_url( $post_id, 'medium' );
		}

		return [
			'id'      => (int) $row['id'],
			'title'   => (string) ( $row['title'] ?? '' ),
			'answer'  => $content,
			'source'  => $source,
			'url'     => $url,
			'image'   => $image,
			'excerpt' => wp_trim_words( $content, 24, '…' ),
		];
	}

	/** The post a synced entry came from ("se_12", "page_34"); 0 for FAQ/manual. */
	private static function post_id( string $source, string $ref ): int {
		if ( in_array( $source, [ 'faq', 'manual', '' ], true ) || ! preg_match( '/_(\d+)$/', $ref, $m ) ) {
			return 0;
		}
		return (int) $m[1];
	}
}
