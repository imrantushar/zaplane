<?php

namespace Zaplane\Services;

use Zaplane\Database\Seeders\StoreengineEmailsGroupSeeder;
use Zaplane\Models\Recipe;
use Zaplane\Models\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps StoreEngine from sending an email that a workflow now sends.
 *
 * Each workflow the StoreEngine Store Emails group recipe sets up sends one of
 * StoreEngine's emails. Setting one up notes which email it took over, and while
 * that workflow is on, StoreEngine's own copy is switched off as its emails read
 * their settings. Nothing StoreEngine saved is changed: pause or delete the
 * workflow and StoreEngine sends the email again.
 *
 * Needs a StoreEngine with the `storeengine/email/setting` filter.
 */
class StoreengineEmailHandover {

	/** Workflow id => the StoreEngine email it took over, as "setting key.recipient". */
	const OPTION = 'zaplane_storeengine_email_handover';

	/**
	 * Setting key => the recipients whose copy a live workflow sends, once worked out.
	 *
	 * @var array<string,array<int,string>>|null
	 */
	private static ?array $taken = null;

	public static function boot(): void {
		add_filter( 'storeengine/email/setting', [ self::class, 'filter_setting' ], 10, 2 );
		add_action( 'zaplane_recipe_group_installed', [ self::class, 'record' ], 10, 2 );
	}

	/**
	 * Switches StoreEngine's copy of an email off while a workflow sends it.
	 *
	 * @param mixed $value The setting as StoreEngine saved it.
	 * @param mixed $name  Its key, such as order_confirmation.
	 * @return mixed
	 */
	public static function filter_setting( $value, $name ) {
		if ( ! is_array( $value ) || ! is_string( $name ) ) {
			return $value;
		}

		foreach ( self::taken()[ $name ] ?? [] as $recipient ) {
			if ( isset( $value[ $recipient ] ) && is_array( $value[ $recipient ] ) ) {
				$value[ $recipient ]['is_enable'] = false;
			}
		}

		return $value;
	}

	/**
	 * Notes which StoreEngine email each workflow of the group took over, whether
	 * or not it was switched on: one set up as a draft can be switched on later.
	 *
	 * @param mixed $result What RecipeGroupService::install() created.
	 * @param mixed $recipe The group recipe it came from.
	 */
	public static function record( $result, $recipe ): void {
		if ( ! is_array( $result ) || ! $recipe instanceof Recipe || StoreengineEmailsGroupSeeder::SLUG !== (string) $recipe->slug ) {
			return;
		}

		$index = self::index();

		foreach ( (array) ( $result['workflows'] ?? [] ) as $workflow ) {
			$email = StoreengineEmailsGroupSeeder::HANDOVER[ (string) ( $workflow['key'] ?? '' ) ] ?? '';

			if ( '' !== $email && ! empty( $workflow['id'] ) ) {
				$index[ (int) $workflow['id'] ] = $email;
			}
		}

		update_option( self::OPTION, $index );
		self::flush();
	}

	/**
	 * The emails that live workflows took over: setting key => recipients.
	 *
	 * Worked out once a request, and only asks the database on a site where the
	 * group was set up.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function taken(): array {
		if ( null !== self::$taken ) {
			return self::$taken;
		}

		$taken = [];
		$index = self::index();

		if ( $index ) {
			foreach ( Workflow::whereIn( 'id', array_keys( $index ) )->select( 'id', 'status' )->fresh()->get() as $workflow ) {
				$email = (string) ( $index[ (int) $workflow->id ] ?? '' );

				if ( 'active' !== (string) $workflow->status || false === strpos( $email, '.' ) ) {
					continue;
				}

				list( $key, $recipient ) = explode( '.', $email, 2 );

				if ( ! in_array( $recipient, $taken[ $key ] ?? [], true ) ) {
					$taken[ $key ][] = $recipient;
				}
			}
		}

		self::$taken = $taken;

		return $taken;
	}

	/**
	 * Forgets what was worked out, so the next read asks again.
	 */
	public static function flush(): void {
		self::$taken = null;
	}

	/**
	 * @return array<int,string>
	 */
	private static function index(): array {
		$index = get_option( self::OPTION, [] );

		return is_array( $index ) ? $index : [];
	}
}
