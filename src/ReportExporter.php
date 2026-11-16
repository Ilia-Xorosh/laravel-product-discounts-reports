<?php

namespace ProductDiscounts\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ProductDiscounts\Models\Doing;

class ReportExporter
{
    public function write(Builder $query, string $mode): string
    {
        if (!in_array($mode, ['mini', 'max'], true)) throw new \InvalidArgumentException('Unknown export mode.');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $path = tempnam(sys_get_temp_dir(), 'pd-report-');
        if ($path === false) throw new \RuntimeException('Cannot create export temporary file.');
        try {
            $headings = ['GUID', 'Дата оплаты', 'Статус', 'Источник', 'Начальная сумма', 'Итоговая сумма', 'Скидка корзины',
                'Акция', 'До акции', 'После акции', 'Скидка акции'];
            $headings = array_merge($headings, $mode === 'mini' ? ['Количество уникальных товаров, затронутых акцией'] : [
                'Действие', 'Тип', 'Применение', 'Единица', 'Размер', 'Условие', 'Сравнение', 'Значение',
                'Целевой ID', 'До действия', 'После действия', 'Скидка действия',
                'Товар ID', 'Товар', 'Количество', 'До', 'После', 'Скидка товара',
            ]);
            $rowNumber = 1;
            $sheet->fromArray($headings, null, 'A1');
            foreach ($this->rows($query, $mode) as $row) {
                if (++$rowNumber > (int) config('product-discounts-reports.export_max_rows', 50000) + 1) {
                    throw ValidationException::withMessages(['export' => 'Слишком много строк. Уточните фильтры.']);
                }
                foreach ($row as $column => $value) {
                    if ($value !== null) {
                        // Explicit strings prevent spreadsheet formula injection and preserve leading zeroes in IDs.
                        $sheet->setCellValueExplicit([$column + 1, $rowNumber], $value, is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    }
                }
            }
            $sheet->freezePane('A2');
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            (new Xlsx($spreadsheet))->save($path);
            return $path;
        } catch (\Throwable $e) {
            unlink($path);
            throw $e;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function rows(Builder $query, string $mode): \Generator
    {
        foreach ($query->with('actions.doings.products')->reorder('id')->lazyById(100) as $basket) {
            $base = [$basket->basket_guid, $basket->paid_at?->toIso8601String(), $basket->status, $basket->order_from,
                $basket->start_price, $basket->final_price, $basket->discount];
            if ($basket->actions->isEmpty()) {
                yield [...$base, ...array_fill(0, $mode === 'mini' ? 5 : 22, null)];
            }
            foreach ($basket->actions as $action) {
                $actionValues = [$action->name, $action->start_price, $action->final_price, $action->discount];
                if ($mode === 'mini') {
                    $unique = [];
                    foreach ($action->doings as $doing) foreach ($doing->products as $product) $unique[$product->product_id] = $product->quantity;
                    yield [...$base, ...$actionValues, array_sum($unique)];
                    $base = array_fill(0, 7, null);
                    continue;
                }
                foreach ($action->doings as $doing) {
                    $snapshot = $doing->snapshot;
                    $doingValues = [$doing->name, $doing->type, $doing->applie, $doing->type_discount, (int) ($snapshot['size'] ?? 0),
                        Doing::CONDITIONS[$doing->condition_id] ?? (string) $doing->condition_id,
                        $snapshot['comparison'] ?? null, $snapshot['value'] ?? null, $snapshot['product_id'] ?? null,
                        $doing->start_price, $doing->final_price, $doing->discount];
                    foreach ($doing->products as $product) {
                        yield [...$base, ...$actionValues, ...$doingValues, $product->product_id, $product->product_name,
                            $product->quantity, $product->start_subtotal, $product->final_subtotal, $product->discount];
                        $base = array_fill(0, 7, null); $actionValues = array_fill(0, 4, null); $doingValues = array_fill(0, 12, null);
                    }
                }
            }
        }
    }
}
