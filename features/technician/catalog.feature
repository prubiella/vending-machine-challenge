Feature: Changing the products of the machine
  As a technician
  I want to add, retire and replace products while I service the machine
  So that it sells what customers want, without starting it again from nothing

  Background:
    Given a technician "ana.tech" with the password "correct-horse"
    And I am logged in as "ana.tech" with the password "correct-horse"

  Scenario: A technician adds a product and a customer buys it
    When I service the machine adding TEA, "Green tea" at 1.20, with 6 units
    And a customer pays 1.20 for TEA
    Then the customer gets TEA
    When I ask for the stock and the change
    Then the machine holds 5 units of TEA
    And the machine holds 10 units of WATER

  Scenario: A technician retires a product and a customer is refused it
    When I service the machine retiring JUICE
    And a customer pays 1.00 for JUICE
    Then the machine refuses it saying "There is no product with selector JUICE"

  Scenario: A technician replaces one product by another
    When I service the machine replacing JUICE by TEA, "Green tea" at 1.20, with 6 units
    And I ask for the stock and the change
    Then the machine holds 6 units of TEA
    And the machine sells no JUICE

  Scenario: A price that no coins can pay is refused and changes nothing
    When I service the machine adding TEA, "Green tea" at 0.67, with 6 units
    Then the machine refuses it saying "is not a multiple of the smallest coin"
    When I ask for the stock and the change
    Then the machine sells no TEA
