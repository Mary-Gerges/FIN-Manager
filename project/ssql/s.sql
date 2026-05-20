-- ============================================================
-- Finance Tracker Database Schema
-- Compatible with MySQL 8.0+ / MariaDB 10.6+
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ── Users ─────────────────────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                     VARCHAR(100)    NOT NULL,
  `email`                    VARCHAR(150)    NOT NULL,
  `password`                 VARCHAR(255)    NOT NULL COMMENT 'bcrypt hash',
  `currency`                 VARCHAR(10)     NOT NULL DEFAULT 'USD',
  `timezone`                 VARCHAR(50)     NOT NULL DEFAULT 'UTC',
  `monthly_income_goal`      DECIMAL(15,2)   NULL,
  `budget_alerts`            TINYINT(1)      NOT NULL DEFAULT 1,
  `unusual_spending_alerts`  TINYINT(1)      NOT NULL DEFAULT 1,
  `email_verified_at`        TIMESTAMP       NULL,
  `remember_token`           VARCHAR(100)    NULL,
  `created_at`               TIMESTAMP       NULL,
  `updated_at`               TIMESTAMP       NULL,
  `deleted_at`               TIMESTAMP       NULL COMMENT 'soft delete',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `idx_users_email`       (`email`),
  KEY `idx_users_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Personal Access Tokens (Sanctum) ─────────────────────────────────────
CREATE TABLE `personal_access_tokens` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255)    NOT NULL,
  `tokenable_id`   BIGINT UNSIGNED NOT NULL,
  `name`           VARCHAR(255)    NOT NULL,
  `token`          VARCHAR(64)     NOT NULL,
  `abilities`      TEXT            NULL,
  `last_used_at`   TIMESTAMP       NULL,
  `expires_at`     TIMESTAMP       NULL,
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Categories ────────────────────────────────────────────────────────────
CREATE TABLE `categories` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`   BIGINT UNSIGNED NULL    COMMENT 'NULL = system category',
  `name`      VARCHAR(100)    NOT NULL,
  `type`      ENUM('income','expense','both') NOT NULL,
  `icon`      VARCHAR(50)     NOT NULL DEFAULT 'tag',
  `color`     VARCHAR(7)      NOT NULL DEFAULT '#6366f1',
  `is_system` TINYINT(1)      NOT NULL DEFAULT 0,
  `is_active` TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP      NULL,
  `updated_at` TIMESTAMP      NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cat_user_type_active` (`user_id`, `type`, `is_active`),
  KEY `idx_cat_system`           (`is_system`),
  CONSTRAINT `fk_categories_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Transactions ──────────────────────────────────────────────────────────
CREATE TABLE `transactions` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`            BIGINT UNSIGNED NOT NULL,
  `category_id`        BIGINT UNSIGNED NOT NULL,
  `type`               ENUM('income','expense') NOT NULL,
  `amount`             DECIMAL(15,2)   NOT NULL,
  `description`        VARCHAR(255)    NOT NULL,
  `notes`              TEXT            NULL,
  `transaction_date`   DATE            NOT NULL,
  `reference_number`   VARCHAR(100)    NULL,
  `is_recurring`       TINYINT(1)      NOT NULL DEFAULT 0,
  `recurring_interval` ENUM('daily','weekly','monthly','yearly') NULL,
  `tags`               JSON            NULL,
  `created_at`         TIMESTAMP       NULL,
  `updated_at`         TIMESTAMP       NULL,
  `deleted_at`         TIMESTAMP       NULL COMMENT 'soft delete',
  PRIMARY KEY (`id`),
  KEY `idx_txn_user_date`      (`user_id`, `transaction_date`),
  KEY `idx_txn_user_type`      (`user_id`, `type`),
  KEY `idx_txn_user_cat`       (`user_id`, `category_id`),
  KEY `idx_txn_user_date_type` (`user_id`, `transaction_date`, `type`),
  KEY `idx_txn_date`           (`transaction_date`),
  CONSTRAINT `fk_transactions_user`
    FOREIGN KEY (`user_id`)     REFERENCES `users`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transactions_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Budgets ───────────────────────────────────────────────────────────────
CREATE TABLE `budgets` (
  `id`              BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED    NOT NULL,
  `category_id`     BIGINT UNSIGNED    NOT NULL,
  `amount`          DECIMAL(15,2)      NOT NULL,
  `month`           TINYINT UNSIGNED   NOT NULL COMMENT '1-12',
  `year`            SMALLINT UNSIGNED  NOT NULL,
  `alert_threshold` DECIMAL(5,2)       NOT NULL DEFAULT 80.00 COMMENT 'alert at X%',
  `is_active`       TINYINT(1)         NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP          NULL,
  `updated_at`      TIMESTAMP          NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_budget` (`user_id`, `category_id`, `month`, `year`),
  KEY `idx_budget_user_month_year` (`user_id`, `month`, `year`),
  CONSTRAINT `fk_budgets_user`
    FOREIGN KEY (`user_id`)     REFERENCES `users`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budgets_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications ─────────────────────────────────────────────────────────
CREATE TABLE `notifications` (
  `id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`  BIGINT UNSIGNED NOT NULL,
  `type`     VARCHAR(50)     NOT NULL,
  `title`    VARCHAR(200)    NOT NULL,
  `message`  TEXT            NOT NULL,
  `data`     JSON            NULL,
  `is_read`  TINYINT(1)      NOT NULL DEFAULT 0,
  `read_at`  TIMESTAMP       NULL,
  `created_at` TIMESTAMP     NULL,
  `updated_at` TIMESTAMP     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read`    (`user_id`, `is_read`),
  KEY `idx_notif_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
