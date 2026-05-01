# Farmers' Market

Farmers' Market is a web marketplace where farmers list products and buyers place auction bids. The system automatically resolves winners, supports delivery workflows, and maintains reputation scores for buyers and farmers.

## Features

- Live auctions with countdown timers
- Automatic winner selection (highest numeric bid)
- Tie-break rule (earliest bidder wins on equal highest bid)
- Buyer and farmer reputation scores on a 0-5 scale
- Buyer reviews and farmer-to-buyer ratings
- Delivery updates and transaction tracking
- Notifications for bids, auction wins, delivery, and followed farmer posts
- Messaging/chat between users
- Wishlist for buyers
- Follow/favorite farmer support
- Search and filtering (category, location, price, status)
- Admin tools for users, posts, statistics, and market prices

## Tech Stack

- Backend: PHP (procedural)
- Database: MySQL / MariaDB
- Frontend: Bootstrap 4, JavaScript, CSS
- Server: Apache (XAMPP)

## Reputation

See [RATING_SYSTEM_README.md](RATING_SYSTEM_README.md) for full scoring details.

- Buyer score formula:
  5 x (0.35 x BidFairness + 0.30 x PurchaseCompletion + 0.20 x PaymentSpeed + 0.15 x FarmerFeedback)
- Farmer score formula:
  5 x (0.40 x BuyerRatings + 0.25 x SaleSuccessRate + 0.20 x Engagement + 0.15 x DeliveryReliability)
- Score range: 0.0 to 5.0
- Default score for new users: 2.5

## Quick Setup

1. Place this project in your XAMPP htdocs directory.
2. Start Apache and MySQL from XAMPP. If XAMPP MySQL refuses to stay up, run [start_mysql_3306.bat](start_mysql_3306.bat) from this project to launch MariaDB directly on 3306.
3. Create a database in phpMyAdmin.
4. Import [database.sql](database.sql). If needed, also import [delivery_migration.sql](delivery_migration.sql) and [farmer_market.sql](farmer_market.sql).
5. Update your local MySQL credentials by setting `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME` if your setup differs from the defaults used in [includes/config.php](includes/config.php).
6. Open http://localhost/demo in your browser.

Last updated: April 23, 2026.
