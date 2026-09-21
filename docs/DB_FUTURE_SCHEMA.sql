-- Blueprint only. Do not run in the no-DB prototype.
CREATE TABLE sync_import_events (
  event_id CHAR(36) PRIMARY KEY,
  event_type VARCHAR(48) NOT NULL,
  occurred_at DATETIME NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE learner_identities (
  learner_hash CHAR(64) PRIMARY KEY,
  created_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL
);

CREATE TABLE learner_devices (
  device_hash CHAR(64) PRIMARY KEY,
  learner_hash CHAR(64) NOT NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  status ENUM('active','reset','revoked') NOT NULL DEFAULT 'active',
  INDEX idx_device_learner (learner_hash)
);

CREATE TABLE learning_journeys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_uuid CHAR(36) NOT NULL,
  learner_hash CHAR(64) NOT NULL,
  device_hash CHAR(64) NULL,
  journey_total SMALLINT UNSIGNED NOT NULL,
  score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_room_learner (room_uuid, learner_hash)
);

CREATE TABLE learner_card_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  journey_id BIGINT UNSIGNED NOT NULL,
  card_uuid CHAR(36) NOT NULL,
  answer_id VARCHAR(80) NOT NULL,
  is_correct TINYINT(1) NULL,
  points SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  answered_at DATETIME NOT NULL,
  UNIQUE KEY uq_journey_card (journey_id, card_uuid)
);

CREATE TABLE offline_learning_events (
  event_id CHAR(36) PRIMARY KEY,
  learner_hash CHAR(64) NOT NULL,
  device_hash CHAR(64) NOT NULL,
  card_uuid CHAR(36) NOT NULL,
  answer_id VARCHAR(80) NOT NULL,
  is_correct TINYINT(1) NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'offline_reserve',
  client_occurred_at DATETIME NULL,
  received_at DATETIME NOT NULL,
  INDEX idx_offline_learner_time (learner_hash, received_at),
  INDEX idx_offline_card (card_uuid)
);

CREATE TABLE learner_seen_cards (
  learner_hash CHAR(64) NOT NULL,
  card_uuid CHAR(36) NOT NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  PRIMARY KEY (learner_hash, card_uuid)
);

-- v0.6.x distributed-content metadata. These tables are optional future durability/control-center
-- records; the signed-pack files themselves may remain object/file storage assets.
CREATE TABLE content_pack_releases (
  pack_uuid CHAR(36) PRIMARY KEY,
  pack_id VARCHAR(64) NOT NULL,
  pillar VARCHAR(32) NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  card_count SMALLINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  signing_key_id VARCHAR(32) NOT NULL,
  signature TEXT NOT NULL,
  published_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_pack_version (pack_id, version_no),
  INDEX idx_pack_pillar (pillar, published_at)
);

CREATE TABLE content_manifest_releases (
  manifest_uuid CHAR(36) PRIMARY KEY,
  manifest_sha256 CHAR(64) NOT NULL UNIQUE,
  signing_key_id VARCHAR(32) NOT NULL,
  signature TEXT NOT NULL,
  pack_count INT UNSIGNED NOT NULL,
  card_count INT UNSIGNED NOT NULL,
  published_at DATETIME NOT NULL,
  revoked_at DATETIME NULL
);
