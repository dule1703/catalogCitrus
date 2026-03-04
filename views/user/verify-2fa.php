<div class="form-container bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">2FA Verifikacija</h2>
    <p class="text-sm text-gray-600 text-center mb-4">Unesite 6-cifreni kod poslat na vaš email.</p>

    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-md mb-4 text-center">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/verify-2fa" class="space-y-4">
        <?= $csrfService->getHiddenInput() ?>
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Verifikacioni kod:</label>
            <input type="text" name="code" id="code" maxlength="6" required autofocus placeholder="123456"
                   class="input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md text-center text-lg tracking-widest font-mono focus:outline-none focus:ring focus:border-blue-500">
        </div>
        <button type="submit"
                class="submit-button w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
            Potvrdi kod
        </button>
    </form>
    <p class="mt-4 text-center text-sm text-gray-600">
        <a href="/login" class="text-blue-600 hover:underline">Nazad na prijavu</a>
    </p>
</div>