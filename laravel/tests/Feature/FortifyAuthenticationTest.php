<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('users');
    Schema::dropIfExists('kode_sekolah');

    Schema::create('kode_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->string('nama_sekolah');
    });

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('username')->unique();
        $table->string('password');
        $table->enum('role', ['admin', 'user'])->default('user');
        $table->unsignedInteger('id_sekolah')->nullable();
        $table->string('nama_sekolah')->nullable();
    });

    DB::table('kode_sekolah')->insert(['id' => 1, 'nama_sekolah' => 'Sekolah A']);
});

it('authenticates a legacy school user and regenerates the session', function (): void {
    DB::table('users')->insert([
        'username' => 'operator',
        'password' => Hash::make('password-rahasia'),
        'role' => 'user',
        'id_sekolah' => 1,
        'nama_sekolah' => 'Sekolah A',
    ]);

    $oldSessionId = session()->getId();

    $this->post('/login', [
        'username' => 'operator',
        'password' => 'password-rahasia',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
    expect(session()->getId())->not->toBe($oldSessionId)
        ->and(auth()->user()->username)->toBe('operator');
});

it('rejects a school user whose school no longer exists', function (): void {
    DB::table('users')->insert([
        'username' => 'orphan',
        'password' => Hash::make('password-rahasia'),
        'role' => 'user',
        'id_sekolah' => 99,
    ]);

    $this->post('/login', [
        'username' => 'orphan',
        'password' => 'password-rahasia',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('allows an admin without a school assignment', function (): void {
    DB::table('users')->insert([
        'username' => 'admin',
        'password' => Hash::make('password-rahasia'),
        'role' => 'admin',
        'id_sekolah' => null,
    ]);

    $this->post('/login', [
        'username' => 'admin',
        'password' => 'password-rahasia',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
});

it('invalidates the session on logout', function (): void {
    DB::table('users')->insert([
        'username' => 'operator',
        'password' => Hash::make('password-rahasia'),
        'role' => 'user',
        'id_sekolah' => 1,
    ]);

    $this->post('/login', [
        'username' => 'operator',
        'password' => 'password-rahasia',
    ]);

    $authenticatedSessionId = session()->getId();

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    expect(session()->getId())->not->toBe($authenticatedSessionId);
});

it('throttles repeated failed logins for the same account', function (): void {
    DB::table('users')->insert([
        'username' => 'operator',
        'password' => Hash::make('password-rahasia'),
        'role' => 'user',
        'id_sekolah' => 1,
    ]);

    foreach (range(1, 5) as $_) {
        $this->post('/login', [
            'username' => 'operator',
            'password' => 'salah',
        ]);
    }

    $this->post('/login', [
        'username' => 'operator',
        'password' => 'password-rahasia',
    ])->assertTooManyRequests();

    $this->assertGuest();
});
