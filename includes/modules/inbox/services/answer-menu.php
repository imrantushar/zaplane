<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Models\Knowledge;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The quick-answers menu: questions a customer can tap instead of typing,
 * optionally grouped into categories ("Delivery", "Returns"…).
 *
 * Every question points at a Business Knowledge entry (an FAQ), so tapping it
 * sends exactly that answer; nothing is left to matching. Answers written in
 * the menu editor are saved as FAQs, so Business Knowledge stays the one
 * place answers live, and the AI assistant can use them too.
 *
 * Stored in Inbox settings as answers.menu:
 *   grouped    bool
 *   categories [ { id, title, questions: [ { id, question, knowledge_id } ] } ]
 *   questions  [ { id, question, knowledge_id } ]   (used when not grouped)
 */
class AnswerMenu {

	/** WhatsApp list menus hold 10 rows; Messenger quick replies 13. */
	public const MAX_CATEGORIES   = 10;
	public const MAX_PER_CATEGORY = 8;
	public const MAX_FLAT         = 10;

	/** A category label fits a WhatsApp list row (24) with room to spare. */
	public const CATEGORY_LENGTH = 24;
	public const QUESTION_LENGTH = 80;

	public static function topics_label(): string {
		return __( 'All topics', 'zaplane' );
	}

	/** @return array{grouped:bool,categories:array,questions:array} */
	public static function empty(): array {
		return [
			'grouped'    => false,
			'categories' => [],
			'questions'  => [],
		];
	}

	/**
	 * The saved menu. Sites that set "common questions" before menus existed
	 * get them as a flat menu, each linked to the FAQ with the same question.
	 *
	 * @return array{grouped:bool,categories:array,questions:array}
	 */
	public static function get(): array {
		$settings = InboxSettings::get();
		$answers  = $settings['answers'];
		// Old questions are linked within the knowledge in use, so it's part of the key.
		$key = md5( (string) wp_json_encode( [ $answers['menu'] ?? null, $answers['common_questions'] ?? null, $settings['ai']['business_key'] ?? '' ] ) );
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$menu = is_array( $answers['menu'] ?? null ) ? $answers['menu'] : [];
		$menu = self::sanitize( $menu );

		if ( ! $menu['categories'] && ! $menu['questions'] && ! empty( $answers['common_questions'] ) ) {
			foreach ( (array) $answers['common_questions'] as $question ) {
				$menu['questions'][] = [
					'id'           => self::id( 'q' ),
					'question'     => (string) $question,
					'knowledge_id' => self::faq_id_for( (string) $question ),
				];
			}
		}
		self::$cache = [ $key => $menu ];
		return $menu;
	}

	/** @var array<string,array> The menu, per request. */
	private static array $cache = [];

	/**
	 * Clean a menu from the settings form: trimmed, limited, ids kept or given.
	 *
	 * @param array<string,mixed> $in
	 * @return array{grouped:bool,categories:array,questions:array}
	 */
	public static function sanitize( array $in ): array {
		$out            = self::empty();
		$out['grouped'] = ! empty( $in['grouped'] );

		foreach ( array_slice( (array) ( $in['categories'] ?? [] ), 0, self::MAX_CATEGORIES ) as $cat ) {
			$title = mb_substr( trim( sanitize_text_field( (string) ( $cat['title'] ?? '' ) ) ), 0, self::CATEGORY_LENGTH );
			if ( '' === $title ) {
				continue;
			}
			$out['categories'][] = [
				'id'        => self::clean_id( $cat['id'] ?? '', 'c' ),
				'title'     => $title,
				'questions' => self::clean_questions( (array) ( $cat['questions'] ?? [] ), self::MAX_PER_CATEGORY ),
			];
		}
		$out['questions'] = self::clean_questions( (array) ( $in['questions'] ?? [] ), self::MAX_FLAT );

		return $out;
	}

	/**
	 * Before saving: every question that came with an answer written in the
	 * editor gets it stored as an FAQ in Business Knowledge (created, or the
	 * linked one updated), and the menu keeps only the link.
	 *
	 * @param array<string,mixed> $in The menu from the form, questions may carry `answer`.
	 * @return array{grouped:bool,categories:array,questions:array}
	 */
	public static function prepare( array $in, string $business_key ): array {
		$changed = false;
		$save    = static function ( array $q ) use ( $business_key, &$changed ): array {
			$answer = trim( (string) ( $q['answer'] ?? '' ) );
			if ( '' === $answer || '' === trim( (string) ( $q['question'] ?? '' ) ) ) {
				return $q;
			}
			$q['knowledge_id'] = self::store_answer( (int) ( $q['knowledge_id'] ?? 0 ), (string) $q['question'], $answer, $business_key );
			$changed           = true;
			return $q;
		};

		foreach ( (array) ( $in['categories'] ?? [] ) as $i => $cat ) {
			foreach ( (array) ( $cat['questions'] ?? [] ) as $j => $q ) {
				$in['categories'][ $i ]['questions'][ $j ] = $save( (array) $q );
			}
		}
		foreach ( (array) ( $in['questions'] ?? [] ) as $j => $q ) {
			$in['questions'][ $j ] = $save( (array) $q );
		}

		if ( $changed && \Zaplane\Services\KnowledgeEmbeddings::enabled() ) {
			\Zaplane\Modules\KnowledgeAutomation\KnowledgeAutomationModule::queue_embedding( $business_key );
		}

		return self::sanitize( $in );
	}

