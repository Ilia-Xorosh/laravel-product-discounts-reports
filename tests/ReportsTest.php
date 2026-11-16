<?php

namespace ProductDiscounts\Reports\Tests;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use ProductDiscounts\Contracts\ProductCatalog;
use ProductDiscounts\Data\Product;
use ProductDiscounts\DiscountCalculator;
use ProductDiscounts\DiscountServiceProvider;
use ProductDiscounts\Models\Action;
use ProductDiscounts\Models\Doing;
use ProductDiscounts\Reports\BasketStatus;
use ProductDiscounts\Reports\Models\Basket;
use ProductDiscounts\Reports\ReportExporter;
use ProductDiscounts\Reports\ReportsServiceProvider;

class ReportsTest extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array { return [DiscountServiceProvider::class, ReportsServiceProvider::class]; }
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t',32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);
        $app['config']->set('product-discounts-reports.admin.enabled', true);
        $app['config']->set('product-discounts-reports.admin.middleware', ['web']);
        $app['config']->set('product-discounts.api.enabled', true);
        $app['config']->set('product-discounts.api.middleware', ['api']);
    }
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname((new \ReflectionClass(DiscountServiceProvider::class))->getFileName()).'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(ProductCatalog::class, fn()=>new class implements ProductCatalog {
            public function find(array $ids): array {
                return ['sku'=>new Product('sku','=HYPERLINK("bad")',10000,100)];
            }
        });
    }
    private function action(string $name = 'Offer', int $size = 10): Action
    {
        return (new Action(['name'=>$name,'priority'=>1,'sort'=>0]))->setRelation('doings',collect([
            new Doing(['name'=>'Step','type'=>'Скидка','applie'=>'На корзину','condition_id'=>8,
                'comparison'=>'Равно','value'=>'SALE','size'=>$size,'type_discount'=>'Процент']),
        ]));
    }
    private function calculate(?string $guid = 'basket-1', ?array $actions = null): array
    {
        return app(DiscountCalculator::class)->calculate([['configurationId'=>'sku','quantity'=>2]], $actions ?? [$this->action()], 'SALE', $guid, 'site');
    }
    public function test_optional_recording_and_recalculation_replace_tree_without_duplicates(): void
    {
        $this->calculate(null); $this->assertDatabaseCount('pd_report_baskets',0);
        $this->calculate();
        $basket=Basket::with('actions.doings.products')->firstOrFail();
        $this->assertSame(18000,$basket->final_price);
        $this->assertSame(2000,$basket->actions[0]->doings[0]->products[0]->discount);
        $this->assertSame('SALE',$basket->applied_promocode);
        $this->calculate('basket-1',[$this->action('Changed',20)]);
        $this->assertDatabaseCount('pd_report_baskets',1);
        $this->assertDatabaseCount('pd_report_actions',1);
        $this->assertDatabaseCount('pd_report_doings',1);
        $this->assertDatabaseCount('pd_report_products',1);
        $this->assertSame(16000,$basket->fresh()->final_price);
        // Recalculation with no promotion must remove stale action statistics.
        $this->calculate('basket-1',[]);
        $this->assertDatabaseCount('pd_report_products',0);
        $this->assertSame(0,$basket->fresh()->discount);
    }
    public function test_snapshots_survive_original_rule_deletion_and_paid_baskets_are_frozen(): void
    {
        $doing=Doing::create($this->action()->doings[0]->getAttributes());
        $action=Action::create(['name'=>'Stored','priority'=>1,'sort'=>0,'actively'=>true,'start'=>now()->subDay(),'end'=>now()->addDay()]);
        $action->doings()->attach($doing);
        $this->calculate('basket-1',[$action]);
        $doing->delete(); $action->delete();
        app(BasketStatus::class)->update('basket-1','paid',now());
        $this->calculate('basket-1',[]);
        $basket=Basket::firstOrFail();
        $this->assertSame(18000,$basket->final_price);
        $this->assertSame('paid',$basket->status);
        $this->get('/admin/product-discount-reports/'.$basket->id)->assertOk()->assertSee('Stored')->assertSee('Step');
    }
    public function test_filters_summary_and_exports_use_identical_selection(): void
    {
        $this->calculate(); $this->calculate('basket-2',[]);
        app(BasketStatus::class)->update('basket-1','paid',now());
        $url='/admin/product-discount-reports?status=paid&type='.urlencode('Скидка').'&action_name_search=Offer&condition_id=8';
        $this->get($url)->assertOk()->assertViewHas('summary',fn($s)=>(int)$s->baskets===1 && (int)$s->discount_total===2000)
            ->assertViewHas('baskets',fn($b)=>$b->total()===1);
        $this->get('/admin/product-discount-reports?end='.now()->toDateString())->assertOk();
        $this->get('/admin/product-discount-reports?date_field=arbitrary')->assertRedirect()->assertSessionHasErrors('date_field');
        $download=$this->get('/admin/product-discount-reports/export/mini?status=paid')->assertOk()->assertDownload('baskets-mini.xlsx');
        unlink($download->baseResponse->getFile()->getPathname());
    }
    public function test_xlsx_contents_formula_safety_and_mini_quantities(): void
    {
        $action=$this->action();
        $second=new Doing(array_replace($action->doings[0]->getAttributes(),['condition_id'=>4,'comparison'=>'Больше или равно','value'=>'1']));
        $action->doings->push($second);
        $this->calculate('basket-1',[$action]);
        foreach (['mini','max'] as $mode) {
            $path=app(ReportExporter::class)->write(Basket::query(),$mode);
            try {
                $sheet=IOFactory::load($path)->getActiveSheet();
                $this->assertSame('basket-1',$sheet->getCell('A2')->getValue());
                if ($mode==='mini') $this->assertSame(2,$sheet->getCell('L2')->getValue());
                else {
                    $this->assertSame(DataType::TYPE_STRING,$sheet->getCell('Y2')->getDataType());
                    $this->assertStringStartsWith('=HYPERLINK',$sheet->getCell('Y2')->getValue());
                    $this->assertNull($sheet->getCell('E3')->getValue());
                }
            } finally { unlink($path); }
        }
    }
    public function test_failure_rolls_back_entire_report_update(): void
    {
        $this->calculate();
        DB::statement("CREATE TRIGGER fail_report BEFORE INSERT ON pd_report_actions BEGIN SELECT RAISE(ABORT,'forced'); END");
        try { $this->calculate('basket-1',[$this->action('Changed',20)]); $this->fail('Expected persistence error'); }
        catch (\Illuminate\Database\QueryException) {}
        $this->assertSame(18000,Basket::firstOrFail()->final_price);
        $this->assertSame('Offer',Basket::firstOrFail()->actions[0]->name);
    }
    public function test_api_records_context_and_ignores_untrusted_payment_status(): void
    {
        $this->postJson('/api/product-discounts/calculate',['items'=>[['configurationId'=>'sku','quantity'=>1]],'basket_guid'=>'api-basket','order_from'=>'app','status'=>'paid'])
            ->assertOk()->assertJsonPath('totalPrice',10000);
        $this->assertSame('new',Basket::firstOrFail()->status);
        $this->assertSame('app',Basket::firstOrFail()->order_from);
    }
    public function test_recording_switch_and_net_zero_action_trace(): void
    {
        config(['product-discounts-reports.recording'=>false]);
        $this->calculate();
        $this->assertDatabaseCount('pd_report_baskets',0);
        config(['product-discounts-reports.recording'=>true]);
        $action=$this->action();
        $rule=array_replace($action->doings[0]->getAttributes(),[
            'condition_id'=>4,'comparison'=>'Больше или равно','value'=>'1','type_discount'=>'Сумма','size'=>1000,
        ]);
        $action->setRelation('doings',collect([new Doing($rule),new Doing(array_replace($rule,['type'=>'Наценка']))]));
        $this->calculate('basket-1',[$action]);
        $basket=Basket::with('actions.doings')->firstOrFail();
        $this->assertSame(0,$basket->discount);
        $this->assertCount(2,$basket->actions[0]->doings);
        $this->assertSame(-1000,$basket->actions[0]->doings[1]->discount);
    }
    public function test_export_limit_is_enforced(): void
    {
        $this->calculate(); $this->calculate('basket-2');
        config(['product-discounts-reports.export_max_rows'=>1]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ReportExporter::class)->write(Basket::query(),'max');
    }
}
