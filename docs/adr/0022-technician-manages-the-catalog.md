# 22. A technician changes the catalog while servicing the machine

## Status

Accepted.

## Context

The products of the machine came only from `vending_machine.default_products` when it was first
installed. Adding one meant editing `config/services.yaml` and running `vending:reset`, which wipes
the stock, the credit and the tray. A real machine changes its products when a technician opens it:
they take out what no longer sells, put in something new, or change a price label.

## Decision

- A service can also change which products the machine sells. Next to the units and the coins it
  takes a `CatalogChange`: the products it installs, each with selector, name, price and units, and
  the selectors it retires. Replacing a product by another is a retire and an install in the same
  service. There is no separate use case and no separate route: it is still one `SERVICE`.
- The change is explicit, never declarative. A product left out of the request stays where it is,
  so an incomplete payload cannot empty half the machine, and a selector the machine lacks under
  `units` or under `retire` is still `UnknownSelectorException` (404 over HTTP): a typo is never
  taken as a new product or as nothing to do.
- Installing a selector the machine already has redefines its name and price and sets its units.
  That is how a price changes, and it keeps the service idempotent (ADR 10): the same service twice
  leaves the same machine. Retiring twice is refused the second time and changes nothing, so a
  retried request still leaves the machine as the first one did.
- Retiring a product takes its slot and the units still in it out of the machine. A unit already in
  the tray stays there, since the tray keeps its own copy of each product it holds.
- A service that contradicts itself is refused with `ContradictoryCatalogChangeException` and
  changes nothing: the same selector installed twice or retired twice, installed and retired, or
  given units under `units` while it is installed (it brings its own) or retired (it has none left).
- A price must be a multiple of the smallest coin (`Coin::smallest()`, 5 cents), or no coins could
  pay it exactly or give its change: `UnpayablePriceException`. `Product` knows no coins, so the
  machine checks it where a product enters it, in `install()` and in `service()`. `restore()` does
  not check it: loading is not installing (ADR 19).
- The machine holds at most `Inventory::CAPACITY` (40) products, a common size for a snack machine.
  It is counted after the retired products leave and the installed ones arrive, so a full machine
  can swap one product for another: `InventoryCapacityExceededException`. An installed product's
  units are bounded by `Slot::CAPACITY` as a restock is (ADR 19).
- All of it is computed before any part changes, in one step of the aggregate (ADR 6): retires
  first, then installs, then the units of the products left in place, then the coins.
- Two domain events record it, after `MachineServiced`: `ProductRetired` (technician, product, units
  removed) and `ProductInstalled` (technician, product, units). They reach `machine_history` with
  their own types, `product_retired` and `product_installed`, so its index on `type` answers who
  installed or retired what without reading the JSON. No migration and no change to the relay.
- Installing a product with `Slot::LOW_STOCK_THRESHOLD` units or fewer records no
  `StockRunningLow`: that event is a sale crossing the threshold, and a service that leaves few
  units, an install or a restock, records none, since the technician is there.
- Over HTTP the price of an installed product is `priceCents`, an integer, as `GET /machine`
  answers it. In the console it is written as the console shows prices, `0.65`:
  `vending:service --install SELECTOR:PRICE:UNITS:NAME --retire SELECTOR`, both repeatable. The name
  goes last so that it may hold colons. `SERVICE` inside `vending:run` asks nothing new: it keeps
  asking the units of the products present.
- Every technician may change the catalog and the prices. There is one role.

## Consequences

- "What if we need to add new products?" has an answer that keeps the machine running: a technician
  installs it in the next service, and the history records who did.
- The default products in the configuration only describe the machine as first installed.
- A technician with a typo in a price changes the price for every customer until the next service.
  Known simplification: one role for restocking and pricing, where a real operator would often
  separate them.
- A name with a space reaches `vending:service` through `docker compose exec`, but not through
  `make run`, which splits its arguments on spaces.

## Alternatives considered

- **A full declarative inventory**, where a product left out is retired: one missing line in a
  payload would delete a product and its units.
- **Refusing to install a selector the machine has**, as a duplicate: changing a price would then
  take two services, or a retire and an install of the same selector, which is a contradiction.
- **Extending `MachineServiced`** with what was installed and retired: as cheap to relay, but the
  history could only find who installed a product by reading every service's JSON.
- **A separate use case and route for the catalog**: a technician changes products with the machine
  open, in the same visit as the restock, and two calls could leave the machine half changed.
- **Prices checked in `Product`**: it would make every product know the coins of the machine.
