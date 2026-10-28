-- ====================================================================
-- DimensioneX PHP Game Engine - MySQL Database Schema
-- ====================================================================

CREATE TABLE IF NOT EXISTS dimx_worlds (
    world_id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    instance_id VARCHAR(64) NOT NULL,
    mode INT DEFAULT 1 COMMENT '1 = running, 0 = game won (Goal)',
    state_json LONGTEXT COMMENT 'Full PHP-serialized world object (base64-encoded)',
    world_src VARCHAR(512) DEFAULT NULL COMMENT 'URL or path to the .dxw source file',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dimx_players (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Numeric auto-increment account ID in the database',
    world_id VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    player_id VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'Transient in-memory session player ID in game engine',
    password VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Bcrypt one-way password hash',
    is_bot TINYINT(1) DEFAULT 0 COMMENT '1 if player is an automated bot/AI player',
    score INT DEFAULT 0,
    container_id VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'ID of room where player is currently located',
    properties_json MEDIUMTEXT COMMENT 'Player attributes/properties (Health, Strength, etc.)',
    items_json MEDIUMTEXT COMMENT 'Inventory items owned by player',
    console_json MEDIUMTEXT COMMENT 'Pending console messages for this player',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_world_name (world_id, name),
    INDEX idx_world (world_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dimx_settings (
    world_id VARCHAR(64) NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    setting_value MEDIUMTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (world_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat messages table (fully concurrent, no locking required for reads/writes)
CREATE TABLE IF NOT EXISTS dimx_messages (
    message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    world_id VARCHAR(64) NOT NULL,
    sender_name VARCHAR(128) NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_world_time (world_id, message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hall of Fame (Goal completions / game victories)
CREATE TABLE IF NOT EXISTS dimx_hall_of_fame (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    world_id VARCHAR(64) NOT NULL,
    player_name VARCHAR(128) NOT NULL,
    player_numeric_id INT UNSIGNED DEFAULT NULL,
    world_title VARCHAR(128) NOT NULL DEFAULT '',
    world_version VARCHAR(32) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_world (world_id),
    INDEX idx_player (player_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
