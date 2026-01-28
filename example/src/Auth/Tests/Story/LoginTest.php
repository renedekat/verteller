<?php
declare(strict_types=1);

namespace App\Auth\Tests\Story;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Verteller\Attributes\CoversStory;

final class LoginTest extends TestCase
{

    #[CoversStory(storyFile: 'src/Auth/Stories/Login.story', scenario: 'Successful login with valid credentials', hash: '0f68c1c061b07a675745c669a657fd30')]
    public function test_successful_login_with_valid_credentials(): void
    {
        /*
        Scenario: Successful login with valid credentials
        Given a registered user with email "user@example.com"
        When they submit the login form with correct password
        Then they should be authenticated
        And redirected to the dashboard
        */
        self::markTestIncomplete('This test is auto-generated from the story and needs implementation.');
    }

    #[CoversStory(storyFile: 'src/Auth/Stories/Login.story', scenario: 'Login fails with invalid password', hash: 'd9e0017a7b1da56ca1791699cb304dcd')]
    public function test_login_fails_with_invalid_password(): void
    {
        /*
        Scenario: Login fails with invalid password
        Given a registered user with email "user@example.com"
        When they submit the login form with wrong password
        Then they should see an error message
        And remain on the login page
        */
        self::markTestIncomplete('This test is auto-generated from the story and needs implementation.');
    }

    #[CoversStory(storyFile: 'src/Auth/Stories/Login.story', scenario: 'Login validation errors', hash: 'dc290638865123bc65abb673f1f3d178')]
    #[DataProvider('login_validation_errors_provider')]
    public function test_login_validation_errors(string $email, string $password, string $message): void
    {
        /*
        Scenario Outline: Login validation errors
        Given a login form
        When the user submits with <email> and <password>
        Then they should see error <message>
        */
        self::markTestIncomplete('This test is auto-generated from the story outline and needs implementation.');
    }

    public static function login_validation_errors_provider(): array
    {
        return [
            ['email' => 'invalid', 'password' => 'secret', 'message' => 'Invalid email format'],
            ['email' => 'user@example.com', 'password' => '', 'message' => 'Password is required'],
            ['email' => '', 'password' => 'secret', 'message' => 'Email is required'],
        ];
    }
}
