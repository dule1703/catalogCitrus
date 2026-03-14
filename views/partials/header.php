<header class="bg-orange-600 text-white shadow">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
        <div class="text-2xl font-bold">
            <a href="/" class="hover:text-orange-200">CitrusApp</a>
        </div>

        <nav class="space-x-6">
            <?php if (!empty($isLoggedIn)): ?>
                <!-- Ulogovan -->
                <span class="font-medium">
                    <?= htmlspecialchars($currentUser['username'] ?? 'Korisnik') ?>
                </span>

                <a href="/logout" 
                   class="bg-white text-orange-600 px-4 py-2 rounded hover:bg-gray-100 transition">
                    Odjavi se
                </a>
            <?php else: ?>
                <!-- Gost -->
                <a href="/login" 
                   class="hover:text-orange-200 transition">
                    Prijava
                </a>

                <a href="/register" 
                   class="bg-white text-orange-600 px-4 py-2 rounded hover:bg-gray-100 transition">
                    Registracija
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>