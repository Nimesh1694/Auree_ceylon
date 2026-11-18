<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('agents.announcements.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:255'],
            'title_si' => ['nullable', 'string', 'max:255'],
            'content_en' => ['required', 'string', 'max:10000'],
            'content_si' => ['nullable', 'string', 'max:10000'],
            'target_audience' => ['required', 'in:all,high_level_agent,low_level_agent'],
            'priority' => ['required', 'in:normal,important,urgent'],
            'is_pinned' => ['nullable', 'boolean'],
            'pin_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'publish_action' => ['nullable', 'in:draft,publish_now,schedule'],
            'scheduled_at' => ['nullable', 'required_if:publish_action,schedule', 'date', 'after:now'],
        ];
    }
}

