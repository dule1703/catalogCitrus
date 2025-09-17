document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('registerForm');
        const errorMessage = document.getElementById('error-message');

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const username = document.getElementById('username').value.trim();
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;

                // Klijentska validacija
                const usernameRegex = /^[a-zA-Z0-9_-]{3,20}$/;
                if (!usernameRegex.test(username)) {
                    errorMessage.textContent = 'Korisničko ime nije validno. Dozvoljeni su alfanumerički karakteri, donja crta i crtica, dužine 3-20 karaktera.';
                    errorMessage.classList.remove('hidden');
                    return;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    errorMessage.textContent = 'Nevalidna email adresa.';
                    errorMessage.classList.remove('hidden');
                    return;
                }
                if (password.length < 8 || !/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/.test(password)) {
                    errorMessage.textContent = 'Lozinka mora imati najmanje 8 karaktera i sadržati slova i brojeve.';
                    errorMessage.classList.remove('hidden');
                    return;
                }

                errorMessage.classList.add('hidden');

                const data = { username, email, password };

                try {
                    const response = await fetch('/register', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (response.ok) {
                        window.location.href = '/register/success';
                    } else {
                        errorMessage.textContent = result.error || 'Nepoznata greška';
                        errorMessage.classList.remove('hidden');
                    }
                } catch (error) {
                    errorMessage.textContent = 'Greška prilikom komunikacije sa serverom';
                    errorMessage.classList.remove('hidden');
                }
            });
        }
    });