<div class="form-container bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Dvofaktorska verifikacija</h2>
    <p class="text-center text-sm text-gray-500 mb-6">
        Unesi 6-cifreni kod koji smo poslali na tvoj email.
    </p>

    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-md mb-4 text-center">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/verify-2fa" class="space-y-4">
        <?= $csrfService->getHiddenInput($csrf_token ?? '') ?>
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Verifikacioni kod:</label>
            <input
                type="text"
                name="code"
                id="code"
                required
                maxlength="6"
                pattern="\d{6}"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="123456"
                class="input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md text-center
                       text-xl tracking-widest focus:outline-none focus:ring focus:border-orange-500"
            >
        </div>
        <button type="submit"
                class="submit-button w-full bg-orange-600 text-white py-2 px-4 rounded-md
                       hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500
                       focus:ring-offset-2 transition-colors duration-200">
            Potvrdi
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-600">
        <a href="/login" class="text-orange-600 hover:underline">Nazad na prijavu</a>
    </p>
</div>