<?php

namespace DietitianAssist\Middleware;

use PDO;

class MiddlewareHandler {
    private $db;
    private $middleware = [];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function addMiddleware($middleware) {
        $this->middleware[] = $middleware;
    }

    public function handle() {
        foreach ($this->middleware as $middleware) {
            if (is_string($middleware)) {
                $middleware = new $middleware($this->db);
            }
            $middleware->handle();
        }
    }
} 