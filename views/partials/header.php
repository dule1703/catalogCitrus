<header class="bg-orange-600 text-white shadow">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
        <div class="text-2xl font-bold">
            <a href="/" class="hover:text-orange-200">CitrusApp</a>
        </div>

        <nav class="space-x-6 flex items-center">
            <?php if (!empty($isLoggedIn)): ?>
                <!-- ULOGOVAN -->
                <span class="font-medium">
                    <?= htmlspecialchars($currentUser['username'] ?? 'Korisnik') ?>
                </span>

                <!-- Logout preko forme - POST metoda -->
                <form method="POST" action="/logout" class="inline">
                    <?= $csrfService->getHiddenInput($csrf_token ?? '') ?>
                    <button type="submit"
                            class="bg-white text-orange-600 px-5 py-2 rounded hover:bg-gray-100 transition font-medium">
                        Odjavi se
                    </button>
                </form>
            <?php else: ?>
                <!-- Gost -->
                <a href="/login" class="hover:text-orange-200 transition font-medium">Prijava</a>
                <a href="/register" 
                class="bg-white text-orange-600 px-5 py-2 rounded hover:bg-gray-100 transition font-medium">
                    Registracija
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>