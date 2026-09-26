<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Post;

/**
 * PostPolicy
 * 
 * Authorization policy for Post model.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear authorization logic
 * - Model-specific rules
 * - Easy to test
 * 
 * Example Usage:
 *   Gate::policy(Post::class, PostPolicy::class);
 *   
 *   can('update', $post)
 *   cannot('delete', $post)
 *   authorize('update', $post)
 */
class PostPolicy
{
    /**
     * Determine if the user can view any posts
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view posts
        return true;
    }

    /**
     * Determine if the user can view the post
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function view(User $user, Post $post): bool
    {
        // Users can view published posts or their own drafts
        return $post->published || $post->user_id === $user->id;
    }

    /**
     * Determine if the user can create posts
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // All authenticated users can create posts
        return true;
    }

    /**
     * Determine if the user can update the post
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function update(User $user, Post $post): bool
    {
        // Users can only update their own posts
        return $post->user_id === $user->id;
    }

    /**
     * Determine if the user can delete the post
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function delete(User $user, Post $post): bool
    {
        // Users can delete their own posts, or admins can delete any
        return $post->user_id === $user->id || $user->isAdmin();
    }

    /**
     * Determine if the user can restore the post
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function restore(User $user, Post $post): bool
    {
        // Same as delete
        return $this->delete($user, $post);
    }

    /**
     * Determine if the user can permanently delete the post
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function forceDelete(User $user, Post $post): bool
    {
        // Only admins can permanently delete
        return $user->isAdmin();
    }
}
