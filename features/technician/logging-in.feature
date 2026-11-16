Feature: Logging in as a technician
  As a technician
  I want to log in with my username and password
  So that only I can read the stock and the change of the machine

  Every login gives a token with the technician's role, so these scenarios cannot reach a token
  without it: the kernel tests cover that refusal with a token made for the purpose.

  Background:
    Given a technician "ana.tech" with the password "correct-horse"

  Scenario: The right username and password let the technician in
    When I log in as "ana.tech" with the password "correct-horse"
    Then I am let in

  Scenario: A wrong password is refused
    When I log in as "ana.tech" with the password "wrong-horse"
    Then the machine refuses it with status 401 saying "Wrong username or password"

  Scenario: The stock and the change are not shown without logging in
    When I ask for the stock and the change
    Then the machine refuses it with status 401 saying "Log in as a technician"

  Scenario: A technician reads the stock and the change
    Given I am logged in as "ana.tech" with the password "correct-horse"
    When I ask for the stock and the change
    Then the machine holds 10 units of WATER
    And the machine holds 10 coins of 0.25
    And the machine holds 0 coins of 1
