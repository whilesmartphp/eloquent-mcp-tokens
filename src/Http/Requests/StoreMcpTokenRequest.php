<?php

namespace Whilesmart\McpTokens\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Whilesmart\McpTokens\Contracts\LifetimePolicy;
use Whilesmart\OwnerAccess\Concerns\AuthorizesOwnerRequest;

class StoreMcpTokenRequest extends FormRequest
{
    use AuthorizesOwnerRequest;

    private ?Model $resolvedOwner = null;

    public function authorize(): bool
    {
        return $this->authorizeOwnerInRequest();
    }

    public function rules(): array
    {
        return [
            'owner_type' => ['required', 'string'],
            'owner_id' => ['required'],
            'name' => ['required', 'string', 'max:60'],
            'days' => ['nullable', 'integer', 'min:1'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', Rule::in(config('mcp-tokens.scopes', []))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (! $owner = $this->owner()) {
                $validator->errors()->add('owner_id', 'The owner does not exist.');

                return;
            }
            $max = app(LifetimePolicy::class)->maxDays($owner);
            if ($this->filled('days') && (int) $this->input('days') > $max) {
                $validator->errors()->add('days', "A token can last at most {$max} days.");
            }
        });
    }

    public function owner(): ?Model
    {
        if ($this->resolvedOwner) {
            return $this->resolvedOwner;
        }
        $class = Relation::getMorphedModel($this->input('owner_type')) ?? $this->input('owner_type');
        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $this->resolvedOwner = $class::find($this->input('owner_id'));
    }

    public function days(): int
    {
        return $this->filled('days') ? (int) $this->input('days') : app(LifetimePolicy::class)->defaultDays($this->owner());
    }
}
