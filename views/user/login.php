<div class="form-container bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Prijava</h2>
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-md mb-4 text-center">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="/login" class="space-y-4">
        <?= $csrfService->getHiddenInput() ?>
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Korisničko ime:</label>
            <input type="text" name="username" id="username" required
                class="input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-500">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Šifra:</label>
            <input type="password" name="password" id="password" required
                class="input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-500">
        </div>
        <button type="submit"
                class="submit-button w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
            Prijavi se
        </button>
    </form>
    <p class="mt-4 text-center text-sm text-gray-600">
        <a href="/register" class="text-blue-600 hover:underline">Nemate nalog? Registrujte se</a>
    </p>
    <p class="mt-2 text-center text-sm text-gray-600">
        <a href="/forgot-password" class="text-blue-600 hover:underline">Zaboravili ste šifru?</a>
    </p>
</div>
