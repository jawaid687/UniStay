<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Auth System'; ?></title>
    <?php if(isset($css_file)): ?>
        <link rel="stylesheet" href="../assets/css/<?php echo $css_file; ?>">
    <?php endif; ?>
</head>
<body>