<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Buat 5 user dummy
        $users = [
            ['name' => 'Ahmad Kurniawan', 'email' => 'ahmad@example.com'],
            ['name' => 'Budi Santoso',    'email' => 'budi@example.com'],
            ['name' => 'Dina Pratiwi',    'email' => 'dina@example.com'],
            ['name' => 'Rizky Hidayat',   'email' => 'rizky@example.com'],
            ['name' => 'Nurul Fatimah',   'email' => 'nurul@example.com'],
        ];

        $createdUsers = [];
        foreach ($users as $userData) {
            $createdUsers[] = User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password'  => Hash::make('password'),
                    'is_online' => false,
                ])
            );
        }

        // Buat group "Kelompok 5 - PWL"
        $group1 = Group::firstOrCreate(
            ['name' => 'Kelompok 5 - PWL'],
            ['created_by' => $createdUsers[0]->id]
        );
        // Tambah semua user ke group
        foreach ($createdUsers as $u) {
            $role = $u->id === $createdUsers[0]->id ? 'admin' : 'member';
            $group1->members()->syncWithoutDetaching([$u->id => ['role' => $role]]);
        }

        // Buat group "Kelas TI-4A"
        $group2 = Group::firstOrCreate(
            ['name' => 'Kelas TI-4A'],
            ['created_by' => $createdUsers[0]->id]
        );
        foreach (array_slice($createdUsers, 0, 3) as $u) {
            $role = $u->id === $createdUsers[0]->id ? 'admin' : 'member';
            $group2->members()->syncWithoutDetaching([$u->id => ['role' => $role]]);
        }

        // Buat beberapa pesan dummy di group 1
        $dummyGroupMessages = [
            ['sender' => 1, 'body' => 'Hai semua! Sudah mulai ngerjain tugas chat app-nya belum?'],
            ['sender' => 0, 'body' => 'Udah dong, lagi setup Laravel sama Reverb-nya nih'],
            ['sender' => 3, 'body' => 'Sama! Websocket-nya sudah connect belum? Aku masih error di broadcasting'],
            ['sender' => 0, 'body' => 'Sudah bisa! Kunci utamanya di .env BROADCAST_CONNECTION=reverb'],
        ];

        foreach ($dummyGroupMessages as $msg) {
            Message::firstOrCreate(
                [
                    'sender_id' => $createdUsers[$msg['sender']]->id,
                    'group_id'  => $group1->id,
                    'body'      => $msg['body'],
                ],
                [
                    'receiver_id' => null,
                ]
            );
        }

        // Pesan private antara Ahmad & Budi
        Message::firstOrCreate(
            [
                'sender_id'   => $createdUsers[1]->id,
                'receiver_id' => $createdUsers[0]->id,
                'body'        => 'Hei Ahmad, bisa minta tolong jelaskan tentang WebSocket?',
            ],
            ['group_id' => null]
        );
        Message::firstOrCreate(
            [
                'sender_id'   => $createdUsers[0]->id,
                'receiver_id' => $createdUsers[1]->id,
                'body'        => 'Tentu! WebSocket itu protocol komunikasi dua arah antara client dan server.',
            ],
            ['group_id' => null]
        );

        $this->command->info('Seeder selesai! Login dengan email: ahmad@example.com, password: password');
    }
}
