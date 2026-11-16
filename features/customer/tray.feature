Feature: Collecting the tray
  As a customer
  I want to take what the machine dropped
  So that the tray is free for the next purchase

  Scenario: Collecting the tray empties it
    When I insert 1
    And I insert 0.25
    And I insert 0.25
    And I select SODA
    And I collect the tray
    Then the tray is empty
