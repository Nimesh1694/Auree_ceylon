<?php

namespace App\Policies;

use App\Models\CustomerInquiry;
use App\Models\User;

class CustomerInquiryPolicy
{
    /**
     * Determine whether the user can view any inquiries.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('inquiries.manage');
    }

    /**
     * Determine whether the user can view the specific inquiry.
     */
    public function view(User $actor, CustomerInquiry $inquiry): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('inquiries.manage');
    }

    /**
     * Determine whether the user can update the inquiry (status or priority).
     */
    public function update(User $actor, CustomerInquiry $inquiry): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('inquiries.manage');
    }

    /**
     * Determine whether the user can assign staff to the inquiry.
     */
    public function assign(User $actor, CustomerInquiry $inquiry): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('inquiries.manage');
    }

    /**
     * Determine whether the user can append internal notes to the inquiry.
     */
    public function addNote(User $actor, CustomerInquiry $inquiry): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('inquiries.manage');
    }

    /**
     * Determine whether the user can delete the inquiry.
     * Inquiries are immutable legal compliance records and can NEVER be deleted.
     */
    public function delete(User $actor, CustomerInquiry $inquiry): bool
    {
        return false;
    }
}

