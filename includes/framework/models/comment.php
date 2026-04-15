<?php

namespace Zaplane\Framework\Models;

use Zaplane\Framework\Database\ORM\Collection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comment extends WpModel {

	protected static string $table = 'comments';
	protected static string $primaryKey = 'comment_ID';
	protected static bool $timestamps = false;

	protected static array $fillable = [
		'comment_post_ID',
		'comment_author',
		'comment_author_email',
		'comment_author_url',
		'comment_author_IP',
		'comment_date',
		'comment_date_gmt',
		'comment_content',
		'comment_karma',
		'comment_approved',
		'comment_agent',
		'comment_type',
		'comment_parent',
		'user_id',
	];

	protected static array $casts = [
		'comment_ID' => 'integer',
		'comment_post_ID' => 'integer',
		'comment_karma' => 'integer',
		'comment_parent' => 'integer',
		'user_id' => 'integer',
	];

	public function post(): ?Post {
		return Post::find( $this->comment_post_ID );
	}

	public function author(): ?User {
		if ( ! $this->user_id ) {
			return null;
		}
		return User::find( $this->user_id );
	}

	public function parent(): ?self {
		if ( ! $this->comment_parent ) {
			return null;
		}
		return static::find( $this->comment_parent );
	}

	public function replies(): Collection {
		return static::where( 'comment_parent', $this->comment_ID )->get();
	}

	public function isApproved(): bool {
		return '1' === $this->comment_approved;
	}

	public function isPending(): bool {
		return '0' === $this->comment_approved;
	}

	public function isSpam(): bool {
		return 'spam' === $this->comment_approved;
	}

	public static function approved(): Collection {
		return static::where( 'comment_approved', '1' )->get();
	}

	public static function pending(): Collection {
		return static::where( 'comment_approved', '0' )->get();
	}

	public static function forPost( int $postId ): Collection {
		return static::where( 'comment_post_ID', $postId )
			->where( 'comment_approved', '1' )
			->orderBy( 'comment_date', 'asc' )
			->get();
	}
}
