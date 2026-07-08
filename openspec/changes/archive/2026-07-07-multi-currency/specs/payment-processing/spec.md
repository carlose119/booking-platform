# Delta for Payment Processing

## MODIFIED Requirements

### Requirement: PaymentIntent Creation

The system SHALL create a Stripe PaymentIntent when a guest confirms a booking for tenants with payment_policy=100upfront or fraction. The PaymentIntent amount MUST match the required payment, currency MUST match the tenant default currency resolved at charge time, and booking payment snapshot fields MUST store the charged amount and currency. Existing or missing currency data MUST fall back to `usd`.
(Previously: PaymentIntent amount matched payment, with implicit/hardcoded USD and no currency snapshot.)

#### Scenario: Full payment PaymentIntent
- GIVEN tenant T1 has payment_policy=100upfront, default_currency=`eur`, and service costs 5000 minor units
- WHEN a guest confirms booking
- THEN PaymentIntent is created with amount=5000 and currency=`eur`
- AND PaymentIntent ID, charged amount, and charged currency are stored on the booking

#### Scenario: Deposit payment PaymentIntent
- GIVEN tenant T1 has payment_policy=fraction, deposit_percentage=20, default_currency=`usd`, and service costs 5000 minor units
- WHEN a guest confirms booking
- THEN PaymentIntent is created with amount=1000 and currency=`usd`
- AND deposit amount, total, charged amount, and charged currency are recorded on booking

#### Scenario: Missing tenant currency falls back to USD
- GIVEN tenant T1 has no configured currency
- WHEN payment is required for a booking
- THEN PaymentIntent currency is `usd`
- AND booking charged currency is `usd`

#### Scenario: Unsupported Stripe currency is rejected
- GIVEN tenant T1 has an unsupported currency configured
- WHEN payment is attempted
- THEN no PaymentIntent is created
- AND the booking remains unpaid or pending without cross-tenant side effects
