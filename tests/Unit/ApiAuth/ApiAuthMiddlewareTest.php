<?php

namespace Tests\Unit\ApiAuth;

use PHPUnit\Framework\TestCase;
use DietitianAssist\ApiAuth\ApiAuthMiddleware;
use DietitianAssist\ApiAuth\ApiAuthController;
use PDO;

class ApiAuthMiddlewareTest extends TestCase
{
    private $pdo;
    private $controller;
    private $middleware;

    protected function setUp(): void
    {
        // Create a mock PDO instance
        $this->pdo = $this->createMock(PDO::class);
        $this->controller = $this->createMock(ApiAuthController::class);
        $this->middleware = new ApiAuthMiddleware($this->controller);
    }

    public function testHandleRequestWithValidToken()
    {
        // Mock the request headers
        $headers = [
            'HTTP_X_API_KEY' => 'valid-token'
        ];

        // Configure the controller mock
        $this->controller->expects($this->once())
            ->method('validateApiToken')
            ->with('valid-token')
            ->willReturn(true);

        // Test the middleware
        $result = $this->middleware->handleRequest($headers);
        $this->assertTrue($result);
    }

    public function testHandleRequestWithMissingToken()
    {
        // Mock the request headers without API key
        $headers = [];

        // Test the middleware
        $result = $this->middleware->handleRequest($headers);
        $this->assertFalse($result);
    }

    public function testHandleRequestWithInvalidToken()
    {
        // Mock the request headers
        $headers = [
            'HTTP_X_API_KEY' => 'invalid-token'
        ];

        // Configure the controller mock
        $this->controller->expects($this->once())
            ->method('validateApiToken')
            ->with('invalid-token')
            ->willReturn(false);

        // Test the middleware
        $result = $this->middleware->handleRequest($headers);
        $this->assertFalse($result);
    }

    public function testHandleWithValidToken()
    {
        $server = [
            'HTTP_X_API_KEY' => 'valid-token'
        ];

        // Configure the controller mock
        $this->controller->expects($this->once())
            ->method('validateApiToken')
            ->with('valid-token')
            ->willReturn(true);

        // Test the handle method
        $result = $this->middleware->handle($server);
        $this->assertTrue($result);
    }

    public function testHandleWithMissingToken()
    {
        $server = [];

        // Start output buffering
        ob_start();
        $result = $this->middleware->handle($server);
        $output = ob_get_clean();

        $this->assertFalse($result);
        
        // Verify response
        $response = json_decode($output, true);
        $this->assertEquals(401, $response['status']);
        $this->assertEquals('API token is required', $response['error']);
    }

    public function testHandleWithInvalidToken()
    {
        $server = [
            'HTTP_X_API_KEY' => 'invalid-token'
        ];

        // Configure the controller mock
        $this->controller->expects($this->once())
            ->method('validateApiToken')
            ->with('invalid-token')
            ->willReturn(false);

        // Start output buffering
        ob_start();
        $result = $this->middleware->handle($server);
        $output = ob_get_clean();

        $this->assertFalse($result);
        
        // Verify response
        $response = json_decode($output, true);
        $this->assertEquals(401, $response['status']);
        $this->assertEquals('Invalid or expired API token', $response['error']);
    }

    public function testHandleWithValidationError()
    {
        $server = [
            'HTTP_X_API_KEY' => 'error-token'
        ];

        // Configure the controller mock
        $this->controller->expects($this->once())
            ->method('validateApiToken')
            ->with('error-token')
            ->willThrowException(new \Exception('Validation error'));

        // Start output buffering
        ob_start();
        $result = $this->middleware->handle($server);
        $output = ob_get_clean();

        $this->assertFalse($result);
        
        // Verify response
        $response = json_decode($output, true);
        $this->assertEquals(401, $response['status']);
        $this->assertEquals('Error validating API token', $response['error']);
    }
}

// Mock the getallheaders function in the Tests\Unit\ApiAuth namespace
namespace Tests\Unit\ApiAuth;

function getallheaders()
{
    global $test;
    return $test->headers;
} 