<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListPostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', 'exists:sources,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'hours' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function sources(): array
    {
        return $this->array('sources');
    }

    public function pageNumber(): int
    {
        return (int) $this->integer('page', 1);
    }

    public function hours(): ?int
    {
        $hours = $this->integer('hours');

        return $hours !== 0 ? (int) $hours : null;
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 20);
    }
}
