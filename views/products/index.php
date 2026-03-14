<?php
// views/products/index.php

// Očekivane promenljive iz controllera:
// - $products     = niz proizvoda (array of assoc arrays)
// - $isLoggedIn   = bool
// - $user         = array sa podacima o korisniku (ili null)
// - $title        = string (npr. "Početna - CitrusApp")

// Ovo je sadržaj koji će se ubaciti u main layout
?>

<div class="py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-10">
        Naši sveži citrusi
    </h1>

    <?php if (empty($products)): ?>
        <div class="text-center text-gray-600 py-12">
            <p class="text-xl">Trenutno nema dostupnih proizvoda.</p>
            <p class="mt-2">Pokušajte kasnije ili nas kontaktirajte.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            <?php foreach ($products as $product): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300 flex flex-col h-full">
                    <!-- Slika -->
                    <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
                        <?php if (!empty($product['image_url'])): ?>
                            <img 
                                src="<?= htmlspecialchars($product['image_url']) ?>" 
                                alt="<?= htmlspecialchars($product['title'] ?? 'Proizvod') ?>"
                                class="w-full h-full object-cover transition-transform duration-500 hover:scale-105"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                Nema slike
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sadržaj -->
                    <div class="p-5 flex flex-col flex-grow">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 line-clamp-2">
                            <?= htmlspecialchars($product['title'] ?? 'Nepoznat proizvod') ?>
                        </h3>

                        <?php if (!empty($product['description'])): ?>
                            <p class="text-gray-600 mb-4 line-clamp-3 flex-grow">
                                <?= htmlspecialchars($product['description']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>