<?php

namespace Zaplane\Framework\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post extends WpModel {

	protected static string $table = 'posts';
	protected static string $primaryKey = 'ID';
	protected static bool $timestamps = false;

	protected static array $fillable = [
		'post_author',
		'post_date',
		'post_date_gmt',
		'post_content',
		'post_title',
		'post_excerpt',
		'post_status',
		'comment_status',
		'ping_status',
		'post_password',
		'post_name',
		'to_ping',
		'pinged',
		'post_modified',
		'post_modified_gmt',
		'post_content_filtered',
		'post_parent',
		'guid',
		'menu_order',
		'post_type',
		'post_mime_type',
		'comment_count',
	];

	protected static array $casts = [
		'ID' => 'integer',
		'post_author' => 'integer',
		'post_parent' => 'integer',
		'menu_order' => 'integer',
		'comment_count' => 'integer',
	];

	public function author(): ?User {
		return User::find( $this->post_author );
	}

	public function parent(): ?self {
		if ( ! $this->post_parent ) {
			return null;
		}
		return static::find( $this->post_parent );
	}

	public function children(): array {
		return static::where( 'post_parent', $this->ID )->get();
	}

	public function getMeta( string $key, bool $single = true ) {
		return get_post_meta( $this->ID, $key, $single );
	}

	public function setMeta( string $key, $value ): bool {
		return (bool) update_post_meta( $this->ID, $key, $value );
	}

	public function deleteMeta( string $key, $value = '' ): bool {
		return delete_post_meta( $this->ID, $key, $value );
	}

	public function isPublished(): bool {
		return $this->post_status === 'publish';
	}

	public function isDraft(): bool {
		return $this->post_status === 'draft';
	}

	public function isTrash(): bool {
		return $this->post_status === 'trash';
	}

	public static function published(): array {
		return static::where( 'post_status', 'publish' )->get();
	}

	public static function ofType( string $type ): array {
		return static::where( 'post_type', $type )->get();
	}

	public static function byAuthor( int $authorId ): array {
		return static::where( 'post_author', $authorId )->get();
	}
}
