<?php
declare(strict_types=1);

/**
 * DimensioneY Configuration Template (config.sample.php)
 *
 * Copy this file to `config.php` and adjust the settings to match your environment.
 * DimensioneY is a single-world multiplayer virtual world and interactive fiction engine.
 * One world per installation, configured authoritatively on the server.
 */

return [
    // -------------------------------------------------------------------------
    // Database Connection Settings
    // -------------------------------------------------------------------------
    // Configure your MySQL / MariaDB connection credentials here.
    // If credentials are left blank, host is unreachable, or the database is offline,
    // the engine automatically falls back to local file persistence in `php/data/`.
    'db' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'dbname' => 'dimya',
        'user'   => 'dbuser',
        'pass'   => 'dbpass',
    ],

    // -------------------------------------------------------------------------
    // Administrator Password
    // -------------------------------------------------------------------------
    // Required to authenticate maintenance, world re-seeding, and reset
    // operations via the web maintenance console (`php/maint.php`).
    'adminPasswd' => 'admin',

    // -------------------------------------------------------------------------
    // World Definition File (.dxw)
    // -------------------------------------------------------------------------
    // The single authoritative world definition file for this installation.
    // Can be an absolute/relative local file path or a remote HTTP/HTTPS URL.
    // (Legacy alias 'default_world' is also supported as fallback).
    'world_file' => __DIR__ . '/../docs/dxw-examples/world1.dxw',

    // -------------------------------------------------------------------------
    // World Clock Tick Interval (seconds)
    // -------------------------------------------------------------------------
    // The game engine evaluates a global clock tick on incoming client requests.
    // When elapsed time since the previous tick is >= tickLength (default: 30 seconds),
    // the global 'onTick' event is triggered, and all characters evaluate their
    // periodic 'Living' routine.
    'tickLength' => 30,

    // -------------------------------------------------------------------------
    // Base URL
    // -------------------------------------------------------------------------
    // Base URL for web resources, client scripts, and media assets
    // when media directives (e.g. IMAGESFOLDER, sounds) use relative paths.
    'base_url' => 'http://localhost',

    // -------------------------------------------------------------------------
    // Test Bots (UIjson vs UIbot)
    // -------------------------------------------------------------------------
    // List of bot account nicknames that should receive diagnostic UIjson
    // responses (including hidden items and internal inspect fields) instead
    // of standard gameplay UIbot snapshots.
    'testBots' => [
        'testbot',
    ],
];
