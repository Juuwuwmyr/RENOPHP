<?php

declare(strict_types=1);

namespace Examples\Models;

/**
 * Post Model (Example)
 * 
 * Simple post model for demonstrating route model binding with different field types.
 */
class Post
{
    /**
     * Post data.
     */
    protected array $data;

    /**
     * Sample posts database.
     */
    protected static array $posts = [
        1 => [
            'id' => 1,
            'title' => 'Getting Started with Horizon Framework',
            'slug' => 'getting-started-horizon-framework',
            'content' => 'Learn how to build web applications with Horizon...',
            'user_id' => 1,
            'published' => true,
        ],
        2 => [
            'id' => 2,
            'title' => 'Advanced Routing Techniques',
            'slug' => 'advanced-routing-techniques',
            'content' => 'Explore advanced routing patterns and model binding...',
            'user_id' => 1,
            'published' => true,
        ],
        3 => [
            'id' => 3,
            'title' => 'Middleware Deep Dive',
            'slug' => 'middleware-deep-dive',
            'content' => 'Understanding the middleware pipeline...',
            'user_id' => 2,
            'published' => false,
        ],
    ];

    /**
     * Create a new Post instance.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Find post by ID.
     */
    public static function find(mixed $id): ?static
    {
        $id = (int) $id;
        
        if (isset(self::$posts[$id])) {
            return new static(self::$posts[$id]);
        }

        return null;
    }

    /**
     * Find post by slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        foreach (self::$posts as $postData) {
            if ($postData['slug'] === $slug) {
                return new static($postData);
            }
        }

        return null;
    }

    /**
     * Find post by field.
     */
    public static function where(string $field, mixed $value): static
    {
        foreach (self::$posts as $postData) {
            if (isset($postData[$field]) && $postData[$field] === $value) {
                return new static($postData);
            }
        }

        return new static(); // Empty result for chaining
    }

    /**
     * Get first result.
     */
    public function first(): ?static
    {
        return empty($this->data) ? null : $this;
    }

    /**
     * Get all posts.
     */
    public static function all(): array
    {
        return array_map(fn($data) => new static($data), self::$posts);
    }

    /**
     * Get published posts.
     */
    public static function published(): array
    {
        $published = array_filter(self::$posts, fn($post) => $post['published']);
        return array_map(fn($data) => new static($data), $published);
    }

    /**
     * Get user relationship.
     */
    public function user(): ?User
    {
        return User::find($this->data['user_id'] ?? null);
    }

    /**
     * Magic getter.
     */
    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Magic setter.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Check if property exists.
     */
    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Convert to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->data);
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}