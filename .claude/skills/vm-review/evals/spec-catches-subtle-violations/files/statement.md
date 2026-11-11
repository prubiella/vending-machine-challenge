# Vending machine (test statement)

A short statement written for the vm-review test cases. It is not the real exercise.

- The machine accepts coins of 0.05, 0.10, 0.25 and 1.00, and returns any other coin.
- Products: Water 0.65, Juice 1.00, Soda 1.50.
- The machine keeps a stock of coins of each value. When it sells a product, it gives the change
  using only the coins in that stock, and the coins it gives leave the stock.
- If it cannot give the exact change with the coins in stock, it does not sell the product and
  returns the coins that were inserted.
- Money is handled in cents, as integers.
