<?php

declare(strict_types=1);

namespace AquaFlow;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class Repository
{
    public function __construct(private PDO $connection)
    {
    }

    public function clients(string $search = ''): array
    {
        $statement = $this->connection->prepare(
            'SELECT clients.*, COUNT(applications.id) AS application_count
             FROM clients
             LEFT JOIN applications ON applications.client_id = clients.id
             WHERE clients.name LIKE :search
                OR clients.telephone LIKE :search
                OR clients.address LIKE :search
             GROUP BY clients.id
             ORDER BY clients.created_at DESC'
        );
        $statement->execute([':search' => '%' . trim($search) . '%']);

        return $statement->fetchAll();
    }

    public function client(int $id): array
    {
        $statement = $this->connection->prepare('SELECT * FROM clients WHERE id = :id');
        $statement->execute([':id' => $id]);
        $client = $statement->fetch();

        if ($client === false) {
            throw new RuntimeException('Client not found.');
        }

        return $client;
    }

    public function createClient(array $input): array
    {
        $name = $this->required($input, 'name');
        $telephone = $this->required($input, 'telephone');
        $address = $this->required($input, 'address');
        $category = $this->category($input['category'] ?? '');
        $email = trim((string) ($input['email'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email address is invalid.');
        }

        $statement = $this->connection->prepare(
            'INSERT INTO clients (name, category, telephone, email, address)
             VALUES (:name, :category, :telephone, :email, :address)'
        );
        $statement->execute([
            ':name' => $name,
            ':category' => $category,
            ':telephone' => $telephone,
            ':email' => $email === '' ? null : $email,
            ':address' => $address,
        ]);

        return $this->client((int) $this->connection->lastInsertId());
    }

    public function updateClient(int $id, array $input): array
    {
        $this->client($id);
        $name = $this->required($input, 'name');
        $telephone = $this->required($input, 'telephone');
        $address = $this->required($input, 'address');
        $category = $this->category($input['category'] ?? '');
        $email = trim((string) ($input['email'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email address is invalid.');
        }

        $statement = $this->connection->prepare(
            'UPDATE clients SET name = :name, category = :category, telephone = :telephone,
             email = :email, address = :address, updated_at = datetime("now") WHERE id = :id'
        );
        $statement->execute([
            ':id' => $id, ':name' => $name, ':category' => $category,
            ':telephone' => $telephone, ':email' => $email === '' ? null : $email,
            ':address' => $address,
        ]);

        return $this->client($id);
    }

    public function deleteClient(int $id): void
    {
        $applications = $this->connection->prepare('SELECT COUNT(*) FROM applications WHERE client_id = :id');
        $applications->execute([':id' => $id]);
        if ((int) $applications->fetchColumn() > 0) {
            throw new InvalidArgumentException('Clients with applications cannot be deleted. Keep the record for history.');
        }
        $statement = $this->connection->prepare('DELETE FROM clients WHERE id = :id');
        $statement->execute([':id' => $id]);
        if ($statement->rowCount() === 0) {
            throw new RuntimeException('Client not found.');
        }
    }

    public function services(): array
    {
        $statement = $this->connection->query(
            'SELECT * FROM services WHERE active = 1 ORDER BY service_group, name'
        );

        return $statement->fetchAll();
    }

    public function createService(array $input): array
    {
        $code = strtolower(preg_replace('/[^a-z0-9]+/', '-', $this->required($input, 'code')) ?? '');
        $name = $this->required($input, 'name');
        $group = $this->required($input, 'service_group');
        $model = $this->pricingModel($input['pricing_model'] ?? 'fixed');
        $price = $this->optionalNumber($input, 'base_price');
        if ($model === 'fixed' && $price === null) {
            throw new InvalidArgumentException('A base price is required for fixed-price services.');
        }
        $statement = $this->connection->prepare(
            'INSERT INTO services (code, name, service_group, description, base_price, pricing_model)
             VALUES (:code, :name, :group, :description, :price, :model)'
        );
        $statement->execute([
            ':code' => $code, ':name' => $name, ':group' => $group,
            ':description' => $this->optional($input, 'description') ?? $name,
            ':price' => $price, ':model' => $model,
        ]);
        return $this->service((int) $this->connection->lastInsertId());
    }

    public function updateService(int $id, array $input): array
    {
        $this->service($id);
        $name = $this->required($input, 'name');
        $group = $this->required($input, 'service_group');
        $model = $this->pricingModel($input['pricing_model'] ?? 'fixed');
        $price = $this->optionalNumber($input, 'base_price');
        $statement = $this->connection->prepare(
            'UPDATE services SET name = :name, service_group = :group, description = :description,
             base_price = :price, pricing_model = :model, active = :active WHERE id = :id'
        );
        $statement->execute([
            ':id' => $id, ':name' => $name, ':group' => $group,
            ':description' => $this->optional($input, 'description') ?? $name,
            ':price' => $price, ':model' => $model,
            ':active' => !empty($input['active']) ? 1 : 0,
        ]);
        return $this->service($id);
    }

    public function applications(string $search = '', string $status = ''): array
    {
        $query = 'SELECT applications.*, clients.name AS client_name, clients.category,
                         services.name AS service_name
                  FROM applications
                  JOIN clients ON clients.id = applications.client_id
                  LEFT JOIN services ON services.id = applications.service_id
                  WHERE (clients.name LIKE :search
                     OR applications.reference LIKE :search
                     OR applications.location LIKE :search)';
        $parameters = [':search' => '%' . trim($search) . '%'];

        if ($status !== '') {
            $query .= ' AND applications.status = :status';
            $parameters[':status'] = $this->status($status);
        }

        $query .= ' ORDER BY applications.created_at DESC';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function createApplication(array $input): array
    {
        $clientId = $this->existingClientId($input['client_id'] ?? null);
        $serviceId = $this->serviceId($input['service_code'] ?? null);
        $status = isset($input['status']) ? $this->status((string) $input['status']) : 'Review';
        $reference = $this->nextApplicationReference();
        $statement = $this->connection->prepare(
            'INSERT INTO applications
             (reference, client_id, service_id, drilling_type, pump_type, location,
              depth_metres, tank_height_metres, notes, status)
             VALUES
             (:reference, :client_id, :service_id, :drilling_type, :pump_type, :location,
              :depth_metres, :tank_height_metres, :notes, :status)'
        );
        $statement->execute([
            ':reference' => $reference,
            ':client_id' => $clientId,
            ':service_id' => $serviceId,
            ':drilling_type' => $this->optional($input, 'drilling_type'),
            ':pump_type' => $this->optional($input, 'pump_type'),
            ':location' => $this->required($input, 'location'),
            ':depth_metres' => $this->optionalNumber($input, 'depth_metres'),
            ':tank_height_metres' => $this->optionalNumber($input, 'tank_height_metres'),
            ':notes' => $this->optional($input, 'notes'),
            ':status' => $status,
        ]);

        return $this->application((int) $this->connection->lastInsertId());
    }

    public function updateApplication(int $id, array $input): array
    {
        $current = $this->application($id);
        $status = isset($input['status']) ? $this->status((string) $input['status']) : $current['status'];
        $statement = $this->connection->prepare(
            'UPDATE applications SET status = :status, notes = :notes, updated_at = datetime("now") WHERE id = :id'
        );
        $statement->execute([
            ':id' => $id, ':status' => $status,
            ':notes' => array_key_exists('notes', $input) ? $this->optional($input, 'notes') : $current['notes'],
        ]);
        return $this->application($id);
    }

    public function saveQuote(array $input): array
    {
        $calculation = QuoteCalculator::calculate($input);
        $clientId = $this->optionalExistingId($input['client_id'] ?? null, 'clients');
        $applicationId = $this->optionalExistingId($input['application_id'] ?? null, 'applications');
        $reference = $this->nextQuoteReference();
        $statement = $this->connection->prepare(
            'INSERT INTO quotations
             (reference, client_id, application_id, category, drilling_type, pump_type,
             depth_metres, tank_height_metres, survey_fee, authority_fee, drilling_fee,
              pump_fee, depth_fee, pipe_type, pipe_diameter_inches, pipe_length_metres,
              outlets, pipe_rate_per_metre, outlet_rate, plumbing_fee, subtotal, tax, total, valid_until, notes)
             VALUES
             (:reference, :client_id, :application_id, :category, :drilling_type, :pump_type,
              :depth, :height, :survey_fee, :authority_fee, :drilling_fee, :pump_fee,
              :depth_fee, :pipe_type, :pipe_diameter, :pipe_length, :outlets, :pipe_rate,
              :outlet_rate, :plumbing_fee, :subtotal, :tax, :total, :valid_until, :notes)'
        );
        $statement->execute([
            ':reference' => $reference,
            ':client_id' => $clientId,
            ':application_id' => $applicationId,
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
            ':valid_until' => $this->optional($input, 'valid_until'),
            ':notes' => $this->optional($input, 'notes'),
        ]);

        return $this->quote((int) $this->connection->lastInsertId());
    }

    public function overview(): array
    {
        $counts = $this->connection->query(
            'SELECT
                (SELECT COUNT(*) FROM clients) AS clients,
                (SELECT COUNT(*) FROM applications
                 WHERE status NOT IN ("Completed")) AS active_projects,
                (SELECT COUNT(*) FROM applications
                 WHERE status = "Review") AS pending_applications,
                (SELECT COUNT(*) FROM quotations
                 WHERE status IN ("Draft", "Sent")) AS pending_quotes,
                (SELECT COALESCE(SUM(total), 0) FROM quotations
                 WHERE status = "Accepted") AS collected_revenue'
        )->fetch();
        $fees = $this->connection->query(
            'SELECT COALESCE(SUM(survey_fee), 0) AS survey_fees,
                    COALESCE(SUM(authority_fee), 0) AS authority_fees,
                    COALESCE(SUM(tax), 0) AS tax,
                    COALESCE(SUM(plumbing_fee), 0) AS plumbing_total,
                    COALESCE(SUM(total), 0) AS quoted_total
             FROM quotations'
        )->fetch();
        $services = $this->connection->query(
            'SELECT service, SUM(amount) AS revenue FROM (
                SELECT "Drilling" AS service, drilling_fee AS amount FROM quotations
                UNION ALL SELECT "Pumps", pump_fee FROM quotations
                UNION ALL SELECT "Depth and height", depth_fee FROM quotations
                UNION ALL SELECT "Plumbing", plumbing_fee FROM quotations
             ) GROUP BY service ORDER BY revenue DESC'
        )->fetchAll();

        $clientTotals = $this->connection->query(
            'SELECT clients.name, clients.category, COALESCE(SUM(quotations.total), 0) AS total
             FROM clients LEFT JOIN quotations ON quotations.client_id = clients.id
             GROUP BY clients.id ORDER BY total DESC, clients.name ASC LIMIT 10'
        )->fetchAll();

        return ['summary' => $counts, 'fees' => $fees, 'services' => $services, 'client_totals' => $clientTotals];
    }

    private function application(int $id): array
    {
        $rows = $this->applications();

        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }

        throw new RuntimeException('Application could not be read after creation.');
    }

    private function quote(int $id): array
    {
        $statement = $this->connection->prepare('SELECT * FROM quotations WHERE id = :id');
        $statement->execute([':id' => $id]);
        $quote = $statement->fetch();

        if ($quote === false) {
            throw new RuntimeException('Quotation could not be read after creation.');
        }

        return $quote;
    }

    private function service(int $id): array
    {
        $statement = $this->connection->prepare('SELECT * FROM services WHERE id = :id');
        $statement->execute([':id' => $id]);
        $service = $statement->fetch();
        if ($service === false) {
            throw new RuntimeException('Service not found.');
        }
        return $service;
    }

    private function existingClientId(mixed $id): int
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new InvalidArgumentException('A valid client_id is required.');
        }

        return (int) $this->client((int) $id)['id'];
    }

    private function optionalExistingId(mixed $id, string $table): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (!filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new InvalidArgumentException('Identifier must be a positive integer.');
        }

        $statement = $this->connection->prepare('SELECT id FROM ' . $table . ' WHERE id = :id');
        $statement->execute([':id' => (int) $id]);

        if ($statement->fetch() === false) {
            throw new InvalidArgumentException('Related record does not exist.');
        }

        return (int) $id;
    }

