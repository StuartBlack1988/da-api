<?php

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use DietitianAssist\User\UserController;
use PDO;
use PDOStatement;

class UserControllerTest extends TestCase
{
    private $db;
    private $userController;

    protected function setUp(): void
    {
        // Create a mock PDO instance
        $this->db = $this->createMock(PDO::class);
        $this->userController = new UserController($this->db);
    }

    public function testListUsersWithFilters()
    {
        // Create mock statement for count query
        $countStmt = $this->createMock(PDOStatement::class);
        $countStmt->expects($this->once())
            ->method('execute')
            ->with(['admin', 1, '%test%', '%test%', '%test%', 20, 0]);
        $countStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(1);

        // Create mock statement for main query
        $mainStmt = $this->createMock(PDOStatement::class);
        $mainStmt->expects($this->once())
            ->method('execute')
            ->with(['admin', 1, '%test%', '%test%', '%test%', 20, 0]);
        $mainStmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'userId' => 1,
                    'email' => 'test@example.com',
                    'name' => 'Test',
                    'surname' => 'User',
                    'role' => 'admin'
                ]
            ]);

        // Configure PDO mock
        $this->db->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($countStmt, $mainStmt);

        $filters = [
            'role' => 'admin',
            'isActive' => true,
            'search' => 'test'
        ];

        $result = $this->userController->listUsers($filters, 1, 20);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['data']['users']);
        $this->assertEquals(1, $result['data']['pagination']['total']);
    }

    public function testUpdateUser()
    {
        // Mock check user exists statement
        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $checkStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['userId' => 1, 'email' => 'old@example.com']);

        // Mock email check statement
        $emailStmt = $this->createMock(PDOStatement::class);
        $emailStmt->expects($this->once())
            ->method('execute')
            ->with(['new@example.com', 1]);
        $emailStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        // Mock update statement
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->expects($this->once())
            ->method('execute')
            ->with(['new@example.com', 'New', 'Name', 1]);

        // Mock get updated user statement
        $updatedStmt = $this->createMock(PDOStatement::class);
        $updatedStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $updatedStmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([
                'userId' => 1,
                'email' => 'new@example.com',
                'name' => 'New',
                'surname' => 'Name',
                'role' => 'admin'
            ]);

        // Configure PDO mock
        $this->db->expects($this->exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($checkStmt, $emailStmt, $updateStmt, $updatedStmt);

        $data = [
            'email' => 'new@example.com',
            'name' => 'New',
            'surname' => 'Name'
        ];

        $result = $this->userController->updateUser(1, $data);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('User updated successfully', $result['message']);
        $this->assertEquals('new@example.com', $result['data']['email']);
    }

    public function testDeactivateUser()
    {
        // Mock check user exists statement
        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $checkStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['userId' => 1]);

        // Mock update statement
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->expects($this->once())
            ->method('execute')
            ->with([1]);

        // Configure PDO mock
        $this->db->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($checkStmt, $updateStmt);

        $result = $this->userController->deactivateUser(1);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('User deactivated successfully', $result['message']);
        $this->assertEquals(1, $result['data']['userId']);
        $this->assertNotNull($result['data']['deactivatedAt']);
    }

    public function testReactivateUser()
    {
        // Mock check user exists statement
        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $checkStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['userId' => 1]);

        // Mock update statement
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->expects($this->once())
            ->method('execute')
            ->with([1]);

        // Configure PDO mock
        $this->db->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($checkStmt, $updateStmt);

        $result = $this->userController->reactivateUser(1);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('User reactivated successfully', $result['message']);
        $this->assertEquals(1, $result['data']['userId']);
        $this->assertNull($result['data']['deactivatedAt']);
    }

    public function testUpdateUserNotFound()
    {
        // Mock check user exists statement
        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->expects($this->once())
            ->method('execute')
            ->with([999]);
        $checkStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        // Configure PDO mock
        $this->db->expects($this->once())
            ->method('prepare')
            ->willReturn($checkStmt);

        $result = $this->userController->updateUser(999, ['email' => 'test@example.com']);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('User not found', $result['error']);
    }

    public function testUpdateUserEmailAlreadyInUse()
    {
        // Mock check user exists statement
        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $checkStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['userId' => 1, 'email' => 'old@example.com']);

        // Mock email check statement
        $emailStmt = $this->createMock(PDOStatement::class);
        $emailStmt->expects($this->once())
            ->method('execute')
            ->with(['existing@example.com', 1]);
        $emailStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['userId' => 2]);

        // Configure PDO mock
        $this->db->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($checkStmt, $emailStmt);

        $result = $this->userController->updateUser(1, ['email' => 'existing@example.com']);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Email already in use', $result['error']);
    }
} 