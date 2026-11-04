<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Kopipiro</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link href="css/font.css" rel="stylesheet">
    </head>
    <body class="bg-[#1b140e] text-gray-200 inter">
        <?php include_once "components/navbar.php" ?>
        <main class="flex container px-6 md:px-12 lg:px-40 mx-auto min-h-[calc(100vh-3rem)] items-start">
            <section class="w-full">
                <img class="mx-auto h-64 md:h-80 lg:h-100 object-contain" src="images/logo-text.png" alt="Kopipiro - Toko Kopi">
                <p class="text-center">"<i class="text-lg">Kopipiro melayani dengan sepenuh hati </i>❤️"</p>
                <p class="mt-12 px-4 md:px-20 lg:px-60 text-center">
                    Sejak 2026, Kopipiro telah menjadi salah satu destinasi favorit yang menghadirkan pengalaman menikmati racikan kopi berkualitas dengan suasana yang nyaman, modern, dan terjangkau bagi semua kalangan.
                </p>
                <a href="explore.php" class="w-fit flex mx-auto mt-12 items-center gap-4 text-lg font-bold border-2 px-8 py-2 border-yellow-600 duration-300 hover:bg-orange-950 cursor-pointer focus:outline-none focus:bg-orange-950">
                    <svg class="h-6 stroke-2"  xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"class="w-6 h-6 stroke-2">
                        <circle cx="12" cy="12" r="10" />
                        <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76" />
                    </svg>
                    Jelajahi produk
                </a>
            </section>
        </main>
    </body>
</html>