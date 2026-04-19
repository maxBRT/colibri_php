<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListSourcesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) && ! is_array($value)) {
                        $fail("The {$attribute} field must be a string or array.");
                    }
                },
            ],
            'category.*' => ['string'],
        ];
    }

    public function categories(): ?array
    {
        $category = $this->input('category');

        if ($category === null) {
            return null;
        }

        if (is_array($category)) {
            return array_values($category);
        }

        if (is_string($category) && $category !== '') {
            return [$category];
        }

        return null;
    }
}