	/**
	 * The menu for the settings editor: each question with its answer text,
	 * and a flag for questions whose answer is missing.
	 *
	 * @return array<string,mixed>
	 */
	public static function for_admin(): array {
		$menu = self::get();
		$fill = static function ( array $q ): array {
			$row           = self::knowledge_row( (int) $q['knowledge_id'] );
			$q['answer']   = $row ? (string) $row['content'] : '';
			$q['missing']  = ! $row;
			return $q;
		};
		foreach ( $menu['categories'] as $i => $cat ) {
			$menu['categories'][ $i ]['questions'] = array_map( $fill, $cat['questions'] );
		}
		$menu['questions'] = array_map( $fill, $menu['questions'] );
		return $menu;
	}

	/**
	 * What the website chat needs: labels only.
	 *
	 * @return array{grouped:bool,categories:array<int,array{title:string,questions:array<int,string>}>,questions:array<int,string>}
	 */
	public static function for_widget(): array {
		$menu = self::get();
		return [
			'grouped'    => $menu['grouped'] && (bool) $menu['categories'],
			'categories' => array_map( static fn( $c ) => [
				'title'     => $c['title'],
				'questions' => array_column( $c['questions'], 'question' ),
			], $menu['categories'] ),
			'questions'  => array_column( $menu['questions'], 'question' ),
		];
	}

	public static function grouped(): bool {
		$menu = self::get();
		return $menu['grouped'] && (bool) $menu['categories'];
	}

	/**
	 * The first level: category titles when grouped, else the questions.
	 *
	 * @return array<int,string>
	 */
	public static function top_level(): array {
		$menu = self::get();
		return self::grouped() ? array_column( $menu['categories'], 'title' ) : array_column( $menu['questions'], 'question' );
	}

	/**
	 * Messenger Ice Breakers / WhatsApp starters: four at most.
	 *
	 * @return array<int,string>
	 */
	public static function starters(): array {
		return array_slice( self::top_level(), 0, 4 );
	}

	/**
	 * What a customer's message picks from the menu, if anything. Taps arrive
	 * as the label's text, so an exact (case- and punctuation-insensitive)
	 * match is all that's needed.
	 *
	 * @return array{type:string,item:array<string,mixed>}|null  type: topics|category|question
	 */
	public static function lookup( string $text ): ?array {
		$said = self::normalize( $text );
		if ( '' === $said ) {
			return null;
		}
		if ( self::normalize( self::topics_label() ) === $said ) {
			return [
				'type' => 'topics',
				'item' => [],
			];
		}

		$menu = self::get();
		if ( self::grouped() ) {
			foreach ( $menu['categories'] as $cat ) {
				if ( self::normalize( $cat['title'] ) === $said ) {
					return [
						'type' => 'category',
						'item' => $cat,
					];
				}
			}
		}
		foreach ( array_merge( $menu['questions'], ...array_column( $menu['categories'], 'questions' ) ) as $q ) {
			if ( self::normalize( $q['question'] ) === $said ) {
				return [
					'type' => 'question',
					'item' => $q,
				];
			}
		}
		return null;
	}

	/**
	 * Every question label in the menu (for "not asked yet" filtering).
	 *
	 * @return array<int,string>
	 */
	public static function all_questions(): array {
		$menu = self::get();
		return array_column( array_merge( $menu['questions'], ...array_column( $menu['categories'], 'questions' ) ), 'question' );
	}

	/** @return array<string,mixed>|null */
	public static function knowledge_row( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, content, source, ref_id FROM %i WHERE id = %d', Knowledge::getTable(), $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	private static function store_answer( int $knowledge_id, string $question, string $answer, string $business_key ): int {
		global $wpdb;
		$table = Knowledge::getTable();
		$row   = self::knowledge_row( $knowledge_id );
		$data  = [
			'title'      => sanitize_text_field( $question ),
			'content'    => sanitize_textarea_field( $answer ),
			'updated_at' => current_time( 'mysql' ),
		];

		if ( $row ) {
			if ( $row['content'] !== $data['content'] || $row['title'] !== $data['title'] ) {
				// A changed answer needs a new embedding.
				$data['embedding'] = null;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, $data, [ 'id' => $knowledge_id ] );
			}
			return $knowledge_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data + [
			'business_key' => $business_key,
			'source'       => 'faq',
		] );
		return (int) $wpdb->insert_id;
	}

	private static function faq_id_for( string $question ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE business_key = %s AND source = 'faq' AND title = %s ORDER BY id ASC LIMIT 1",
				Knowledge::getTable(),
				(string) InboxSettings::get()['ai']['business_key'],
				$question
			)
		);
	}

	/**
	 * @param array<int,mixed> $questions
	 * @return array<int,array{id:string,question:string,knowledge_id:int}>
	 */
	private static function clean_questions( array $questions, int $max ): array {
		$out  = [];
		$seen = [];
		foreach ( $questions as $q ) {
			$q    = is_array( $q ) ? $q : [ 'question' => (string) $q ];
			$text = mb_substr( trim( sanitize_text_field( (string) ( $q['question'] ?? '' ) ) ), 0, self::QUESTION_LENGTH );
			$key  = self::normalize( $text );
			if ( '' === $text || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = [
				'id'           => self::clean_id( $q['id'] ?? '', 'q' ),
				'question'     => $text,
				'knowledge_id' => max( 0, (int) ( $q['knowledge_id'] ?? 0 ) ),
			];
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	private static function normalize( string $text ): string {
		return trim( preg_replace( '/[\s\p{P}]+/u', ' ', mb_strtolower( wp_strip_all_tags( $text ) ) ) );
	}

	private static function clean_id( $id, string $prefix ): string {
		$id = (string) $id;
		return preg_match( '/^[a-z]_[a-z0-9]{6,16}$/', $id ) ? $id : self::id( $prefix );
	}

	private static function id( string $prefix ): string {
		return $prefix . '_' . strtolower( wp_generate_password( 8, false, false ) );
	}
}
