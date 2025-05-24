<?php

// Initialize middleware handler
$middlewareHandler = new \DietitianAssist\Middleware\MiddlewareHandler($db);

// Add audit middleware
$middlewareHandler->addMiddleware(\DietitianAssist\Middleware\AuditMiddleware::class);

// Handle middleware
$middlewareHandler->handle(); 