    private function serviceId(mixed $code): ?int
    {
        if ($code === null || trim((string) $code) === '') {
            return null;
        }

        $statement = $this->connection->prepare('SELECT id FROM services WHERE code = :code');
        $statement->execute([':code' => trim((string) $code)]);
        $service = $statement->fetch();

        if ($service === false) {
            throw new InvalidArgumentException('Selected service does not exist.');
        }

        return (int) $service['id'];
    }

    private function category(mixed $value): string
    {
        $category = trim((string) $value);
        $allowed = ['Domestic', 'Commercial', 'Industrial'];

        if (!in_array($category, $allowed, true)) {
            throw new InvalidArgumentException('Client category is invalid.');
        }

        return $category;
    }

    private function status(string $value): string
    {
        $allowed = ['Draft', 'Review', 'Survey booked', 'Approved', 'Completed'];

        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('Application status is invalid.');
        }

        return $value;
    }

    private function pricingModel(mixed $value): string
    {
        $model = trim((string) $value);
        if (!in_array($model, ['fixed', 'assessment'], true)) {
            throw new InvalidArgumentException('Pricing model is invalid.');
        }
        return $model;
    }

    private function required(array $input, string $key): string
    {
        $value = trim((string) ($input[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException(str_replace('_', ' ', $key) . ' is required.');
        }

        return $value;
    }

    private function optional(array $input, string $key): ?string
    {
        $value = trim((string) ($input[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    private function optionalNumber(array $input, string $key): ?float
    {
        if (!array_key_exists($key, $input) || $input[$key] === '') {
            return null;
        }

        if (!is_numeric($input[$key]) || (float) $input[$key] < 0) {
            throw new InvalidArgumentException($key . ' must be a positive number.');
        }

        return (float) $input[$key];
    }

    private function nextApplicationReference(): string
    {
        $number = (int) $this->connection->query('SELECT COUNT(*) FROM applications')->fetchColumn();

        return 'AF-' . str_pad((string) ($number + 1028), 4, '0', STR_PAD_LEFT);
    }

    private function nextQuoteReference(): string
    {
        $number = (int) $this->connection->query('SELECT COUNT(*) FROM quotations')->fetchColumn();

        return 'AQ-' . date('Y') . '-' . str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT);
    }
}
