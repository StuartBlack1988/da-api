<?php

namespace Tests\Unit\ApiAuth;

use PHPUnit\Framework\TestCase;
use DietitianAssist\ApiAuth\ApiAuthController;
use PDO;
use PDOStatement;
use PDOException;

class ApiAuthControllerTest extends TestCase
{
    private $pdo;
    private $controller;

    protected function setUp(): void
    {
        // Create a mock PDO instance
        $this->pdo = $this->createMock(PDO::class);
        $this->controller = new ApiAuthController($this->pdo);
    }

    public function testValidateApiTokenWithValidToken()
    {
        // Create mock statements
        $selectStmt = $this->createMock(PDOStatement::class);
        $updateStmt = $this->createMock(PDOStatement::class);
        
        // Configure the select statement mock
        $selectStmt->expects($this->once())
            ->method('execute')
            ->with(['valid-token'])
            ->willReturn(true);
            
        $selectStmt->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'apiAuthId' => 1,
                'token' => 'valid-token',
                'isActive' => 1,
                'lastUsed' => '2024-01-01 00:00:00'
            ]);

        // Configure the update statement mock
        $updateStmt->expects($this->once())
            ->method('execute')
            ->with([1])
            ->willReturn(true);

        // Configure the PDO mock
        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function($sql) use ($selectStmt, $updateStmt) {
                if (strpos($sql, 'SELECT') !== false) {
                    return $selectStmt;
                }
                if (strpos($sql, 'UPDATE') !== false) {
                    return $updateStmt;
                }
                return false;
            });

        // Test the validation
        $result = $this->controller->validateApiToken('valid-token');
        $this->assertTrue($result);
    }

    public function testValidateApiTokenWithInvalidToken()
    {
        // Create a mock statement
        $stmt = $this->createMock(PDOStatement::class);
        
        // Configure the statement mock
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['invalid-token'])
            ->willReturn(true);
            
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        // Configure the PDO mock
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with("
                SELECT * 
                FROM ApiAuth 
                WHERE token = ? 
                AND isActive = TRUE 
                AND (expiryDate IS NULL OR expiryDate > CURRENT_TIMESTAMP)
            ")
            ->willReturn($stmt);

        // Test the validation
        $result = $this->controller->validateApiToken('invalid-token');
        $this->assertFalse($result);
    }

    public function testValidateApiTokenWithInactiveToken()
    {
        // Create mock statements
        $selectStmt = $this->createMock(PDOStatement::class);
        
        // Configure the select statement mock
        $selectStmt->expects($this->once())
            ->method('execute')
            ->with(['inactive-token'])
            ->willReturn(true);
            
        $selectStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        // Configure the PDO mock
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with("
                SELECT * 
                FROM ApiAuth 
                WHERE token = ? 
                AND isActive = TRUE 
                AND (expiryDate IS NULL OR expiryDate > CURRENT_TIMESTAMP)
            ")
            ->willReturn($selectStmt);

        // Test the validation
        $result = $this->controller->validateApiToken('inactive-token');
        $this->assertFalse($result);
    }

    public function testCreateApiTokenSuccess()
    {
        // Create a mock statement
        $stmt = $this->createMock(PDOStatement::class);
        
        // Configure the statement mock
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($params) {
                return $params[0] === 'Test Token' &&
                       $params[1] !== null && // token should be generated
                       $params[2] === 'Test Description' &&
                       $params[3] === '2024-12-31' &&
                       $params[4] === 1;
            }))
            ->willReturn(true);

        // Configure the PDO mock
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO ApiAuth'))
            ->willReturn($stmt);

        // Test token creation
        $result = $this->controller->createApiToken(
            'Test Token',
            'Test Description',
            '2024-12-31',
            1
        );

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('API token created successfully', $result['message']);
        $this->assertNotEmpty($result['token']);
    }

    public function testCreateApiTokenFailure()
    {
        // Configure the PDO mock to throw an exception
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Database error'));

        // Test token creation
        $result = $this->controller->createApiToken('Test Token');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Failed to create API token', $result['message']);
    }

    public function testDeactivateApiTokenSuccess()
    {
        // Create a mock statement
        $stmt = $this->createMock(PDOStatement::class);
        
        // Configure the statement mock
        $stmt->expects($this->once())
            ->method('execute')
            ->with([1])
            ->willReturn(true);

        // Configure the PDO mock
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE ApiAuth'))
            ->willReturn($stmt);

        // Test token deactivation
        $result = $this->controller->deactivateApiToken(1);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('API token deactivated successfully', $result['message']);
    }

    public function testDeactivateApiTokenFailure()
    {
        // Configure the PDO mock to throw an exception
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Database error'));

        // Test token deactivation
        $result = $this->controller->deactivateApiToken(1);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Failed to deactivate API token', $result['message']);
    }

    public function testListApiTokensWithoutUserId()
    {
        // Create a mock statement
        $stmt = $this->createMock(PDOStatement::class);
        
        // Configure the statement mock
        $stmt->expects($this->once())
            ->method('execute')
            ->with([])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['apiAuthId' => 1, 'token' => 'token1'],
                ['apiAuthId' => 2, 'token' => 'token2']
            ]);

        // Configure the PDO mock
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalAnd(
                $this->stringContains('SELECT'),
                $this->stringContains('FROM ApiAuth'),
                $this->stringContains('LEFT JOIN User'),
                $this->stringContains('ORDER BY')
            ))
            ->willReturn($stmt);

        // Test listing tokens
        $result = $this->controller->listApiTokens();

        $this->assertEquals('success', $result['status']);
        $this->assertCount(2, $result['tokens']);
    }

    public function testListApiTokensWithUserId()
    {
        // Create a mock statement
        $stmt = $this->createMock(PDOStatement::class);
        
        // Configure the statement mock
        $stmt->expects($this->once())
            ->method('execute')
            ->with([1])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['apiAuthId' => 1, 'token' => 'token1']
            ]);

        // Configure the PDO mock
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalAnd(
                $this->stringContains('SELECT'),
                $this->stringContains('FROM ApiAuth'),
                $this->stringContains('LEFT JOIN User'),
                $this->stringContains('WHERE a.createdBy = ?'),
                $this->stringContains('ORDER BY')
            ))
            ->willReturn($stmt);

        // Test listing tokens for specific user
        $result = $this->controller->listApiTokens(1);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['tokens']);
    }

    public function testListApiTokensFailure()
    {
        // Configure the PDO mock to throw an exception
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Database error'));

        // Test listing tokens
        $result = $this->controller->listApiTokens();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Failed to list API tokens', $result['message']);
    }
} 