# 🌾 Farmers' Market

**Farmers' Market** is a web-based marketplace that connects local farmers and buyers directly.  
Farmers list agricultural products for auction; buyers compete with bids; the platform automatically resolves winners, tracks deliveries, and maintains reputation scores for both sides — creating a transparent and efficient ecosystem for agricultural trade.

---

## 🚀 Features

### Auction & Bidding

- ⏱️ **Live Auction Countdown** — Real-time timers on every product listing
- 🏆 **Automatic Winner Selection** — When an auction ends, the highest numeric bid wins automatically; no farmer intervention required
- 🔢 **Tie-break fairness** — If two buyers bid the same amount, the earliest bidder wins
- 🚫 **No manual approval** — Farmers cannot override or cherry-pick winning bids


### Reputation System (0–5 stars)

- ⭐ **Buyer Score** — Calculated from bid fairness, purchase completion, payment speed, and farmer feedback
- 🌱 **Farmer Score** — Calculated from buyer reviews, sale success rate, listing engagement, and delivery reliability
- 🔄 **Always up-to-date** — Scores are fully recalculated from live data on every relevant event, not accumulated deltas
- 📝 **Farmer rates buyer** — After marking an order delivered, farmers can leave a 1–5 star rating for the buyer
- 🛒 **Buyer rates farmer** — Buyers can leave a product review after purchase, which directly feeds the farmer's score


---


## ⚙️ Tech Stack

| Layer      | Technology                                                 |
| ---------- | ---------------------------------------------------------- |
| Backend    | PHP (procedural)                                           |
| Database   | MySQL / MariaDB 10.4                                       |
| Frontend   | Bootstrap 4, Font Awesome 6, Google Fonts (Inter, Poppins) |
| Server     | Apache via XAMPP                                           |
| Animations | Lottie (login page)                                        |

## ⭐ Reputation Algorithm

See [RATING_SYSTEM_README.md](RATING_SYSTEM_README.md) for the full documentation.

**Summary:**

- **Buyer score** = 5 × (0.35 × BidFairness + 0.30 × PurchaseCompletion + 0.20 × PaymentSpeed + 0.15 × FarmerFeedback)
- **Farmer score** = 5 × (0.40 × BuyerRatings + 0.25 × SaleSuccessRate + 0.20 × Engagement + 0.15 × DeliveryReliability)
- Scale: **0.0 – 5.0 stars**
- Default for new users: **2.5** (neutral)

---

_Last Updated: March 9, 2026_
