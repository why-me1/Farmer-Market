# Rating System Documentation

## Overview

The Farmers' Market platform uses a **multi-factor reputation algorithm** to score both buyers and farmers on a **0.0 – 5.0 star scale**. Scores are stored in `users.automatic_rating` and are **fully recalculated from raw data** on every relevant event — there are no cumulative delta adjustments. New users start at **2.5** (neutral).

Scores are displayed as star ratings throughout the platform (profile pages, dashboards, bid activity).

---

## Buyer Reputation Score

**Formula:**

> BuyerScore = 5 × (0.35 × BidFairness + 0.30 × PurchaseCompletion + 0.20 × PaymentSpeed + 0.15 × FarmerFeedback)

All four sub-factors return a value between **0.0 and 1.0**. Each returns **0.5 (neutral)** when no data exists yet for that buyer.

---

### Factor 1 — Bid Fairness (weight: 35%)

Measures how reasonable a buyer's bids are relative to the farmer's asking price, averaged across all bids placed.

| Bid vs Asking Price         | Score |
| --------------------------- | ----- |
| Within 10% below (or above) | 1.0   |
| 10 – 30% below              | 0.7   |
| 30 – 50% below              | 0.4   |
| More than 50% below         | 0.0   |

Only numeric bids are included. Bids above the asking price are treated as within 10% (score 1.0).

**Triggered by:** every bid placed (`comment.php`)

---

### Factor 2 — Purchase Completion (weight: 30%)

Ratio of auctions the buyer actually received delivery on vs total auctions they won.

> PurchaseCompletion = delivered wins ÷ total wins

- A buyer who wins and always receives delivery scores **1.0**
- A buyer who wins but orders are never marked delivered scores **0.0**
- No wins yet → **0.5** (neutral)

**Triggered by:** every bid placed; every delivery confirmed in Manage Orders

---

### Factor 3 — Payment Speed (weight: 20%)

Measures how quickly a transaction is completed after the buyer wins an auction. The gap is between two timestamps stored in the `transactions` table:

- `win_at` — stamped when the auction auto-selects the highest bidder
- `paid_at` — stamped when the farmer marks the order as **Delivered**

| Gap (win → delivered)         | Score         |
| ----------------------------- | ------------- |
| ≤ 1 hour                      | 1.0           |
| 1 – 12 hours                  | 0.7           |
| > 12 hours                    | 0.4           |
| No completed transactions yet | 0.5 (neutral) |

Averaged across all past transactions.

**Triggered by:** farmer marking an order as Delivered (`manage_orders.php`)

---

### Factor 4 — Farmer Feedback (weight: 15%)

Average of all star ratings (1–5) given by farmers to this buyer after completed deliveries, normalised to 0–1.

> FarmerFeedback = average buyer_rating ÷ 5

- Farmers rate buyers via the star widget on the Manage Orders page after marking delivered
- Each farmer can rate a buyer once per order
- No ratings yet → **0.5** (neutral)

**Triggered by:** farmer submitting a buyer rating (`manage_orders.php`)

---

## Farmer Reputation Score

**Formula:**

> FarmerScore = 5 × (0.40 × BuyerRatings + 0.25 × SaleSuccessRate + 0.20 × EngagementScore + 0.15 × DeliveryReliability)

All four sub-factors return a value between **0.0 and 1.0**. Each returns **0.5 (neutral)** when no data exists yet.

---

### Factor 1 — Buyer Ratings (weight: 40%)

Average star rating (1–5) left by buyers on the farmer's products via product reviews, normalised to 0–1.

> BuyerRatings = average review rating ÷ 5

**Triggered by:** buyer submitting a product review (`submit_review.php`)

---

### Factor 2 — Sale Success Rate (weight: 25%)

Proportion of the farmer's admin-approved listings that have been sold or delivered.

> SaleSuccessRate = (sold + delivered posts) ÷ approved posts

- Only admin-approved listings count in the denominator — posts still awaiting approval do not penalise the farmer
- No approved posts yet → **0.5** (neutral)

**Triggered by:** any event that recalculates the farmer score (bid placed, auction won, delivery confirmed)

---

### Factor 3 — Engagement Score (weight: 20%)

Measures buyer demand per product by counting unique bidders per listing, then averaging across all listings.

| Unique bidders per listing | Score |
| -------------------------- | ----- |
| > 10                       | 1.0   |
| 5 – 10                     | 0.7   |
| 2 – 4                      | 0.4   |
| < 2                        | 0.1   |

No listings with bids yet → **0.1**

**Triggered by:** every bid placed on any of the farmer's products

---

### Factor 4 — Delivery Reliability (weight: 15%)

Proportion of completed sales that the farmer has marked as delivered.

> DeliveryReliability = delivered posts ÷ (sold + delivered posts)

- A farmer who always marks delivery scores **1.0**
- No completed sales yet → **0.5** (neutral)

**Triggered by:** farmer marking an order as Delivered

---

## When Scores Are Recalculated

| Event                          | Buyer score updated        | Farmer score updated |
| ------------------------------ | -------------------------- | -------------------- |
| Buyer places a bid             | ✅                         | ✅                   |
| Auction auto-selects winner    | ✅                         | ✅                   |
| Farmer marks order Delivered   | ✅ (payment speed stamped) | ✅                   |
| Farmer rates a buyer           | ✅                         | —                    |
| Buyer submits a product review | —                          | ✅                   |

Every recalculation reads **current live data** from the database — no deltas, no drift.

---

## Auction Winner Selection

When an auction ends:

- The **highest numeric bid** wins (string-safe `CAST(comment_text AS DECIMAL(12,2))`)
- Minimum 5 bids required and the highest bid must meet the asking price
- Tie-break: earliest bidder at the maximum amount wins (`ORDER BY created_at ASC LIMIT 1`)
- The winning `comments` row is marked `is_approved = 1`

Farmers have **no ability to manually approve or reject bids**. The process is fully automatic.

---

## Score Display

Scores are displayed as filled stars (★) out of 5 across:

- Farmer public profile (`farmer/profile.php`) — "Fairness Rating"
- User dashboard (`user/dashboard.php`)
- User public profile (`user/profile.php`)
- Bid Activity page (`farmer/manage_comments.php`)

Badge thresholds on profiles:

| Score  | Badge                      |
| ------ | -------------------------- |
| ≥ 4.5  | Top Seller / Trusted Buyer |
| ≥ 3.75 | Verified Seller            |
| < 3.75 | New Seller                 |

---

## Technical Notes

- **Scale:** 0.0 – 5.0, stored as `DECIMAL(3,1)` in `users.automatic_rating`
- **Default:** 2.5 (neutral, for new users with no activity)
- **Migration:** legacy 0–10 values are halved to 0–5 automatically on first load
- **New tables:** `transactions` (win_at / paid_at tracking), `buyer_ratings` (farmer-to-buyer ratings) — created automatically if they don't exist
- **Market prices:** managed by admins via `admin/update_market_price.php`, stored in `market_prices` table

---

## Version History

| Version        | Changes                                                                                                                                                                                                                                                                      |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v1.0           | Delta-based system: fixed +/- adjustments on 0–10 scale                                                                                                                                                                                                                      |
| v2.0           | Added sale speed, unsold penalties, bidding activity deltas                                                                                                                                                                                                                  |
| v3.0 (current) | Full rewrite: multi-factor weighted formula, 0–5 scale, full recalculation on every event, `transactions` and `buyer_ratings` tables, farmer-rates-buyer UI, auction winner tie-break fix, sale success rate excludes unapproved posts, reviews trigger farmer recalculation |

---

_Last Updated: March 9, 2026_
