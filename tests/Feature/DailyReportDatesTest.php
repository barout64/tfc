<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Which days a station's daily report can be written for: from 1 October 2026 up to today. */
class DailyReportDatesTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 11, 5, 9, 0, 0, 'Africa/Dar_es_Salaam'));
        $station = Station::query()->create(['name' => 'Test Station', 'is_active' => true]);
        $this->reporter = User::factory()->create();
        $this->reporter->forceFill(['station_id' => $station->id])->save();
    }

    private function report(string $date)
    {
        return $this->actingAs($this->reporter)->post(route('reports.store'), ['report_date' => $date, 'lines' => [
            ['product' => 'DAP', 'unit' => 'Bags', 'quantity' => 10, 'unit_price' => 1000, 'amount' => 10000],
        ]]);
    }

    public function test_the_first_of_october_can_be_filled_in(): void
    {
        $this->report('2026-10-01')->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('2026-10-01', DailyReport::query()->firstOrFail()->report_date->toDateString());
    }

    public function test_a_day_past_the_old_fourteen_days_can_be_filled_in(): void
    {
        $this->report('2026-10-15')->assertSessionHasNoErrors();

        $this->assertSame(1, DailyReport::query()->count());
    }

    public function test_before_the_first_of_october_is_refused(): void
    {
        $this->report('2026-09-30')->assertSessionHasErrors('report_date');

        $this->assertSame(0, DailyReport::query()->count());
    }

    public function test_a_future_day_is_still_refused(): void
    {
        $this->report('2026-11-06')->assertSessionHasErrors('report_date');
    }

    public function test_the_date_box_offers_only_those_days(): void
    {
        $this->actingAs($this->reporter)->get(route('reports.create'))->assertOk()
            ->assertSee('min="2026-10-01"', false)->assertSee('max="2026-11-05"', false);
    }
}
