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
    <!-- KOMENTARI -->
    <div class="mt-20">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-10">
            Šta kažu naši kupci
        </h2>

        <?php if (empty($comments)): ?>
            <div class="text-center text-gray-500 py-10">
                <p>Nema odobrenih komentara trenutno.</p>
            </div>
        <?php else: ?>
            
            <!-- Grid komentara -->
            <div id="comments-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($comments as $comment): ?>
                    <div class="bg-white rounded-2xl shadow p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center font-semibold">
                                <?= strtoupper(substr($comment['name'] ?? 'K', 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($comment['name'] ?? 'Anonimni kupac') ?></p>
                                <p class="text-xs text-gray-500">
                                    <?= date('d.m.Y.', strtotime($comment['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                        <p class="text-gray-700 leading-relaxed">
                            "<?= htmlspecialchars($comment['text']) ?>"
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Dugme "Učitaj još" -->
            <div class="text-center mt-12 mb-12">
                <button id="load-more-btn"
                        data-offset="3"
                        class="px-8 py-3 bg-orange-600 text-white font-medium rounded-xl hover:bg-orange-700 transition-colors">
                    Učitaj još komentara
                </button>
            </div>

        <?php endif; ?>
    </div>
         <!-- === FORMA ZA NOVI KOMENTAR === -->
    <div class="mt-12 bg-white rounded-2xl shadow p-8">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Ostavi svoj komentar</h2>

        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 text-center">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 text-center">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/comments" class="space-y-6">
            <?= $csrfService->getHiddenInput($csrf_token ?? '') ?>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Vaše ime</label>
                <input type="text" name="name" id="name" required
                       class="input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Vaš email</label>
                <input type="email" name="email" id="email" required
                       class="input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>

            <div>
                <label for="text" class="block text-sm font-medium text-gray-700">Vaš komentar</label>
                <textarea name="text" id="text" rows="5" required
                          class="input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
            </div>

            <button type="submit"
                    class="submit-button w-full bg-orange-600 text-white py-3 px-4 rounded-xl hover:bg-orange-700 transition-colors">
                Pošalji komentar
            </button>
        </form>
    </div>               
</div>