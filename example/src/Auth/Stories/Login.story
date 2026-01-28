Feature: User Login

Scenario: Successful login with valid credentials
    Given a registered user with email "user@example.com"
    When they submit the login form with correct password
    Then they should be authenticated
    And redirected to the dashboard

Scenario: Login fails with invalid password
    Given a registered user with email "user@example.com"
    When they submit the login form with wrong password
    Then they should see an error message
    And remain on the login page

Scenario Outline: Login validation errors
    Given a login form
    When the user submits with <email> and <password>
    Then they should see error <message>

    Examples:
        | email            | password | message                |
        | invalid          | secret   | Invalid email format   |
        | user@example.com |          | Password is required   |
        |                  | secret   | Email is required      |
