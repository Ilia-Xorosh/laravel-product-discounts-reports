<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Статистика скидок</title>
<style>
body{font:16px system-ui,sans-serif;max-width:1200px;margin:24px auto;padding:0 16px;background:#f7f8fb;color:#172033}
a{color:#2456ba}table{width:100%;border-collapse:collapse;background:white}td,th{padding:10px;text-align:left;border-bottom:1px solid #ddd}
label{display:block;margin:10px 0}input,select{display:block;padding:7px;max-width:100%;box-sizing:border-box}button{padding:8px 16px}
.filters{display:flex;gap:16px;flex-wrap:wrap}.summary{display:flex;gap:24px;flex-wrap:wrap;padding:16px;background:#e2f3e7}
details{padding:12px;margin:12px 0;background:white;border:1px solid #ddd}summary{cursor:pointer}.scroll{overflow:auto}.errors{color:#9b1429}
</style></head><body>
<nav><a href="{{ route('product-discounts-reports.index') }}">Статистика корзин</a></nav>
@if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<p>Все суммы в копейках/центах. Отрицательная скидка означает наценку.</p>
@yield('content')
</body></html>
