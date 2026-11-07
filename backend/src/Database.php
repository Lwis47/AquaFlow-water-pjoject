<?php

declare(strict_types=1);

namespace AquaFlow;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $storagePath = dirname(__DIR__) . '/storage';
        $databasePath = $storagePath . '/aquaflow.sqlite';

        if (!is_dir($storagePath) && !mkdir($storagePath, 0775, true) && !is_dir($storagePath)) {
            throw new RuntimeException('Unable to create the database storage directory.');
        }

        self::$connection = new PDO('sqlite:' . $databasePath);
        self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$connection->exec('PRAGMA foreign_keys = ON');
        self::migrate(self::$connection);

        return self::$connection;
    }

    private static function migrate(PDO $connection): void
    {
        $schemaPath = dirname(__DIR__) . '/database/schema.sql';
        $schema = file_get_contents($schemaPath);

        if ($schema === false) {
            throw new RuntimeException('Database schema could not be read.');
        }

        $connection->exec($schema);
        self::ensureQuoteColumns($connection);
        self::seedServices($connection);
    }

    private static function ensureQuoteColumns(PDO $connection): void
    {
        $existing = $connection->query('PRAGMA table_info(quotations)')->fetchAll(PDO::FETCH_COLUMN, 1);
        $columns = [
            'pipe_type' => 'TEXT',
            'pipe_diameter_inches' => 'REAL NOT NULL DEFAULT 0',
            'pipe_length_metres' => 'REAL NOT NULL DEFAULT 0',
            'outlets' => 'INTEGER NOT NULL DEFAULT 0',
            'pipe_rate_per_metre' => 'REAL NOT NULL DEFAULT 0',
            'outlet_rate' => 'REAL NOT NULL DEFAULT 0',
            'plumbing_fee' => 'INTEGER NOT NULL DEFAULT 0',
        ];

        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                $connection->exec("ALTER TABLE quotations ADD COLUMN {$name} {$definition}");
            }
        }
    }

    private static function seedServices(PDO $connection): void
    {
        $services = [
            ['symmetric', 'Symmetric drilling', 'Drilling', 130000, 'fixed'],
            ['core', 'Core drilling', 'Drilling', 225000, 'fixed'],
            ['geo-technical', 'Geo-technical drilling', 'Drilling', 335000, 'fixed'],
            ['submersible', 'Submersible electric pump', 'Pumps', 90000, 'fixed'],
            ['solar', 'Solar pump', 'Pumps', 65000, 'fixed'],
            ['hand', 'Hand pump', 'Pumps', 30000, 'fixed'],
            ['tank', 'Tank installation', 'Storage', null, 'assessment'],
            ['plumbing', 'Plumbing service', 'Support', null, 'assessment'],
        ];

        $statement = $connection->prepare(
            'INSERT OR IGNORE INTO services
             (code, name, service_group, description, base_price, pricing_model)
             VALUES (:code, :name, :group, :description, :price, :model)'
        );

        foreach ($services as [$code, $name, $group, $price, $model]) {
            $statement->execute([
                ':code' => $code,
                ':name' => $name,
                ':group' => $group,
                ':description' => $name . ' provided by AquaFlow Water Solutions.',
                ':price' => $price,
                ':model' => $model,
            ]);
        }
    }
}
