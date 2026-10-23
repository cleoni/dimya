<?php
declare(strict_types=1);

/**
 * DimensioneX PHP Configuration File
 *
 * Configure your MySQL / MariaDB connection settings here.
 * If credentials are left blank or the database is offline,
 * the engine automatically uses local JSON storage in php/data/.
 */

return [
    'db' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'dbname' => 'dimx_game',
        'user'   => 'root',
        'pass'   => '',
    ],
    // Default world file if none specified via ?world= or ?game=
    'default_world' => 'https://test.dimensionex.net/worlds/world1.dxw',
    // Base URL for images when IMAGESFOLDER is relative
    'base_url' => 'https://test.dimensionex.net',
    // Usernames of test bots that receive UIjson (with diagnostic fields) instead of UIbot
    'testBots' => [
        'BotAlpha',
    ],
];
