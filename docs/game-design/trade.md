# Trade

Built in GSD Phase 27.

## Why it exists

Specialisation. A player whose land favours iron should be able to turn that into
food without conquering a farm. Trade converts geographic luck into a social
relationship, which serves the "people are the endgame" pillar.

## Forms

| Form | Speed | Rate | Risk |
|------|-------|------|------|
| **NPC trade** | Instant | Poor, fixed | None |
| **Player market** | Travel time | Negotiated | Transport can be intercepted |
| **Alliance transfer** | Travel time | Free or low tax | Low |

NPC trade is deliberately a bad deal. It is a floor that guarantees a stuck player
can always convert something, not a strategy.

## Conservation — the rule everything else serves

**A trade moves value. It never creates it.**

```
sender_before + receiver_before  ==  sender_after + receiver_after + tax
```

This is asserted as a property test over random sequences (Phase 27). Trade is
the single most attractive surface for a duplication exploit (threat model T-12),
because it is the one place resources legitimately cross an ownership boundary.

The controls:

1. **Escrow on order creation.** Resources leave the seller's city immediately and
   are held by the order.
2. **Resources in transit exist in exactly one place** — never simultaneously in
   the city and the transport march.
3. **One transaction** for the transfer.
4. **Row lock on the order.** Two concurrent accepts produce one success and one
   `MARKET_ORDER_UNAVAILABLE`.
5. **Cancellation and expiry return escrow exactly once.**

## Limits

Trade volume per player per period is capped (`TRADE_LIMIT_REACHED`). This is the
main control against resource laundering — funnelling many accounts' output into
one — and against a market that lets a whale simply buy a server.

## Tax

A percentage sink scaling with distance and reduced by Marketplace level. It is a
genuine faucet counterweight (see [`economy.md`](economy.md)) and gives the
Marketplace a reason to be upgraded.

## Transport

Player-to-player trade moves goods via a transport march, so it takes real time
and travels through real space. A transport march can be intercepted.

That is the design payoff for routing trade through the march system rather than
teleporting resources: it makes trade routes worth protecting, and worth raiding.

## Dynamic pricing

Deferred to Phase 46. It is only worth building once there is real player data to
observe — a simulated market tuned against no players is a guess with extra steps.
