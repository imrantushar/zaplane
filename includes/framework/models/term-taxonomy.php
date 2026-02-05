<?php

namespace Zaplane\Framework\Models;

if (!defined('ABSPATH')) exit;

class TermTaxonomy extends WpModel
{
    protected static string $table = 'term_taxonomy';
    protected static string $primaryKey = 'term_taxonomy_id';
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'term_id',
        'taxonomy',
        'description',
        'parent',
        'count',
    ];

    protected static array $casts = [
        'term_taxonomy_id' => 'integer',
        'term_id' => 'integer',
        'parent' => 'integer',
        'count' => 'integer',
    ];

    public function term(): ?Term
    {
        return Term::find($this->term_id);
    }

    public function parentTerm(): ?self
    {
        if (!$this->parent) {
            return null;
        }
        return static::find($this->parent);
    }

    public static function ofTaxonomy(string $taxonomy): array
    {
        return static::where('taxonomy', $taxonomy)->get();
    }

    public static function categories(): array
    {
        return static::ofTaxonomy('category');
    }

    public static function tags(): array
    {
        return static::ofTaxonomy('post_tag');
    }
}
