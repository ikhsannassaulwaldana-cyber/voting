<?php
require 'config.php';
foreach ($pdo->query('SHOW TABLES') as $row) {
    $table = array_values($row)[0];
    echo "TABLE $table\n";
    foreach ($pdo->query('DESCRIBE `' . str_replace('`', '``', $table) . '`') as $column) {
        echo $column['Field'] . ' ' . $column['Type'] . "\n";
    }
}
