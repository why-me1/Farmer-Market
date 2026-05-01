<?php
echo "<h1>Database Diagnostic Report</h1>";
echo "<hr>";

// Check MySQL extension
echo "<h2>1. PHP Extensions</h2>";
echo "mysqli extension loaded: " . (extension_loaded('mysqli') ? '<span style="color:green">✓ YES</span>' : '<span style="color:red">✗ NO</span>') . "<br>";

// Check database settings
echo "<h2>2. Current Database Settings</h2>";
$settings = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '(empty)',
    'name' => getenv('DB_NAME') ?: 'farmer_market',
];

echo "<table border='1'><tr><th>Setting</th><th>Value</th></tr>";
foreach ($settings as $key => $value) {
    echo "<tr><td>$key</td><td><code>$value</code></td></tr>";
}
echo "</table>";

// Test connections with different configurations
echo "<h2>3. Connection Tests</h2>";

$testConfigs = [
    ['host' => '127.0.0.1', 'user' => 'root', 'password' => '', 'name' => 'farmer_market', 'port' => 3306, 'label' => 'Root @ 127.0.0.1'],
    ['host' => 'localhost', 'user' => 'root', 'password' => '', 'name' => 'farmer_market', 'port' => 3306, 'label' => 'Root @ localhost'],
    ['host' => 'localhost', 'user' => '', 'password' => '', 'name' => 'farmer_market', 'port' => 3306, 'label' => 'Anonymous @ localhost'],
    ['host' => 'localhost', 'user' => 'root', 'password' => '', 'name' => '', 'port' => 3306, 'label' => 'Root (no database)'],
];

foreach ($testConfigs as $config) {
    echo "<p><strong>{$config['label']}:</strong> ";
    $conn = @new mysqli(
        $config['host'],
        $config['user'],
        $config['password'],
        $config['name'],
        $config['port']
    );

    if ($conn->connect_error) {
        echo '<span style="color:red">✗ FAILED</span><br>';
        echo '&nbsp;&nbsp;&nbsp;&nbsp;Error: ' . htmlspecialchars($conn->connect_error) . '<br>';
    } else {
        echo '<span style="color:green">✓ SUCCESS</span><br>';
        $result = $conn->query("SELECT VERSION()");
        if ($result) {
            $row = $result->fetch_row();
            echo '&nbsp;&nbsp;&nbsp;&nbsp;MySQL Version: ' . htmlspecialchars($row[0]) . '<br>';
        }
        $conn->close();
    }
}

// Check port 3306 listening
echo "<h2>4. Port 3306 Status</h2>";
$fp = @fsockopen('localhost', 3306, $errno, $errstr, 2);
if ($fp) {
    echo '<span style="color:green">✓ Port 3306 is listening</span>';
    fclose($fp);
} else {
    echo '<span style="color:red">✗ Port 3306 is NOT listening</span>';
    echo "<br>Error: $errstr ($errno)";
}
