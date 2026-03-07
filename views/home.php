<?php if (!empty($user)): ?>
    <div class="p-6">
        <h1 class="text-2xl font-bold">Dobrodošao, <?= htmlspecialchars($user['username'] ?? 'Korisniče') ?>!</h1>
        <p>Ovo je zaštićena stranica.</p>

        <form method="POST" action="/logout" class="mt-6 inline">
            <?= $csrfService->getHiddenInput($csrf_token ?? '') ?>
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                Odjavi se
            </button>
        </form>
    </div>
<?php else: ?>
    <div class="p-6">
        <h1 class="text-2xl font-bold">Greška</h1>
        <p>Niste prijavljeni.</p>
        <a href="/login" class="text-orange-600 hover:underline">Nazad na prijavu</a>
    </div>
<?php endif; ?> 