<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;

/**
 * Brings the data of the old separate Meeting and Protocol systems into this one, once. Their people are matched to
 * the TFC people through the portal's links, then by email. It only reads the old systems. People who could not be
 * matched are added switched off, so that their history stays and an administrator can decide about them.
 */
class ImportOldSystems extends Command
{
    protected $signature = 'tfc:import-old-systems
        {--meeting-db=/var/www/meeting/data/meetings.db}
        {--meeting-uploads=/var/www/meeting/uploads}
        {--protocol-db=/var/www/protocol/data/db.json}
        {--protocol-uploads=/var/www/protocol/data/uploads}
        {--fresh : Empty the Meetings and Protocol tables first and read everything again}';

    protected $description = 'One-time copy of the old Meeting and Protocol systems into the TFC system.';

    /** @var array<string, int> */
    private array $departments = [];

    /** @var list<string> */
    private array $added = [];

    public function handle(): int
    {
        $meetingDb = (string) $this->option('meeting-db');
        $protocolDb = (string) $this->option('protocol-db');
        foreach ([$meetingDb, $protocolDb] as $path) {
            if (! is_readable($path)) {
                $this->error("Cannot read {$path} - nothing changed.");

                return self::FAILURE;
            }
        }
        $has = DB::table('meetings')->exists() || DB::table('protocol_templates')->exists() || DB::table('protocol_rules')->exists();
        if ($has && ! $this->option('fresh')) {
            $this->error('Meetings or Protocol already have data here - nothing changed. Use --fresh to replace it with a new copy.');

            return self::FAILURE;
        }

        $db = new PDO('sqlite:'.$meetingDb, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $old = json_decode((string) file_get_contents($protocolDb), true, flags: JSON_THROW_ON_ERROR);

        $files = DB::transaction(function () use ($db, $old) {
            $this->option('fresh') && $this->emptyTables();
            $files = $this->meetings($db);
            $this->protocol($old);

            return $files;
        });

        // The database is in; now the files
        $copied = $missing = 0;
        foreach ($files as $stored) {
            $from = rtrim((string) $this->option('meeting-uploads'), '/')."/{$stored}";
            is_readable($from) ? ++$copied && Storage::disk('local')->put("meetings/{$stored}", (string) file_get_contents($from)) : $missing++;
        }
        foreach ($old['uploads'] ?? [] as $u) {
            $from = rtrim((string) $this->option('protocol-uploads'), '/')."/{$u['file']}";
            is_readable($from) ? ++$copied && Storage::disk('local')->put("protocol/{$u['file']}", (string) file_get_contents($from)) : $missing++;
        }

        $count = fn (string $t) => DB::table($t)->count();
        $this->info(sprintf('Meetings: %d meetings, %d participants, %d agenda items, %d minutes, %d decisions, %d action items, %d files.', $count('meetings'), $count('meeting_participants'), $count('meeting_agenda_items'), $count('meeting_minutes'), $count('meeting_decisions'), $count('meeting_action_items'), $count('meeting_attachments')));
        $this->info(sprintf('Protocol: %d templates, %d documents, %d handbook rules, %d images.', $count('protocol_templates'), $count('protocol_documents'), $count('protocol_rules'), $count('protocol_uploads')));
        $this->info("Files copied: {$copied}".($missing ? ", MISSING: {$missing}" : ''));
        if ($this->added) {
            $this->warn(count($this->added).' people had no TFC login, and were added switched off (an administrator can switch on the real ones and give them a password):');
            foreach ($this->added as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }

    private function emptyTables(): void
    {
        foreach (['meeting_notifications', 'meeting_attachments', 'meeting_action_items', 'meeting_audience', 'meeting_group_members', 'meeting_groups', 'meeting_decisions', 'meeting_minutes', 'meeting_agenda_items', 'meeting_participants', 'meetings', 'meeting_rooms',
            'protocol_counters', 'protocol_notifications', 'protocol_audit', 'protocol_rules', 'protocol_documents', 'protocol_templates', 'protocol_settings'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('protocol_uploads')->where('id', '!=', 'coat-of-arms')->delete();
    }

    private function department(string $name, ?string $code = null): int
    {
        $key = mb_strtolower(trim($name));
        $id = $this->departments[$key] ??= Department::query()->whereRaw('lower(name) = ?', [$key])->value('id') ?? Department::query()->create(['name' => trim($name)])->id;
        if ($code && ! Department::query()->where('code', $code)->exists()) {
            Department::query()->where('id', $id)->whereNull('code')->update(['code' => $code]);
        }

        return $id;
    }

    /** The TFC person for an old account: by the portal's link, then by email, else added switched off. */
    private function person(string $system, string $remoteId, string $name, ?string $email, string $from): User
    {
        $linked = DB::table('system_links')->where('system', $system)->where('remote_id', $remoteId)->value('user_id');
        $user = $linked ? User::query()->find($linked) : null;
        $email = $email ? strtolower(trim($email)) : Str::slug($from, '.').'@old-'.$system.'.tfc.local';
        $user ??= User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($user) {
            return $user;
        }
        $user = new User(['name' => $name, 'email' => $email, 'password' => Str::random(40), 'is_active' => false]);
        $user->save();
        $this->added[] = "{$name} <{$email}> (from {$system})";

        return $user;
    }

    /** @return list<string> the attachment files to copy */
    private function meetings(PDO $db): array
    {
        $rows = fn (string $table) => $db->query("SELECT * FROM {$table}")->fetchAll();
        $departmentNames = array_column($rows('departments'), 'name', 'id');
        $people = [];
        foreach ($rows('users') as $u) {
            $user = $this->person('meeting', (string) $u['id'], $u['name'], $u['email'], $u['email']);
            $user->meeting_role = $u['role'];
            $user->job_title = $user->job_title ?: $u['job_title'];
            if ($u['department_id'] && isset($departmentNames[$u['department_id']])) {
                $user->department_id = $this->department($departmentNames[$u['department_id']]);
            }
            $user->save();
            $people[(int) $u['id']] = $user->id;
        }
        $person = fn ($id) => $id === null ? null : ($people[(int) $id] ?? null);
        $some = fn (array $row, array $columns) => array_intersect_key($row, array_flip($columns));

        foreach ($rows('rooms') as $r) {
            DB::table('meeting_rooms')->insert($some($r, ['id', 'name', 'capacity', 'location', 'equipment']));
        }
        foreach ($rows('meetings') as $m) {
            DB::table('meetings')->insert($some($m, ['id', 'title', 'description', 'meeting_type', 'start_time', 'end_time', 'room_id', 'online_link', 'status', 'quorum', 'series_id', 'started_at', 'ended_at', 'created_at']) + ['organizer_id' => $person($m['organizer_id'])]);
        }
        foreach ($rows('participants') as $p) {
            DB::table('meeting_participants')->insertOrIgnore(['meeting_id' => $p['meeting_id'], 'user_id' => $person($p['user_id']), 'rsvp' => $p['rsvp'], 'attendance' => $p['attendance']]);
        }
        foreach ($rows('agenda_items') as $a) {
            DB::table('meeting_agenda_items')->insert($some($a, ['id', 'meeting_id', 'title', 'description', 'duration', 'position', 'started_at', 'ended_at']) + ['presenter_id' => $person($a['presenter_id'])]);
        }
        foreach ($rows('meetings') as $m) {
            DB::table('meetings')->where('id', $m['id'])->update(['current_agenda_id' => $m['current_agenda_id'] ?? null]);
        }
        foreach ($rows('minutes') as $x) {
            DB::table('meeting_minutes')->insert($some($x, ['meeting_id', 'content', 'status', 'updated_at', 'approved_at']) + ['updated_by' => $person($x['updated_by']), 'approved_by' => $person($x['approved_by'])]);
        }
        foreach ($rows('decisions') as $d) {
            DB::table('meeting_decisions')->insert($some($d, ['id', 'meeting_id', 'agenda_item_id', 'text', 'votes_for', 'votes_against', 'votes_abstain', 'outcome', 'created_at']) + ['proposer_id' => $person($d['proposer_id']), 'seconder_id' => $person($d['seconder_id'])]);
        }
        foreach ($rows('user_groups') as $g) {
            DB::table('meeting_groups')->insert($some($g, ['id', 'name', 'description', 'color', 'created_at']) + ['created_by' => $person($g['created_by'])]);
        }
        foreach ($rows('user_group_members') as $g) {
            DB::table('meeting_group_members')->insertOrIgnore(['group_id' => $g['group_id'], 'user_id' => $person($g['user_id'])]);
        }
        foreach ($rows('meeting_audience') as $a) {
            $ref = $a['kind'] === 'department' && isset($departmentNames[$a['ref_id']]) ? $this->department($departmentNames[$a['ref_id']]) : $a['ref_id'];
            DB::table('meeting_audience')->insertOrIgnore(['meeting_id' => $a['meeting_id'], 'kind' => $a['kind'], 'ref_id' => $ref]);
        }
        foreach ($rows('action_items') as $a) {
            DB::table('meeting_action_items')->insert($some($a, ['id', 'meeting_id', 'title', 'description', 'due_date', 'status', 'created_at', 'completed_at']) + ['assignee_id' => $person($a['assignee_id'])]);
        }
        $files = [];
        foreach ($rows('attachments') as $a) {
            DB::table('meeting_attachments')->insert($some($a, ['id', 'meeting_id', 'filename', 'stored_name', 'size', 'uploaded_at']) + ['agenda_item_id' => $a['agenda_item_id'] ?? null, 'uploaded_by' => $person($a['uploaded_by'])]);
            $files[] = $a['stored_name'];
        }
        foreach ($rows('notifications') as $n) {
            $link = (string) $n['link'];
            if ($link !== '' && ! str_starts_with($link, '/meetings/')) {
                $link = '/meetings'.($link === '/' ? '' : $link);   // the old addresses, inside this system
            }
            $person($n['user_id']) && DB::table('meeting_notifications')->insert(['id' => $n['id'], 'user_id' => $person($n['user_id']), 'message' => $n['message'], 'link' => $link ?: null, 'is_read' => (bool) $n['is_read'], 'created_at' => $n['created_at']]);
        }

        return $files;
    }

    private function protocol(array $old): void
    {
        $departments = [];
        foreach ($old['departments'] ?? [] as $d) {
            $departments[$d['id']] = (string) $this->department($d['name'], $d['code'] ?? null);
        }
        $people = [];
        foreach ($old['users'] ?? [] as $u) {
            $user = $this->person('protocol', (string) $u['id'], $u['name'], $u['tfcEmail'] ?? null, $u['username']);
            $user->protocol_role = $u['role'];
            $user->job_title = $user->job_title ?: ($u['title'] ?? null);
            $user->department_id = $user->department_id ?: ((int) ($departments[$u['departmentId'] ?? ''] ?? 0) ?: null);
            $user->save();
            $people[$u['id']] = (string) $user->id;
        }

        // Inside the records, every person and department is named by its id: move those to the ids here
        $remap = function (mixed $value) use (&$remap, $people, $departments) {
            if (! is_array($value)) {
                return $value;
            }
            foreach ($value as $k => $v) {
                $value[$k] = match (true) {
                    in_array($k, ['createdBy', 'updatedBy', 'submittedBy', 'approvedBy', 'returnedBy', 'by'], true) => is_string($v) ? ($people[$v] ?? null) : $v,
                    $k === 'departmentId' => is_string($v) ? ($departments[$v] ?? null) : $v,
                    $k === 'acks' => array_values(array_filter(array_map(fn ($id) => $people[$id] ?? null, (array) $v))),
                    default => $remap($v),
                };
            }

            return $value;
        };
        foreach (['templates' => 'protocol_templates', 'documents' => 'protocol_documents', 'protocol' => 'protocol_rules'] as $from => $table) {
            foreach ($old[$from] ?? [] as $record) {
                DB::table($table)->insert(['id' => $record['id'], 'data' => json_encode($remap($record), JSON_UNESCAPED_UNICODE)]);
            }
        }
        foreach ($old['uploads'] ?? [] as $u) {
            DB::table('protocol_uploads')->updateOrInsert(['id' => $u['id']], ['file' => $u['file'], 'name' => $u['name'], 'type' => $u['type'], 'size' => $u['size'], 'by' => isset($people[$u['by'] ?? '']) ? (int) $people[$u['by']] : null, 'at' => $u['at']]);
        }
        foreach ($old['notifications'] ?? [] as $n) {
            if (isset($people[$n['userId']])) {
                DB::table('protocol_notifications')->insert(['user_id' => (int) $people[$n['userId']], 'text' => $n['text'], 'link' => $n['link'] ?? '', 'at' => $n['at'], 'read' => (bool) ($n['read'] ?? false)]);
            }
        }
        foreach ($old['audit'] ?? [] as $a) {
            DB::table('protocol_audit')->insert(['at' => $a['at'], 'user_id' => isset($people[$a['userId'] ?? '']) ? (int) $people[$a['userId']] : null, 'action' => $a['action'], 'detail' => $a['detail'] ?? '']);
        }
        foreach ($old['counters'] ?? [] as $key => $n) {
            DB::table('protocol_counters')->insert(['key' => $key, 'n' => $n]);
        }
        $settings = $old['settings'] ?? [];
        unset($settings['showDemoLogins']);
        DB::table('protocol_settings')->delete();
        DB::table('protocol_settings')->insert(['data' => json_encode($settings + ['showDemoLogins' => false], JSON_UNESCAPED_UNICODE)]);
    }
}
