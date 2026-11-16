Feature: Inserting and returning coins
  As a customer
  I want the machine to take only the coins it knows and give me mine back
  So that I never lose money

  Scenario: Start adding money and get it back
    When I insert 0.10
    And I insert 0.10
    And I ask for my coins back
    Then the tray holds no product and the coins 0.10, 0.10
    And my credit is 0.00

  Scenario: A coin the machine does not take is refused
    When I insert 0.50
    Then the machine refuses it with status 422 saying "does not accept a coin of 0.50"
    And my credit is 0.00
