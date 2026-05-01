$f = 'price_compare.php'
$lines = [System.IO.File]::ReadAllLines($f)

# Fix line 428 (0-indexed): broken category pill line
$lines[428] = '                    class="pc-cat-pill <?php echo $selected_category === $cat ? ''active'' : ''''; ?>">'

# Keep lines 0-588 only (drop the duplicate tail 589-597)
$clean = $lines[0..588]

[System.IO.File]::WriteAllLines($f, $clean, [System.Text.Encoding]::UTF8)
Write-Host ("Done. Lines: " + $clean.Length)
