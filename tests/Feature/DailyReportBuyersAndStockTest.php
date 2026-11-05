<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\Station;
use App\Models\StationStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Daily reports split by who bought (agro dealers, farmers), and each station's opening and closing stock per month. */
class DailyReportBuyersAndStockTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    private User $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 11, 5, 9, 0, 0, 'Africa/Dar_es_Salaam'));
        $this->station = Station::query()->create(['name' => 'Test Station', 'is_active' => true]);
        $this->reporter = $this->reporterAt($this->station);
    }

    private function reporterAt(Station $station): User
    {
        $user = User::factory()->create();
        $user->forceFill(['station_id' => $station->id])->save();

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function sell(string $date, array $lines)
    {
        return $this->actingAs($this->reporter)->post(route('reports.store'), ['report_date' => $date, 'lines' => $lines]);
    }

    private function line(string $buyer, float $qty, float $price, string $product = 'DAP'): array
    {
        return ['buyer' => $buyer, 'product' => $product, 'unit' => 'Bags', 'quantity' => $qty, 'unit_price' => $price, 'amount' => $qty * $price];
    }

    public function test_the_same_product_is_sold_to_each_buyer_at_its_own_price(): void
    {
        $this->sell('2026-11-03', [$this->line('agro_dealer', 10, 60000), $this->line('farmer', 4, 70000)])->assertSessionHasNoErrors();

        $report = DailyReport::query()->with('lines')->firstOrFail();
        $this->assertEquals(60000, $report->lines->firstWhere('buyer', 'agro_dealer')->unit_price);
        $this->assertEquals(70000, $report->lines->firstWhere('buyer', 'farmer')->unit_price);

        $this->actingAs($this->reporter)->get(route('reports.show', $report))->assertOk()
            ->assertSee('Agro dealers')->assertSee('Farmers')->assertSee('600,000')->assertSee('280,000')->assertSee('880,000');
        $this->actingAs($this->reporter)->get(route('reports.index', ['from' => '2026-11-01']))->assertOk()
            ->assertSee('Agro dealers 600,000')->assertSee('Farmers 280,000');
    }

    public function test_a_day_with_only_one_kind_of_buyer_can_be_saved(): void
    {
        $this->sell('2026-11-03', [$this->line('farmer', 4, 70000)])->assertSessionHasNoErrors();

        $this->assertSame(['farmer'], DailyReport::query()->firstOrFail()->lines->pluck('buyer')->all());
    }

    public function test_an_unknown_buyer_is_refused(): void
    {
        $this->sell('2026-11-03', [$this->line('friend', 4, 70000)])->assertSessionHasErrors('lines.0.buyer');
    }

    public function test_the_form_has_both_buyers_and_reloads_a_saved_day_into_them(): void
    {
        $this->actingAs($this->reporter)->get(route('reports.create'))->assertOk()
            ->assertSee('data-buyer="agro_dealer"', false)->assertSee('data-buyer="farmer"', false)->assertSee("sections[0].closest('form')", false);

        $this->sell('2026-11-03', [$this->line('agro_dealer', 10, 60000)]);
        $this->actingAs($this->reporter)->get(route('reports.create', ['date' => '2026-11-03']))->assertOk()->assertSee('"buyer":"agro_dealer"', false);
    }

    public function test_an_older_report_without_a_buyer_still_opens_and_can_be_edited(): void
    {
        $report = DailyReport::query()->create(['station_id' => $this->station->id, 'report_date' => '2026-11-01', 'submitted_by' => $this->reporter->id]);
        $report->lines()->create(['product' => 'UREA', 'unit' => 'Bags', 'quantity' => 2, 'unit_price' => 50000, 'amount' => 100000]);

        $this->actingAs($this->reporter)->get(route('reports.show', $report))->assertOk()->assertSee('Buyer not recorded');
        $this->actingAs($this->reporter)->get(route('reports.create', ['date' => '2026-11-01']))->assertOk()->assertSee('"buyer":null', false);
        $this->sell('2026-11-01', [['product' => 'UREA', 'unit' => 'Bags', 'quantity' => 3, 'amount' => 150000]])->assertSessionHasNoErrors();
        $this->actingAs($this->reporter)->get(route('reports.stock'))->assertOk()->assertSee('3 sold in older reports, buyer not recorded');
    }

    public function test_opening_and_closing_stock_with_what_was_sold_to_each_buyer(): void
    {
        $this->sell('2026-11-03', [$this->line('agro_dealer', 10, 60000), $this->line('farmer', 4, 70000)]);
        $this->sell('2026-10-31', [$this->line('farmer', 99, 70000)]);   // another month

        $this->actingAs($this->reporter)->post(route('reports.stock.store'), ['month' => '2026-11', 'rows' => [
            ['product' => 'DAP', 'unit' => 'Bags', 'opening' => 100, 'closing' => 80],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('reports.stock', ['month' => '2026-11']));

        $this->assertSame($this->station->id, StationStock::query()->firstOrFail()->station_id);
        // 100 − 14 sold = 86 expected, 80 counted: 6 to explain
        $page = $this->actingAs($this->reporter)->get(route('reports.stock', ['month' => '2026-11']))->assertOk();
        $page->assertSee('name="rows[', false)->assertSee('-6');
        $rows = collect($page->viewData('rows'))->keyBy('product');
        $this->assertEquals(10, $rows['DAP']['agro_dealer']);
        $this->assertEquals(4, $rows['DAP']['farmer']);
        $this->assertEquals(-6, $rows['DAP']['difference']);
    }

    public function test_this_months_opening_starts_from_last_months_closing_until_entered(): void
    {
        $this->actingAs($this->reporter)->post(route('reports.stock.store'), ['month' => '2026-10', 'rows' => [
            ['product' => 'DAP', 'unit' => 'Bags', 'opening' => 50, 'closing' => 30],
        ]]);

        $page = $this->actingAs($this->reporter)->get(route('reports.stock', ['month' => '2026-11']))->assertOk()->assertSee("From last month's closing", false);
        $this->assertEquals(30, collect($page->viewData('rows'))->firstWhere('product', 'DAP')['opening']);
    }

    public function test_saving_again_replaces_and_clearing_a_row_removes_it(): void
    {
        $post = fn ($opening) => $this->actingAs($this->reporter)->post(route('reports.stock.store'), ['month' => '2026-11', 'rows' => [
            ['product' => 'DAP', 'unit' => 'Bags', 'opening' => $opening, 'closing' => null],
        ]]);
        $post(100);
        $post(120);
        $this->assertEquals(120, StationStock::query()->sole()->opening);

        $post(null);
        $this->assertSame(0, StationStock::query()->count());
    }

    public function test_months_are_october_2026_up_to_now(): void
    {
        $post = fn ($month) => $this->actingAs($this->reporter)->post(route('reports.stock.store'), ['month' => $month, 'rows' => [['product' => 'DAP', 'unit' => 'Bags', 'opening' => 1]]]);

        $post('2026-09')->assertSessionHasErrors('month');
        $post('2026-12')->assertSessionHasErrors('month');
        $post('2026-10')->assertSessionHasNoErrors();
    }

    public function test_a_station_keeps_to_its_own_and_an_administrator_sees_every_station(): void
    {
        $other = Station::query()->create(['name' => 'Other Station', 'is_active' => true]);
        $this->actingAs($this->reporterAt($other))->post(route('reports.stock.store'), ['month' => '2026-11', 'rows' => [['product' => 'DAP', 'unit' => 'Bags', 'opening' => 77]]]);

        // Their own page, whatever station is asked for
        $page = $this->actingAs($this->reporter)->get(route('reports.stock', ['month' => '2026-11', 'station_id' => $other->id]))->assertOk();
        $this->assertTrue($page->viewData('station')->is($this->station));
        $this->assertNull(collect($page->viewData('rows'))->firstWhere('opening', 77));
        $this->actingAs($this->reporter)->post(route('reports.stock.store'), ['station_id' => $other->id, 'month' => '2026-11', 'rows' => [['product' => 'DAP', 'unit' => 'Bags', 'opening' => 1]]])
            ->assertSessionHasErrors('station_id');

        // The administrator: all stations, then one, and can enter for it
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('reports.stock', ['month' => '2026-11']))->assertOk()->assertSee('Other Station')->assertSee('77')->assertDontSee('name="rows[', false);
        $this->actingAs($admin)->post(route('reports.stock.store'), ['station_id' => $this->station->id, 'month' => '2026-11', 'rows' => [['product' => 'DAP', 'unit' => 'Bags', 'opening' => 5]]])
            ->assertSessionHasNoErrors()->assertRedirect(route('reports.stock', ['month' => '2026-11', 'station_id' => $this->station->id]));
        $this->assertSame(2, StationStock::query()->count());
    }
}
