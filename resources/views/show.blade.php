@extends('product-discounts-reports::layout')
@section('content')
<h1>Корзина {{ $basket->basket_guid }}</h1>
<p>Источник: {{ $basket->order_from }}. Статус: {{ $basket->status }}. Расчёт: {{ $basket->calculated_at }}. Оплата: {{ $basket->paid_at ?? '—' }}.</p>
<p>До: {{ $basket->start_price }} → после: {{ $basket->final_price }}. Скидка: {{ $basket->discount }}. Промокод: {{ $basket->applied_promocode ?? '—' }}.</p>
<h2>Итоговые товары</h2>
<div class="scroll"><table><thead><tr><th>ID / товар</th><th>Количество</th><th>Исходная цена за штуку</th><th>Итог позиции</th><th>Скидка</th></tr></thead><tbody>
@foreach($basket->items as $item)<tr><td>{{ $item['id'] }} / {{ $item['name'] }}</td><td>{{ $item['quantity'] }}</td><td>{{ $item['price'] }}</td><td>{{ $item['subtotal'] }}</td><td>{{ $item['discount'] }}</td></tr>@endforeach
</tbody></table></div>
<h2>Акции → действия → изменения товаров</h2>
@forelse($basket->actions as $action)
<details open><summary>{{ $action->name }}: {{ $action->start_price }} → {{ $action->final_price }}, скидка {{ $action->discount }}</summary>
<p>Исходный ID: {{ $action->snapshot['id'] ?? '—' }}. Приоритет: {{ $action->snapshot['priority'] ?? '—' }}. Порядок: {{ $action->snapshot['sort'] ?? '—' }}.</p>
<p>{{ $action->snapshot['content'] ?? '' }}</p>
@foreach($action->doings as $doing)
<details><summary>{{ $doing->name }}: {{ $doing->start_price }} → {{ $doing->final_price }}, скидка {{ $doing->discount }}</summary>
<p>{{ $doing->type }} / {{ $doing->applie }} / {{ $doing->snapshot['size'] ?? '' }} {{ $doing->type_discount }}.</p>
<p>Условие: {{ \ProductDiscounts\Models\Doing::CONDITIONS[$doing->condition_id] ?? $doing->condition_id }} {{ $doing->snapshot['comparison'] ?? '' }} {{ $doing->snapshot['value'] ?? '' }}. Целевой ID: {{ $doing->snapshot['product_id'] ?? '—' }}.</p>
<div class="scroll"><table><thead><tr><th>ID / товар</th><th>Количество</th><th>До действия</th><th>После действия</th><th>Скидка действия</th></tr></thead><tbody>
@foreach($doing->products as $product)<tr><td>{{ $product->product_id }} / {{ $product->product_name }}</td><td>{{ $product->quantity }}</td><td>{{ $product->start_subtotal }}</td><td>{{ $product->final_subtotal }}</td><td>{{ $product->discount }}</td></tr>@endforeach
</tbody></table></div></details>
@endforeach
</details>
@empty<p>Акции не изменили стоимость корзины.</p>@endforelse
@endsection
