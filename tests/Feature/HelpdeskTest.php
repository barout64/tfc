<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** TFC Helpdesk: staff ask for help, support takes the request and fixes it, the person confirms it works. */
class HelpdeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_printer_problem_from_asking_to_fixed(): void
    {
        Storage::fake('local');
        $amina = User::factory()->create(['name' => 'Amina Juma']);
        $baraka = User::factory()->create(['name' => 'Baraka Mushi']);
        $it = User::factory()->create(['name' => 'Ibrahim IT', 'is_support' => true]);
        $admin = User::factory()->create(['is_admin' => true]);

        // Everyone sees the Helpdesk on the Systems page
        $this->actingAs($amina)->get(route('home'))->assertOk()->assertSee('TFC Helpdesk')->assertSee(route('helpdesk.create'), false);

        // Amina asks, with a photo of the error
        $this->actingAs($amina)->get(route('helpdesk.create'))->assertOk()->assertSee('Printer or scanner');
        $this->actingAs($amina)->post(route('helpdesk.store'), [
            'category' => 'printer', 'priority' => 'urgent', 'subject' => 'Printer on 2nd floor does not print',
            'description' => 'It shows "paper jam" but there is no paper stuck.', 'location' => 'Finance, room 12', 'phone' => '0712 000 111',
            'file' => UploadedFile::fake()->image('error.jpg'),
        ])->assertRedirect();
        $ticket = HelpdeskTicket::query()->firstOrFail();
        $this->assertSame(['open', 'urgent', $amina->id], [$ticket->status, $ticket->priority, $ticket->user_id]);
        $photo = $ticket->messages()->whereNotNull('attachment_path')->firstOrFail();
        Storage::disk('local')->assertExists($photo->attachment_path);
        $this->assertSame(1, Activity::query()->where('action', 'helpdesk_asked')->count());

        // Her colleague does not see it; support and the administrator do
        $this->actingAs($baraka)->get(route('helpdesk.show', $ticket))->assertNotFound();
        $this->actingAs($baraka)->get(route('helpdesk.file', $photo))->assertNotFound();
        $this->actingAs($baraka)->get(route('helpdesk.index'))->assertOk()->assertDontSee('Printer on 2nd floor');
        $this->actingAs($admin)->get(route('helpdesk.index'))->assertOk()->assertSee('Printer on 2nd floor');
        $this->actingAs($it)->get(route('helpdesk.index'))->assertOk()->assertSee('Printer on 2nd floor')->assertSee('Amina Juma')->assertSee('Urgent');

        // Staff cannot take or close requests
        $this->actingAs($baraka)->post(route('helpdesk.assign', $ticket))->assertForbidden();
        $this->actingAs($amina)->post(route('helpdesk.status', $ticket), ['status' => 'resolved'])->assertForbidden();

        // Support takes it and asks a question; Amina answers, which puts it back with support
        $this->actingAs($it)->post(route('helpdesk.assign', $ticket))->assertRedirect();
        $this->assertSame([$it->id, 'in_progress'], [$ticket->refresh()->assigned_to, $ticket->status]);
        $this->actingAs($it)->post(route('helpdesk.status', $ticket), ['status' => 'waiting', 'note' => 'Which printer exactly — the HP or the Canon?'])->assertRedirect();
        $this->actingAs($amina)->get(route('helpdesk.show', $ticket))->assertSee('Support is waiting for your answer')->assertSee('the HP or the Canon');
        $this->actingAs($amina)->post(route('helpdesk.reply', $ticket), ['body' => 'The HP'])->assertRedirect();
        $this->assertSame('in_progress', $ticket->refresh()->status);

        // Fixed: Amina is asked whether it works; first it doesn't, then it does
        $this->actingAs($it)->post(route('helpdesk.status', $ticket), ['status' => 'resolved', 'note' => 'Cleaned the sensor.'])->assertRedirect();
        $this->assertNotNull($ticket->refresh()->resolved_at);
        $this->actingAs($amina)->get(route('helpdesk.show', $ticket))->assertSee('Does it work now?');
        $this->actingAs($amina)->post(route('helpdesk.confirm', $ticket), ['works' => '0'])->assertRedirect();
        $this->assertSame('in_progress', $ticket->refresh()->status);
        $this->actingAs($it)->post(route('helpdesk.status', $ticket), ['status' => 'resolved'])->assertRedirect();
        $this->actingAs($amina)->post(route('helpdesk.confirm', $ticket), ['works' => '1'])->assertRedirect();
        $this->assertSame('closed', $ticket->refresh()->status);
        $this->assertNotNull($ticket->closed_at);

        // Everything is kept on the request, and support can open the photo
        $this->actingAs($it)->get(route('helpdesk.show', $ticket))->assertOk()->assertSee('Ibrahim IT is taking care of this.')
            ->assertSee('confirmed it works')->assertSee('Cleaned the sensor.');
        $this->actingAs($it)->get(route('helpdesk.file', $photo))->assertOk();
        $this->actingAs($admin)->get(route('admin.activity'))->assertOk()->assertSee('Asked the helpdesk:');
    }

    public function test_the_administrator_makes_someone_helpdesk_support(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $it = User::factory()->create(['name' => 'Ibrahim IT', 'email' => 'ibrahim@tfc.go.tz']);
        \Illuminate\Support\Facades\Http::fake();

        $this->actingAs($admin)->get(route('admin.users.edit', $it))->assertOk()->assertSee('Helpdesk support');
        $this->actingAs($admin)->put(route('admin.users.update', $it), ['name' => 'Ibrahim IT', 'email' => 'ibrahim@tfc.go.tz', 'is_active' => '1', 'is_admin' => '0', 'is_support' => '1'])->assertRedirect();
        $this->assertTrue($it->refresh()->isSupport());
        $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('Helpdesk support');

        // A request needs the main details; a file must be a picture or document
        $this->actingAs($it)->post(route('helpdesk.store'), ['category' => 'nonsense', 'priority' => 'normal', 'subject' => '', 'description' => ''])
            ->assertSessionHasErrors(['category', 'subject', 'description']);
        $this->actingAs($it)->post(route('helpdesk.store'), ['category' => 'other', 'priority' => 'normal', 'subject' => 'x', 'description' => 'y',
            'file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
        $this->assertSame(0, HelpdeskTicket::count());
    }
}
