<?php

namespace ProductDiscounts\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ProductDiscounts\Models\Doing;

class ReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $rules = [];
        foreach (['basket_guid', 'order_from', 'action_name_search', 'product_name_search'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }
        return $rules + [
            'start' => ['nullable', 'date_format:Y-m-d'], 'end' => ['nullable', 'date_format:Y-m-d', ...($this->filled('start') ? ['after_or_equal:start'] : [])],
            'date_field' => ['nullable', Rule::in(['calculated_at', 'paid_at'])],
            'discount' => ['nullable', 'integer', 'between:-1000000000000,1000000000000'],
            'status' => ['nullable', Rule::in(['new', 'paid', 'failed', 'refund', 'error'])],
            'type' => ['nullable', Rule::in(['Скидка', 'Наценка', 'Фиксированная цена'])],
            'applie' => ['nullable', Rule::in(['На товар', 'На корзину'])],
            'type_discount' => ['nullable', Rule::in(['Процент', 'Сумма'])],
            'condition_id' => ['nullable', 'integer', Rule::in(array_keys(Doing::CONDITIONS))],
            'length' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }
}
