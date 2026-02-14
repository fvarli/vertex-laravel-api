<?php

namespace App\Http\Requests\Api\V1\Program;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:150'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'week_start_date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', Rule::in([Program::STATUS_DRAFT, Program::STATUS_ACTIVE, Program::STATUS_ARCHIVED])],
            'items' => ['nullable', 'array'],
            'items.*.day_of_week' => ['required_with:items', 'integer', 'between:1,7'],
            'items.*.order_no' => ['required_with:items', 'integer', 'min:1', 'max:999'],
            'items.*.exercise' => ['required_with:items', 'string', 'min:2', 'max:160'],
            'items.*.sets' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.reps' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.rest_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            $seen = [];

            foreach ($items as $index => $item) {
                $day = $item['day_of_week'] ?? null;
                $order = $item['order_no'] ?? null;

                if (! is_numeric($day) || ! is_numeric($order)) {
                    continue;
                }

                $key = sprintf('%d-%d', (int) $day, (int) $order);

                if (isset($seen[$key])) {
                    $validator->errors()->add("items.$index.order_no", __('api.program.duplicate_day_order'));
                }

                $seen[$key] = true;
            }
        });
    }
}
