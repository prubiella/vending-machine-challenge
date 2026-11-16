Feature: Servicing the machine
  As a technician
  I want to set the stock and the change of the machine
  So that customers find their products and their change

  Background:
    Given a technician "ana.tech" with the password "correct-horse"

  Scenario: A technician sets the units of a product and the whole change
    Given I am logged in as "ana.tech" with the password "correct-horse"
    When I service the machine with 20 units of WATER and only 15 coins of 0.25
    And I ask for the stock and the change
    Then the machine holds 20 units of WATER
    And the machine holds 10 units of JUICE
    And the machine holds 15 coins of 0.25
    And the machine holds 0 coins of 0.10

  Scenario: The machine is not serviced without logging in
    When I service the machine with 20 units of WATER and only 15 coins of 0.25
    Then the machine refuses it with status 401 saying "Send a technician's token"
