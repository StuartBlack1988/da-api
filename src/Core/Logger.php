<?php

namespace DietitianAssist\Core;

class Logger {
    private $logFile;

    public function __construct($logFile = null) {
        $this->logFile = $logFile ?? dirname(__DIR__, 2) . '/logs/app.log';
        $this->ensureLogDirectoryExists();
    }

    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }

    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }

    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }

    public function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }

    private function log($level, $message, $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];

        $logLine = json_encode($logEntry) . PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND);
    }

    private function ensureLogDirectoryExists() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }
} 