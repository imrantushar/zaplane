<?php

namespace Zaplane\Framework\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Term extends WpModel {

	protected static string $table = 'terms';
	protected static string $primaryKey = 'term_id';
	protected static bool $timestamps = false;

	protected static array $fillable = [
		'name',
		'slug',
		'term_group',
	];

	protected static array $casts = [
		'term_id' => 'integer',
		'term_group' => 'integer',
	];

	public function taxonomy(): ?TermTaxonomy {
		return TermTaxonomy::where( 'term_id', $this->term_id )->first();
	}

	public static function bySlug( string $slug ): ?self {
		return static::where( 'slug', $slug )->first();
	}

	public static function byName( string $name ): ?self {
		return static::where( 'name', $name )->first();
	}
}
