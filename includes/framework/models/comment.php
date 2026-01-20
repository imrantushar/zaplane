<?php

namespace Zaplane\Framework\Models\WordPress;

if (!defined('ABSPATH')) exit;

class Comment extends WPModel
{
    protected static string $table = 'comments';
    protected static string $primaryKey = 'comment_ID';

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

    public function post(): ?Post
    {
        return Post::find($this->comment_post_ID);
    }

    public function author(): ?User
    {
        if (!$this->user_id) {
            return null;
        }
        return User::find($this->user_id);
    }

    public function parent(): ?self
    {
        if (!$this->comment_parent) {
            return null;
        }
        return static::find($this->comment_parent);
    }

    public function replies(): array
    {
        return static::where('comment_parent', $this->comment_ID)->get();
    }

    public function isApproved(): bool
    {
        return $this->comment_approved === '1';
    }

    public function isPending(): bool
    {
        return $this->comment_approved === '0';
    }

    public function isSpam(): bool
    {
        return $this->comment_approved === 'spam';
    }

    public static function approved(): array
    {
        return static::where('comment_approved', '1')->get();
    }

    public static function pending(): array
    {
        return static::where('comment_approved', '0')->get();
    }

    public static function forPost(int $postId): array
    {
        return static::where('comment_post_ID', $postId)
            ->where('comment_approved', '1')
            ->orderBy('comment_date', 'asc')
            ->get();
    }
}
