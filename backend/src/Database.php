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

        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException(
                'The pdo_sqlite PHP extension is not enabled. Start the server with '
                . '`serve.cmd` / `serve.sh`, or run `php -d extension=pdo_sqlite -S localhost:8000`.'
            );
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

    private const SCHEMA_VERSION = 2;

    private static function migrate(PDO $connection): void
    {
        if ((int) $connection->query('PRAGMA user_version')->fetchColumn() >= self::SCHEMA_VERSION) {
            return;
        }

        $schemaPath = dirname(__DIR__) . '/database/schema.sql';
        $schema = file_get_contents($schemaPath);

        if ($schema === false) {
            throw new RuntimeException('Database schema could not be read.');
        }

        $connection->beginTransaction();
        try {
            $connection->exec($schema);
            self::ensureQuoteColumns($connection);
            self::seedServices($connection);
            self::seedDemoRecords($connection);
            $connection->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
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

    private static function seedDemoRecords(PDO $connection): void
    {
        if ((int) $connection->query('SELECT COUNT(*) FROM clients')->fetchColumn() > 0) {
            return;
        }

        $clients = [
            ['Joy Muthoni', 'Domestic', '0712 845 230', 'joy.muthoni@example.com', 'Kangundo, Machakos'],
            ['Kijani Schools', 'Commercial', '020 273 8201', 'bursar@kijani.example.com', 'Ruiru, Kiambu'],
            ['Green Oaks Farm', 'Commercial', '0722 340 185', 'manager@greenoaks.example.com', 'Ngong, Kajiado'],
            ['Mercy Wanjiru', 'Domestic', '0703 774 521', null, 'Kitengela, Kajiado'],
            ['Rift Valley Bottlers', 'Industrial', '0733 410 900', 'ops@rvbottlers.example.com', 'Industrial Area, Nakuru'],
            ['Amina Otieno', 'Domestic', '0720 118 442', null, 'Athi River, Machakos'],
        ];
        $insertClient = $connection->prepare(
            'INSERT INTO clients (name, category, telephone, email, address, created_at)
             VALUES (:name, :category, :telephone, :email, :address, :created_at)'
        );
        $clientIds = [];
        foreach ($clients as $index => [$name, $category, $telephone, $email, $address]) {
            $insertClient->execute([
                ':name' => $name, ':category' => $category, ':telephone' => $telephone,
                ':email' => $email, ':address' => $address,
                ':created_at' => self::monthsAgo(5 - $index),
            ]);
            $clientIds[] = (int) $connection->lastInsertId();
        }

        $applications = [
            [0, 'symmetric', 'Kangundo, Machakos', 'Review', 0],
            [1, 'solar', 'Ruiru, Kiambu', 'Survey booked', 0],
            [2, 'tank', 'Ngong, Kajiado', 'Approved', 1],
            [3, 'plumbing', 'Kitengela, Kajiado', 'Draft', 1],
            [4, 'geo-technical', 'Industrial Area, Nakuru', 'Approved', 2],
            [5, 'submersible', 'Athi River, Machakos', 'Completed', 4],
            [1, 'core', 'Ruiru, Kiambu', 'Review', 0],
        ];
        $insertApplication = $connection->prepare(
            'INSERT INTO applications (reference, client_id, service_id, location, status, created_at)
             VALUES (:reference, :client_id, :service_id, :location, :status, :created_at)'
        );
        $serviceIds = [];
        foreach ($connection->query('SELECT id, code FROM services') as $row) {
            $serviceIds[$row['code']] = (int) $row['id'];
        }
        foreach ($applications as $index => [$clientIndex, $code, $location, $status, $monthsBack]) {
            $insertApplication->execute([
                ':reference' => 'AF-' . str_pad((string) (1028 + $index), 4, '0', STR_PAD_LEFT),
                ':client_id' => $clientIds[$clientIndex],
                ':service_id' => $serviceIds[$code] ?? null,
                ':location' => $location,
                ':status' => $status,
                ':created_at' => self::monthsAgo($monthsBack, $index),
            ]);
        }

        $quotes = [
            [0, 'Domestic', 'Symmetric', 'Submersible', 90, 5, 'Accepted', 5],
            [1, 'Commercial', 'Core', 'Solar', 150, 8, 'Accepted', 4],
            [2, 'Commercial', 'Symmetric', 'Submersible', 120, 10, 'Accepted', 3],
            [4, 'Industrial', 'Geotechnical', 'Submersible', 260, 12, 'Accepted', 2],
            [3, 'Domestic', 'None', 'Hand', 60, 4, 'Sent', 1],
            [5, 'Domestic', 'Symmetric', 'Solar', 110, 6, 'Sent', 0],
            [1, 'Commercial', 'Core', 'Solar', 180, 9, 'Draft', 0],
            [2, 'Commercial', 'Symmetric', 'None', 80, 6, 'Expired', 4],
        ];
        $insertQuote = $connection->prepare(
            'INSERT INTO quotations
             (reference, client_id, category, drilling_type, pump_type, depth_metres, tank_height_metres,
              survey_fee, authority_fee, drilling_fee, pump_fee, depth_fee, pipe_type, pipe_diameter_inches,
              pipe_length_metres, outlets, pipe_rate_per_metre, outlet_rate, plumbing_fee,
              subtotal, tax, total, status, created_at)
             VALUES
             (:reference, :client_id, :category, :drilling_type, :pump_type, :depth, :height,
              :survey_fee, :authority_fee, :drilling_fee, :pump_fee, :depth_fee, :pipe_type, :pipe_diameter,
              :pipe_length, :outlets, :pipe_rate, :outlet_rate, :plumbing_fee,
              :subtotal, :tax, :total, :status, :created_at)'
        );
        foreach ($quotes as $index => [$clientIndex, $category, $drilling, $pump, $depth, $height, $status, $monthsBack]) {
            $calculation = QuoteCalculator::calculate([
                'category' => $category, 'drilling_type' => $drilling, 'pump_type' => $pump,
                'depth_metres' => $depth, 'tank_height_metres' => $height,
                'pipe_type' => $index % 3 === 0 ? 'PPR' : '',
                'pipe_diameter_inches' => $index % 3 === 0 ? 1 : 0,
                'pipe_length_metres' => $index % 3 === 0 ? 40 : 0,
                'pipe_rate_per_metre' => $index % 3 === 0 ? 350 : 0,
                'outlets' => $index % 3 === 0 ? 6 : 0,
                'outlet_rate' => $index % 3 === 0 ? 1500 : 0,
            ]);
            $insertQuote->execute([
                ':reference' => 'AQ-' . date('Y', strtotime(self::monthsAgo($monthsBack, $index))) . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                ':client_id' => $clientIds[$clientIndex],
                ':category' => $calculation['category'],
                ':drilling_type' => $calculation['drilling_type'],
                ':pump_type' => $calculation['pump_type'],
                ':depth' => $calculation['depth_metres'],
                ':height' => $calculation['tank_height_metres'],
                ':survey_fee' => $calculation['survey_fee'],
                ':authority_fee' => $calculation['authority_fee'],
                ':drilling_fee' => $calculation['drilling_fee'],
                ':pump_fee' => $calculation['pump_fee'],
                ':depth_fee' => $calculation['depth_fee'],
                ':pipe_type' => $calculation['pipe_type'] ?: null,
                ':pipe_diameter' => $calculation['pipe_diameter_inches'],
                ':pipe_length' => $calculation['pipe_length_metres'],
                ':outlets' => $calculation['outlets'],
                ':pipe_rate' => $calculation['pipe_rate_per_metre'],
                ':outlet_rate' => $calculation['outlet_rate'],
                ':plumbing_fee' => $calculation['plumbing_fee'],
                ':subtotal' => $calculation['subtotal'],
                ':tax' => $calculation['tax'],
                ':total' => $calculation['total'],
                ':status' => $status,
                ':created_at' => self::monthsAgo($monthsBack, $index),
            ]);
        }
    }

    private static function monthsAgo(int $months, int $dayOffset = 0): string
    {
        $timestamp = strtotime('-' . $months . ' months', strtotime('first day of this month 09:00'));
        $timestamp = strtotime('+' . (6 + $dayOffset * 3) . ' days', $timestamp);

        return date('Y-m-d H:i:s', min($timestamp, time() - 3600 * ($dayOffset + 1)));
    }
}
