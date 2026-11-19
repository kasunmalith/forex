## Phase 20 instrument and Demo operations controls

For an existing installation, first complete Phases 10 and 19. Then run the CLI-only, idempotent Phase 20 migration once:

```text
C:\wamp64\bin\php\php8.3.28\php.exe C:\wamp64\www\forex\database\apply-phase20-instruments-trading.php
```

The migration adds instrument tick/pip, profit-currency, order-volume, and per-instrument control settings; snapshots each simulator's seed anchor; and inserts default global Demo controls without overwriting current settings. Fresh imports already contain the instrument columns and settings; the helper only adds missing fields and the simulator seed anchor.

Admin access reuses `instruments.view`, `instruments.manage`, `trading.view`, and `trading.manage`. All instrument/global-control changes use the existing Admin activity log. Changes remain limited to simulated Demo trading and market data. Turning off new Demo trading leaves open-position viewing and closure available. Market data cannot be paused while open positions need valuation. A guarded per-instrument reset requires Demo trading disabled, no open positions, a reason, and explicit confirmation; it clears only that instrument's quote, candle, and simulation-state rows.

Volume validation uses the instrument minimum, maximum, and step, capped by the global order-volume limit. Spread remains an absolute price difference (`ask - bid`); margin remains a fraction of notional (for example, `0.01` means 1%). Changing a seed price does not jump a running simulation; it becomes the anchor when an administrator explicitly resets that instrument's simulated market state.

No cTrader, real broker, real exchange feed, real funds, CMS, or content-management workflow is added in Phase 20.
