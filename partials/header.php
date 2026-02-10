<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/security.php';

$pageTitle = $pageTitle ?? 'Supplier & Purchase Order Manager';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .card-stat { border-left: 4px solid #0d6efd; }
        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">PO Manager</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="suppliers.php">Suppliers</a></li>
                <li class="nav-item"><a class="nav-link" href="purchase_orders.php">Purchase Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="receive.php">Receive Goods</a></li>
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4">
