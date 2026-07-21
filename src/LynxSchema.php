<?php namespace ProcessWire;

/**
 * Schema install / uninstall / upgrade for Lynx.
 * Part of the Lynx class via trait composition.
 */
trait LynxSchema {

    public function ___install() {
        parent::___install();
        $db = $this->wire('database');

        $db->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_PROFILES . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL DEFAULT 0,
            slug VARCHAR(128) NOT NULL,
            lang VARCHAR(16) NOT NULL DEFAULT 'en',
            title VARCHAR(255) NOT NULL DEFAULT '',
            bio TEXT NULL,
            avatar VARCHAR(255) NOT NULL DEFAULT '',
            theme VARCHAR(64) NOT NULL DEFAULT 'default',
            font VARCHAR(64) NOT NULL DEFAULT '',
            accent VARCHAR(16) NOT NULL DEFAULT '#1e87f0',
            seo_title VARCHAR(255) NOT NULL DEFAULT '',
            seo_description VARCHAR(512) NOT NULL DEFAULT '',
            og_image VARCHAR(255) NOT NULL DEFAULT '',
            noindex TINYINT(1) NOT NULL DEFAULT 0,
            custom_css TEXT NULL,
            bg_type VARCHAR(16) NOT NULL DEFAULT '',
            bg_value VARCHAR(512) NOT NULL DEFAULT '',
            translations MEDIUMTEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            created INT UNSIGNED NOT NULL DEFAULT 0,
            modified INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_LINKS . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_id INT UNSIGNED NOT NULL,
            label VARCHAR(255) NOT NULL DEFAULT '',
            url VARCHAR(1024) NOT NULL DEFAULT '',
            icon VARCHAR(64) NOT NULL DEFAULT '',
            is_social TINYINT(1) NOT NULL DEFAULT 0,
            translations MEDIUMTEXT NULL,
            start_date INT UNSIGNED NOT NULL DEFAULT 0,
            end_date INT UNSIGNED NOT NULL DEFAULT 0,
            sort INT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY sort (sort),
            KEY profile_active_sort (profile_id, active, sort),
            CONSTRAINT lynx_links_profile_fk FOREIGN KEY (profile_id)
                REFERENCES " . self::TABLE_PROFILES . " (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_CLICKS . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            link_id INT UNSIGNED NOT NULL,
            ts INT UNSIGNED NOT NULL DEFAULT 0,
            ref VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY link_id (link_id),
            KEY link_ts (link_id, ts),
            CONSTRAINT lynx_clicks_link_fk FOREIGN KEY (link_id)
                REFERENCES " . self::TABLE_LINKS . " (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_BLOCKS . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_id INT UNSIGNED NOT NULL,
            type VARCHAR(32) NOT NULL DEFAULT 'gallery',
            title VARCHAR(255) NOT NULL DEFAULT '',
            data MEDIUMTEXT NULL,
            translations MEDIUMTEXT NULL,
            sort INT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY sort (sort),
            KEY profile_active_sort (profile_id, active, sort),
            CONSTRAINT lynx_blocks_profile_fk FOREIGN KEY (profile_id)
                REFERENCES " . self::TABLE_PROFILES . " (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function ___uninstall() {
        $db = $this->wire('database');
        $db->exec("DROP TABLE IF EXISTS " . self::TABLE_BLOCKS);
        $db->exec("DROP TABLE IF EXISTS " . self::TABLE_CLICKS);
        $db->exec("DROP TABLE IF EXISTS " . self::TABLE_LINKS);
        $db->exec("DROP TABLE IF EXISTS " . self::TABLE_PROFILES);
        parent::___uninstall();
    }

    /** Add columns introduced after v101 on existing installs. */
    public function ___upgrade($fromVersion, $toVersion) {
        $db = $this->wire('database');
        $add = function($table, $col, $def) use ($db) {
            $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
            $q->execute(array(':t' => $table, ':c' => $col));
            if(!$q->fetchColumn()) $db->exec("ALTER TABLE $table ADD COLUMN $col $def");
        };
        $addIndex = function($table, $name, $columns) use ($db) {
            $q = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :n");
            $q->execute(array(':t' => $table, ':n' => $name));
            if(!$q->fetchColumn()) $db->exec("ALTER TABLE $table ADD INDEX $name ($columns)");
        };
        // Existing installations may contain legacy orphan rows. Add foreign
        // keys only when doing so is non-destructive; explicit delete logic
        // remains in place for installs where a constraint cannot be added.
        $addForeignKey = function($table, $name, $column, $parent, $parentColumn = 'id') use ($db) {
            $q = $db->prepare("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :n");
            $q->execute(array(':t' => $table, ':n' => $name));
            if($q->fetchColumn()) return;
            $orphans = (int) $db->query("SELECT COUNT(*) FROM $table c LEFT JOIN $parent p
                ON p.$parentColumn = c.$column WHERE p.$parentColumn IS NULL")->fetchColumn();
            if($orphans === 0) {
                $db->exec("ALTER TABLE $table ADD CONSTRAINT $name FOREIGN KEY ($column)
                    REFERENCES $parent ($parentColumn) ON DELETE CASCADE");
            }
        };
        $p = self::TABLE_PROFILES;
        $add($p, 'lang',            "VARCHAR(16) NOT NULL DEFAULT 'en'");
        $add($p, 'seo_title',       "VARCHAR(255) NOT NULL DEFAULT ''");
        $add($p, 'seo_description', "VARCHAR(512) NOT NULL DEFAULT ''");
        $add($p, 'og_image',        "VARCHAR(255) NOT NULL DEFAULT ''");
        $add($p, 'noindex',         "TINYINT(1) NOT NULL DEFAULT 0");
        $add($p, 'custom_css',      "TEXT NULL");
        $add($p, 'font',            "VARCHAR(64) NOT NULL DEFAULT ''");
        $add($p, 'bg_type',         "VARCHAR(16) NOT NULL DEFAULT ''");
        $add($p, 'bg_value',        "VARCHAR(512) NOT NULL DEFAULT ''");
        $add($p, 'translations',    "MEDIUMTEXT NULL");
        $l = self::TABLE_LINKS;
        $add($l, 'is_social',  "TINYINT(1) NOT NULL DEFAULT 0");
        $add($l, 'translations', "MEDIUMTEXT NULL");
        $add($l, 'start_date', "INT UNSIGNED NOT NULL DEFAULT 0");
        $add($l, 'end_date',   "INT UNSIGNED NOT NULL DEFAULT 0");

        // v104: portfolio blocks table
        $db->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_BLOCKS . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_id INT UNSIGNED NOT NULL,
            type VARCHAR(32) NOT NULL DEFAULT 'gallery',
            title VARCHAR(255) NOT NULL DEFAULT '',
            data MEDIUMTEXT NULL,
            translations MEDIUMTEXT NULL,
            sort INT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY sort (sort)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $add(self::TABLE_BLOCKS, 'translations', "MEDIUMTEXT NULL");
        $addIndex(self::TABLE_LINKS, 'profile_active_sort', 'profile_id, active, sort');
        $addIndex(self::TABLE_BLOCKS, 'profile_active_sort', 'profile_id, active, sort');
        $addIndex(self::TABLE_CLICKS, 'link_ts', 'link_id, ts');
        $addForeignKey(self::TABLE_LINKS, 'lynx_links_profile_fk', 'profile_id', self::TABLE_PROFILES);
        $addForeignKey(self::TABLE_BLOCKS, 'lynx_blocks_profile_fk', 'profile_id', self::TABLE_PROFILES);
        $addForeignKey(self::TABLE_CLICKS, 'lynx_clicks_link_fk', 'link_id', self::TABLE_LINKS);
        $this->clearPublicPageCache();
    }
}
