<?php

/*
| The TFC systems opened from the portal. Each one trusts a one-minute, one-use sign-in link signed
| with its own key (the same key is in that system's settings). The portal only reads each system's
| user list, to link a TFC person to their account there; it never writes to those systems.
|
| Kilimo is not part of the TFC Portal (it runs on its own, at kilimo.bosscreativegroup.com).
|
| default_access: true means every active TFC person is given access automatically (an account is
| created for them there the first time they're saved, via that system's own key-authenticated
| /tfc/provision endpoint).
*/
return [
    'link_seconds' => 60,

    'systems' => [
        'meeting' => [
            'name' => 'TFC Meetings',
            'about' => 'Plan meetings, send agendas and invitations, keep minutes, decisions and follow-up actions.',
            'url' => env('TFC_MEETING_URL', 'https://meeting.bosscreativegroup.com'),
            'key' => env('TFC_MEETING_KEY'),
            'default_access' => true,
            'users' => [
                'from' => 'sqlite',
                'path' => env('TFC_MEETING_USERS', '/var/www/meeting/data/meetings.db'),
                'sql' => 'select id, name, email as login, role, active from users order by name',
            ],
        ],
        'protocol' => [
            'name' => 'TFC Protocol',
            'about' => 'Official letters and correspondence, written from each department\'s approved templates.',
            'url' => env('TFC_PROTOCOL_URL', 'https://protocol.bosscreativegroup.com'),
            'key' => env('TFC_PROTOCOL_KEY'),
            'default_access' => true,
            'users' => [
                'from' => 'json',
                'path' => env('TFC_PROTOCOL_USERS', '/var/www/protocol/data/db.json'),
            ],
        ],
    ],
];
