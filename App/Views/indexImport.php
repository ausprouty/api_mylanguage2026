<h1>Import Index</h1>
<ul>
    <?php foreach ($routes as $route => $file): ?>
        <li><a href="<?php echo $route; ?>"><?php echo basename($file, '.php'); ?></a></li>
    <?php endforeach; ?>
</ul>
