<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\ProtocolLetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** TFC Protocol inside the one TFC system: a template from draft to published, a document from it to a final letter. */
class ProtocolTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $head;

    private User $worker;

    private User $outsider;

    private string $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $hr = Department::query()->create(['name' => 'Human Resources', 'code' => 'HR']);
        $this->hr = (string) $hr->id;
        $other = Department::query()->create(['name' => 'Finance', 'code' => 'FIN']);
        $make = fn ($role, $dept) => User::factory()->create(['protocol_role' => $role, 'department_id' => $dept->id]);
        $this->secretary = $make('secretary', $hr);
        $this->head = $make('head', $hr);
        $this->worker = $make('employee', $hr);
        $this->outsider = $make('employee', $other);
    }

    private function content(array $extra = []): array
    {
        return $extra + ['title' => 'Leave letter', 'category' => 'Official Letter', 'body' => "Dear {{name}},\n\n**Your leave** is approved.\n\n- First point\n- Second point\n\nSigned {{sender_name}}",
            'fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]], 'requiresApproval' => true, 'reviewMonths' => 12, 'attachments' => ''];
    }

    private function publish(array $content = []): string
    {
        $id = $this->actingAs($this->secretary)->postJson('/protocol/api/templates', ['content' => $this->content($content)])->assertOk()->json('id');
        $this->actingAs($this->secretary)->postJson("/protocol/api/templates/{$id}/submit")->assertOk();
        $this->actingAs($this->head)->postJson("/protocol/api/templates/{$id}/approve", ['note' => 'Good'])->assertOk()->assertJsonPath('published.version', 1)->assertJsonPath('draft', null);

        return $id;
    }

    public function test_the_page_needs_the_one_sign_in_and_shows_who_is_there(): void
    {
        $this->get(route('protocol.index'))->assertRedirect(route('login'));
        $this->getJson('/protocol/api/bootstrap')->assertUnauthorized();
        $this->actingAs($this->worker)->get(route('protocol.index'))->assertOk()->assertSee('protocol-app/app.js')->assertSee('csrf-token', false);
        $this->actingAs($this->worker)->getJson('/protocol/api/bootstrap')->assertOk()->assertJsonPath('me.id', (string) $this->worker->id)->assertJsonPath('me.role', 'employee')
            ->assertJsonPath('me.departmentId', $this->hr)->assertJsonPath('settings.orgName', 'Tanzania Fertilizer Company');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->getJson('/protocol/api/bootstrap')->assertJsonPath('me.role', 'admin');
        $this->actingAs($admin)->get('/protocol/uploads/coat-of-arms-320x371.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_a_template_from_draft_to_published_and_who_may_do_what(): void
    {
        // An employee cannot create one; a placeholder without a field cannot be submitted
        $this->actingAs($this->worker)->postJson('/protocol/api/templates', ['content' => $this->content()])->assertForbidden();
        $bad = $this->actingAs($this->secretary)->postJson('/protocol/api/templates', ['content' => $this->content(['body' => 'Dear {{nobody}}'])])->assertOk()->json('id');
        $this->actingAs($this->secretary)->postJson("/protocol/api/templates/{$bad}/submit")->assertStatus(400)->assertJsonPath('error', fn ($e) => str_contains($e, '{{nobody}}'));
        $this->actingAs($this->secretary)->postJson('/protocol/api/templates', ['content' => $this->content(['fields' => [['key' => 'date', 'label' => 'x']]])])->assertStatus(400);

        // Submitted: the secretary cannot approve, the head returns it with a reason, then approves it
        $id = $this->actingAs($this->secretary)->postJson('/protocol/api/templates', ['content' => $this->content()])->assertOk()->json('id');
        $this->actingAs($this->secretary)->postJson("/protocol/api/templates/{$id}/submit")->assertOk()->assertJsonPath('draft.status', 'submitted');
        $this->actingAs($this->secretary)->postJson("/protocol/api/templates/{$id}/approve")->assertForbidden();
        $this->actingAs($this->head)->postJson("/protocol/api/templates/{$id}/return", [])->assertStatus(400);
        $this->actingAs($this->head)->postJson("/protocol/api/templates/{$id}/return", ['note' => 'Fix the title'])->assertOk()->assertJsonPath('draft.status', 'returned');
        $this->assertSame(1, DB::table('protocol_notifications')->where('user_id', $this->secretary->id)->where('text', 'like', '%Fix the title%')->count());
        $this->actingAs($this->secretary)->postJson("/protocol/api/templates/{$id}/submit")->assertOk();
        $this->actingAs($this->head)->postJson("/protocol/api/templates/{$id}/approve")->assertOk()->assertJsonPath('published.version', 1)->assertJsonPath('perms.approve', true);

        // Everyone sees the published one; the draft is only for those who manage it
        $this->actingAs($this->outsider)->getJson("/protocol/api/templates/{$id}")->assertOk()->assertJsonPath('published.title', 'Leave letter');
        $this->actingAs($this->secretary)->putJson("/protocol/api/templates/{$id}", ['content' => $this->content(['title' => 'Leave letter 2'])])->assertOk()->assertJsonPath('draft.title', 'Leave letter 2');
        $this->actingAs($this->outsider)->putJson("/protocol/api/templates/{$id}", ['content' => $this->content()])->assertForbidden();
        $this->actingAs($this->outsider)->getJson("/protocol/api/templates/{$id}")->assertJsonPath('draft', null);
        $this->actingAs($this->secretary)->postJson("/protocol/api/templates/{$id}/restore-version", ['version' => 1])->assertOk()->assertJsonPath('draft.title', 'Leave letter');
        $this->actingAs($this->secretary)->deleteJson("/protocol/api/templates/{$id}/draft")->assertOk()->assertJsonPath('deleted', false);

        // A confidential one stays in its department
        $secret = $this->publish(['confidential' => true, 'title' => 'Secret']);
        $this->actingAs($this->outsider)->getJson("/protocol/api/templates/{$secret}")->assertForbidden();
        $this->assertSame([], array_values(array_filter($this->actingAs($this->outsider)->getJson('/protocol/api/templates')->json(), fn ($t) => $t['id'] === $secret)));
    }

    public function test_a_document_from_draft_to_final_letter_with_word_and_print(): void
    {
        $tpl = $this->publish();

        // Missing what is required: nothing is finished; a draft may be incomplete
        $this->actingAs($this->worker)->postJson('/protocol/api/documents', ['templateId' => $tpl, 'values' => [], 'action' => 'complete'])->assertStatus(400)->assertJsonPath('error', fn ($e) => str_contains($e, 'Name'));
        $id = $this->actingAs($this->worker)->postJson('/protocol/api/documents', ['templateId' => $tpl, 'values' => ['name' => 'Juma'], 'action' => 'save'])->assertOk()->assertJsonPath('status', 'draft')->json('id');

        // Others cannot see it. Finished, it waits for the head of the department
        $this->actingAs($this->outsider)->getJson("/protocol/api/documents/{$id}")->assertForbidden();
        $this->actingAs($this->worker)->putJson("/protocol/api/documents/{$id}", ['values' => ['name' => 'Juma', 'date' => '2026-11-04', 'sender_name' => 'Neema'], 'action' => 'complete'])->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('perms.approve', false);
        $this->actingAs($this->worker)->postJson("/protocol/api/documents/{$id}/approve")->assertForbidden();
        $this->assertCount(1, $this->actingAs($this->head)->getJson('/protocol/api/documents?scope=review')->json());
        $this->actingAs($this->head)->getJson('/protocol/api/summary')->assertOk()->assertJsonPath('pendingDocs', 1);

        // A draft's Word file says so
        $draft = $this->actingAs($this->worker)->get("/protocol/api/documents/{$id}/export?format=doc")->assertOk();
        $this->assertStringContainsString('DRAFT', $this->wordText($draft->getContent()));

        // Approved: a reference number; the Word file has the letter, bold, bullets and letterhead logo
        $this->actingAs($this->head)->postJson("/protocol/api/documents/{$id}/approve")->assertOk()->assertJsonPath('status', 'final')->assertJsonPath('ref', 'TFC/HR/'.date('Y').'/0001');
        $word = $this->actingAs($this->worker)->get("/protocol/api/documents/{$id}/export?format=doc")->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $xml = $this->wordText($word->getContent());
        $this->assertStringNotContainsString('DRAFT', $xml);
        $this->assertStringContainsString('Dear Juma', $xml);
        $this->assertStringContainsString('<w:b/>', $xml);
        $this->assertStringContainsString('First point', $xml);
        $this->assertStringContainsString('TANZANIA FERTILIZER COMPANY', strtoupper($xml));
        $this->assertTrue(simplexml_load_string($xml) !== false, 'the Word file is well-formed');
        $this->assertContains('word/media/image1.png', $this->wordNames($word->getContent()));

        $this->actingAs($this->worker)->get("/protocol/api/documents/{$id}/export?format=print")->assertOk()->assertSee('letter-data')->assertSee('Render.renderLetter')->assertSee('Dear {{name}}', false);

        // Final: no more changes, and the next one gets the next number
        $this->actingAs($this->worker)->putJson("/protocol/api/documents/{$id}", ['values' => [], 'action' => 'save'])->assertForbidden();
        $this->actingAs($this->worker)->deleteJson("/protocol/api/documents/{$id}")->assertForbidden();
        $this->assertCount(1, $this->actingAs($this->worker)->getJson('/protocol/api/documents')->json());
    }

    private function wordNames(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'a real zip');
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($path);

        return $names;
    }

    private function wordText(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);

        return $xml;
    }

    public function test_handbook_images_reports_and_administration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // The handbook: rules, read and acknowledged
        $this->actingAs($this->worker)->postJson('/protocol/api/protocol', ['title' => 'x'])->assertForbidden();
        $rule = $this->actingAs($admin)->postJson('/protocol/api/protocol', ['title' => 'Dress code', 'body' => 'Smart'])->assertOk()->json('id');
        $this->actingAs($this->worker)->postJson("/protocol/api/protocol/{$rule}/ack")->assertOk()->assertJsonPath('acks.0', (string) $this->worker->id);
        $this->actingAs($this->worker)->getJson('/protocol/api/summary')->assertJsonPath('unread', 1);
        $this->actingAs($this->worker)->postJson('/protocol/api/notifications/read')->assertOk();
        $this->actingAs($this->worker)->getJson('/protocol/api/summary')->assertJsonPath('unread', 0);

        // Images: only real ones, kept under the sign-in
        $this->actingAs($this->worker)->postJson('/protocol/api/uploads', ['data' => 'x'])->assertForbidden();
        $this->actingAs($this->secretary)->postJson('/protocol/api/uploads', ['data' => 'data:image/png;base64,'.base64_encode('not an image'), 'width' => 1, 'height' => 1])->assertStatus(400);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $file = $this->actingAs($this->secretary)->postJson('/protocol/api/uploads', ['data' => 'data:image/png;base64,'.base64_encode($png), 'width' => 1, 'height' => 1, 'name' => 'dot'])->assertOk()->json('file');
        $this->actingAs($this->worker)->get("/protocol/uploads/{$file}")->assertOk();
        $this->actingAs($this->worker)->get('/protocol/uploads/nothing-1x1.png')->assertNotFound();

        // Reports and audit
        $this->actingAs($this->worker)->getJson('/protocol/api/reports')->assertForbidden();
        $this->publish();
        $this->actingAs($this->head)->getJson('/protocol/api/reports')->assertOk()->assertJsonPath('templates.published', 1)->assertJsonPath('scopedTo', $this->hr);
        $this->actingAs($admin)->getJson('/protocol/api/reports')->assertOk()->assertJsonCount(2, 'byDepartment');
        $this->actingAs($this->worker)->getJson('/protocol/api/audit')->assertForbidden();
        $this->actingAs($admin)->getJson('/protocol/api/audit')->assertOk()->assertJsonFragment(['action' => 'Added protocol rule']);

        // People are the one list; the administrator cannot lock themself out
        $this->actingAs($this->worker)->getJson('/protocol/api/users')->assertForbidden();
        $new = $this->actingAs($admin)->postJson('/protocol/api/users', ['name' => 'Asha New', 'username' => 'asha@tfc.go.tz', 'password' => 'Str0ngPass!', 'role' => 'head', 'departmentId' => $this->hr])->assertOk()->assertJsonPath('role', 'head')->json('id');
        $this->assertSame('asha@tfc.go.tz', User::query()->find($new)->email);
        $this->actingAs($admin)->putJson("/protocol/api/users/{$admin->id}", ['active' => false])->assertStatus(400);
        $this->actingAs($admin)->putJson("/protocol/api/users/{$new}", ['active' => false])->assertOk();
        $this->assertFalse(User::query()->find($new)->is_active);

        // Departments and settings
        $dep = $this->actingAs($admin)->postJson('/protocol/api/departments', ['name' => 'Legal', 'code' => 'leg'])->assertOk()->assertJsonPath('code', 'LEG')->json('id');
        $this->actingAs($admin)->postJson('/protocol/api/departments', ['name' => 'Other', 'code' => 'LEG'])->assertStatus(400);
        $this->actingAs($admin)->putJson("/protocol/api/departments/{$dep}", ['name' => 'Legal Office', 'code' => 'LEG'])->assertOk();
        $this->actingAs($admin)->putJson('/protocol/api/settings', ['refFormat' => 'NO-NUMBER'])->assertStatus(400);
        $this->actingAs($admin)->putJson('/protocol/api/settings', ['motto' => 'Serving farmers'])->assertOk()->assertJsonPath('motto', 'Serving farmers');
        $this->actingAs($this->worker)->putJson('/protocol/api/settings', ['motto' => 'x'])->assertForbidden();

        // Changing the password is the one password
        $this->actingAs($this->worker)->postJson('/protocol/api/password', ['current' => 'wrong', 'next' => 'Another1Pass'])->assertStatus(400);
        $this->actingAs($this->worker)->postJson('/protocol/api/password', ['current' => 'password', 'next' => 'Another1Pass'])->assertOk();
        $this->assertTrue(password_verify('Another1Pass', $this->worker->fresh()->password));
    }

    public function test_letterhead_positions_are_kept_inside_the_page(): void
    {
        $this->assertSame(73.0, ProtocolLetter::clampX(73, 100));
        $this->assertSame(-11.8, ProtocolLetter::clampX(-50, 100));
        $this->assertSame('4 November 2026', ProtocolLetter::formatDate('2026-11-04'));
    }
}
