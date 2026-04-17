<?php
/**
 * Schema update v9
 * - Provider onboarding hardening (admin approval + locked categories + residency document)
 */

require_once __DIR__ . '/../config/database.php';

function tableExistsV9(mysqli $conn, string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function columnExistsV9(mysqli $conn, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !tableExistsV9($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function addColumnIfMissingV9(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!tableExistsV9($conn, $table)) {
        echo "Table {$table} does not exist; skipped {$column}\n";
        return;
    }
    if (columnExistsV9($conn, $table, $column)) {
        echo "{$table}.{$column} already exists\n";
        return;
    }

    if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}")) {
        echo "Added {$table}.{$column}\n";
    } else {
        echo "Failed adding {$table}.{$column}: {$conn->error}\n";
    }
}

if (!tableExistsV9($conn, 'providers')) {
    echo "providers table not found.\n";
    exit;
}

addColumnIfMissingV9($conn, 'providers', 'residency_document_path', "VARCHAR(255) NULL");
addColumnIfMissingV9($conn, 'providers', 'categories_locked', "TINYINT(1) NOT NULL DEFAULT 0");
addColumnIfMissingV9($conn, 'providers', 'approved_at', "DATETIME NULL");
addColumnIfMissingV9($conn, 'providers', 'approved_by', "INT NULL");

echo "Schema update v9 completed.\n";
