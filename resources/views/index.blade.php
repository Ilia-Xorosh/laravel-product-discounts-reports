@extends('product-discounts-reports::layout')
@section('content')
<h1>Корзины и применённые акции</h1>
<form method="get" action="{{ route('product-discounts-reports.index') }}">
<div class="filters">
@foreach(['basket_guid' => 'GUID корзины', 'order_from' => 'Источник', 'action_name_search' => 'Название акции', 'product_name_search' => 'Название товара', 'discount' => 'Скидка от'] as $field => $label)
<label>{{ $label }}<input name="{{ $field }}" value="{{ $filters[$field] ?? '' }}" type="{{ $field === 'discount' ? 'number' : 'text' }}"></label>
@endforeach
@foreach(['start' => 'Дата от', 'end' => 'Дата до'] as $field => $label)
<label>{{ $label }}<input type="date" name="{{ $field }}" value="{{ $filters[$field] ?? '' }}"></label>
@endforeach
@foreach([
    'date_field' => ['Дата', ['calculated_at' => 'Расчёта', 'paid_at' => 'Оплаты']],
    'status' => ['Статус', ['new' => 'Новая', 'paid' => 'Оплачена', 'failed' => 'Отказ', 'refund' => 'Возврат', 'error' => 'Ошибка']],
    'type' => ['Тип действия', array_combine(['Скидка','Наценка','Фиксированная цена'], ['Скидка','Наценка','Фиксированная цена'])],
    'applie' => ['Применение', ['На товар' => 'На товар', 'На корзину' => 'На корзину']],
    'type_discount' => ['Единица', ['Сумма' => 'Сумма', 'Процент' => 'Процент']],
    'condition_id' => ['Условие', \ProductDiscounts\Models\Doing::CONDITIONS],
    'length' => ['На странице', [10=>10,25=>25,50=>50,100=>100]],
] as $field => [$label,$options])
<label>{{ $label }}<select name="{{ $field }}"><option value="">Все / по умолчанию</option>@foreach($options as $value => $name)<option value="{{ $value }}" @selected((string)($filters[$field] ?? '') === (string)$value)>{{ $name }}</option>@endforeach</select></label>
@endforeach
</div><button>Применить</button> <a href="{{ route('product-discounts-reports.index') }}">Сбросить</a>
</form>
<p><a href="{{ route('product-discounts-reports.export', ['mode'=>'mini', ...$filters]) }}">Excel mini</a> · <a href="{{ route('product-discounts-reports.export', ['mode'=>'max', ...$filters]) }}">Excel max</a></p>
<div class="summary"><span>Корзин: {{ $summary->baskets }}</span><span>До: {{ $summary->start_total }}</span><span>После: {{ $summary->final_total }}</span><span>Скидка: {{ $summary->discount_total }}</span></div>
<p>Итоги рассчитаны по всем корзинам с текущими фильтрами. Для выручки выберите статус «Оплачена».</p>
<div class="scroll"><table><thead><tr><th>Корзина</th><th>Источник / статус</th><th>Расчёт / оплата</th><th>До / после</th><th>Скидка</th><th>Акций</th></tr></thead><tbody>
@forelse($baskets as $basket)<tr>
<td><a href="{{ route('product-discounts-reports.show', $basket) }}">{{ $basket->basket_guid }}</a></td>
<td>{{ $basket->order_from }} / {{ $basket->status }}</td><td>{{ $basket->calculated_at }} / {{ $basket->paid_at ?? '—' }}</td>
<td>{{ $basket->start_price }} / {{ $basket->final_price }}</td><td>{{ $basket->discount }}</td><td>{{ $basket->actions_count }}</td>
</tr>@empty<tr><td colspan="6">Корзин по выбранным фильтрам нет.</td></tr>@endforelse
</tbody></table></div>{{ $baskets->links() }}
@endsection
