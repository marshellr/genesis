<?php

declare(strict_types=1);

final class InventoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, sku, quantity, location, notes, created_at, updated_at
             FROM inventory_items
             ORDER BY updated_at DESC, id DESC'
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, sku, quantity, location, notes, created_at, updated_at
             FROM inventory_items
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        $item = $statement->fetch();

        return $item ?: null;
    }

    public function create(array $data): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO inventory_items (name, sku, quantity, location, notes)
             VALUES (:name, :sku, :quantity, :location, :notes)'
        );

        $statement->execute([
            'name' => $data['name'],
            'sku' => $data['sku'],
            'quantity' => $data['quantity'],
            'location' => $data['location'],
            'notes' => $data['notes'],
        ]);
    }

    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE inventory_items
             SET name = :name,
                 sku = :sku,
                 quantity = :quantity,
                 location = :location,
                 notes = :notes
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'name' => $data['name'],
            'sku' => $data['sku'],
            'quantity' => $data['quantity'],
            'location' => $data['location'],
            'notes' => $data['notes'],
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM inventory_items WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function ping(): void
    {
        $this->pdo->query('SELECT 1');
    }
}
