<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Chain Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/hotel_chain_management/assets/css/styles.css?v=<?php echo time(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="/hotel_chain_management/assets/js/main.js" defer></script>
    <script src="/hotel_chain_management/assets/js/script.js" defer></script>
    <script src="/hotel_chain_management/assets/js/dashboard.js" defer></script>
</head>
<body>
    <div class="container mx-auto">