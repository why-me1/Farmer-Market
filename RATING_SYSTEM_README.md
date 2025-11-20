# Automatic Rating System Documentation

## Overview

The Farmer Market platform implements a comprehensive automatic rating system that evaluates both **farmers** and **buyers (users)** based on their behavior, pricing fairness, and market engagement. Ratings range from **0.0 to 10.0**, with a default starting rating of **5.0**.

---

## Buyer (User) Rating System

### Rating Factors

#### 1. Bid Fairness (compared to farmer's asking price)

Evaluated when a user places a bid on a product.

**Rules:**
| Bid Range | Rating Change | Reasoning |
|-----------|---------------|-----------|
| **>50% below** asking price | **-0.5** | Lowball offers waste farmer's time |
| **10-50% below** asking price | **+0.1** | Reasonable negotiation |
| **Within ±10%** of asking price | **+0.3** | Fair and respectful bidding |

**Example:**

- Farmer asks ₹200 for coconut
- User bids ₹95 (52.5% below) → Rating **-0.5** (lowball)
- User bids ₹150 (25% below) → Rating **+0.1** (reasonable)
- User bids ₹195 (2.5% below) → Rating **+0.3** (fair)


## Farmer Rating System

### Rating Factors

#### 1. Product Pricing (compared to market price)

Evaluated when farmer creates a new product post.

**Rules:**
| Price Deviation | Rating Change | Reasoning |
|-----------------|---------------|-----------|
| **>±30%** from market price | **-0.5** | Unrealistic pricing (too high or too low) |
| **Within ±30%** of market price | **+0.2** | Fair market pricing |

**Example:**

- Market price: ₹100
- Farmer lists at ₹140 (40% above) → Rating **-0.5** (overpriced)
- Farmer lists at ₹110 (10% above) → Rating **+0.2** (fair)

---

#### 2. Sale Speed (time to sell)

Evaluated when product is sold. Fast sales indicate good pricing.

**Rules:**
| Time to Sell | Rating Change | Reasoning |
|--------------|---------------|-----------|
| **≤24 hours** | **+0.3** | Excellent pricing, quick sale |
| **24-72 hours** | **+0.1** | Good pricing, reasonable time |
| **≥7 days** | **-0.2** | Poor pricing or low demand |

**Example:**

- Product sold in 18 hours → Rating **+0.3**
- Product sold in 2 days → Rating **+0.1**
- Product sold in 10 days → Rating **-0.2**


#### 3. Final Price Fairness (compared to market price)

Evaluated when product is sold. Checks if winning bid is close to market value.

**Rules:**
| Final Bid vs Market | Rating Change | Reasoning |
|---------------------|---------------|-----------|
| **Within ±20%** | **+0.2** | Fair market transaction |
| **Outside ±20%** | **0** | No penalty, but no reward |

**Example:**

- Market price: ₹200
- Sold for ₹190 (5% below) → Rating **+0.2** (fair)
- Sold for ₹140 (30% below) → No change


#### 4. Unsold Products Penalty

Evaluated when bidding ends but product remains unsold.

**Rules:**
| Unsold Status | Rating Change | Reasoning |
|---------------|---------------|-----------|
| **Single unsold** product | **-0.4** | Overpriced or unrealistic |
| **Each additional unsold** (last 30 days) | **-0.1** | Pattern of poor pricing |

**Example:**

- First unsold product → Rating **-0.4**
- 3 unsold products in 30 days → Rating **-0.6** (-0.4 - 0.1 - 0.1)

**Conditions:**

- Product has `status = 'active'`
- Expiry date has passed (`expiry_date < UNIX_TIMESTAMP(NOW())`)
- At least 5 bids received but none met asking price

---

#### 5. Bidding Activity & Engagement

Evaluated when product is sold. Measures buyer interest.

**Rules:**
| Bid Activity | Rating Change | Reasoning |
|--------------|---------------|-----------|
| **≥10 bids** received | **+0.2** | High engagement, attractive product/pricing |
| **Exactly 5 bids + slow** (>48h to first bid) | **-0.1** | Barely met minimum, low interest |

**Example:**

- Product gets 15 bids → Rating **+0.2** (popular)
- Product gets 5 bids, first bid after 60 hours → Rating **-0.1** (low interest)

---

## Rating Combination Example

**Scenario:** Farmer posts coconut at ₹210 (market: ₹200)

1. **Post Creation**:

   - Price ±5% from market → **+0.2**
   - Current rating: 5.0 → **5.2**

2. **Product Sells**:
   - Sold in 30 hours → **+0.1** (sale speed)
   - Final bid ₹195 (2.5% below market) → **+0.2** (fair price)
   - Received 12 bids → **+0.2** (high engagement)
   - Total adjustment: **+0.5**
   - Final rating: 5.2 → **5.7**

---


## Technical Notes

### Rating Constraints

- **Minimum**: 0.0
- **Maximum**: 10.0
- **Precision**: 1 decimal place
- **Default**: 5.0 (new users)

### Market Price Management

- Updated by admins via `admin/update_market_price.php`
- Keyed by `product_name` (exact match required)
- Used as reference for all fairness calculations

---

## Version History

- **v1.0** (Initial): Basic buyer bid fairness + farmer price fairness
- **v2.0** (Enhanced): Added sale speed, unsold penalties, bidding activity
- **v2.1** (Current): Fixed success rate calculation (auctions vs bids)

---

_Last Updated: November 20, 2025_
