Feature: Buying a product
  As a customer
  I want to pay with coins and take what I chose
  So that I get my product and the right change

  Scenario: Buy a soda with exact change
    When I insert 1
    And I insert 0.25
    And I insert 0.25
    And I select SODA
    Then the tray holds SODA and no coins

  Scenario: Buy a water and get change
    Given the machine has 10 coins of 0.25
    And the machine has 10 coins of 0.10
    When I insert 1
    And I select WATER
    Then the tray holds WATER and the coins 0.25, 0.10

  Scenario: A product that is sold out is refused
    Given SODA is sold out
    When I insert 1
    And I insert 0.25
    And I insert 0.25
    And I select SODA
    Then the machine refuses it saying "is sold out"
    And my credit is 1.50
    And the tray is empty
