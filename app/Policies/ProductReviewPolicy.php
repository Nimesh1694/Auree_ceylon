<?php

namespace App\Policies;

use App\Models\ProductReview;
use App\Models\User;

class ProductReviewPolicy
{
    /**
     * Determine whether the user can view any reviews.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('reviews.view');
    }

    /**
     * Determine whether the user can view the specific review.
     */
    public function view(User $user, ProductReview $review): bool
    {
        return $user->hasPermissionTo('reviews.view');
    }

    /**
     * Determine whether the user can moderate (approve/reject) reviews.
     */
    public function moderate(User $user, ProductReview $review): bool
    {
        return $user->hasPermissionTo('reviews.moderate');
    }

    /**
     * Determine whether the user can permanently delete a review (Owner Only).
     */
    public function delete(User $user, ProductReview $review): bool
    {
        return $user->isOwner() || $user->hasRole('owner');
    }
}

