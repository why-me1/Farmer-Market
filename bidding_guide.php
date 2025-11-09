<?php include 'includes/nav.php'; ?>
<div class="container mt-4">
    <h2>How Bidding Works</h2>
    <p>FarmConnect allows buyers to bid on farm products. The bidding duration is **set dynamically** based on the seller’s settings.</p>

    <h3>🛒 Product Sold Process:</h3>
    <ul>
        <li>⏳ **Bidding starts** when a buyer places a bid.</li>
        <li>⏲️ **The bidding timer begins** (e.g., 2 minutes, 5 minutes, or any duration set by the farmer).</li>
        <li>🔺 If the **highest bid is equal to or higher than the farmer's price**, the product is immediately marked as **SOLD**.</li>
        <li>🔄 If the **highest bid is lower than the farmer's price**, the timer is **extended** by another set duration to allow more bidding.</li>
        <li>🔴 Once a valid bid meets the farmer's price and the timer expires, **bidding closes automatically**.</li>
    </ul>

    <h3>⏰ How is the Bidding Time Set?</h3>
    <p>The bidding time is not fixed at 2 minutes. Instead, it can be:</p>
    <ul>
        <li>🕒 **Set by the farmer** (e.g., 2 minutes, 5 minutes, 10 minutes).</li>
        <li>⚙️ **Defined in the system settings** (e.g., admin can control the default time).</li>
        <li>🔄 **Extended automatically** if there are active bids below the farmer’s price.</li>
    </ul>

    <h3>❗ Important Rules:</h3>
    <ul>
        <li>📌 The minimum bidding time is set by the platform (e.g., at least 2 minutes).</li>
        <li>📌 The bidding **closes automatically** if the time expires and a valid bid is placed.</li>
    </ul>
</div>
